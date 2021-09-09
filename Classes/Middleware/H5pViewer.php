<?php
declare(strict_types=1);

namespace MichielRoos\H5p\Middleware;

/**
 * This file is part of the "h5p" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */


use MichielRoos\H5p\Adapter\Core\CoreFactory;
use MichielRoos\H5p\Adapter\Core\FileStorage;
use MichielRoos\H5p\Adapter\Core\Framework;
use MichielRoos\H5p\Controller\ViewController;
use MichielRoos\H5p\Domain\Repository\ContentRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;


class H5pViewer implements MiddlewareInterface
{


    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Calls the "unavailableAction" of the error controller if the system is in maintenance mode.
     * This only applies if the REMOTE_ADDR does not match the devIpMask
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        /** @var \Psr\Http\Message\UriInterface $requestedUri */
        $requestedUri = $request->getUri();
        if (strpos($requestedUri->getPath(), '/h5p/embed/') !== false) {
            return $this->processRequest($request);
        }

        return $handler->handle($request);
    }

    private function processRequest(ServerRequestInterface $request)
    {
        /** @var Response $response */
        $response = GeneralUtility::makeInstance(Response::class);

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $params = explode('/',$normalizedParams->getRequestUri());
        $id = (int)$params[array_key_last($params)];

        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);
        $content = $contentRepository->findByUid($id);
        if ($content === null) {
            return $response->withStatus(404, 'HP5 content not found');
        }


        $styles = \H5PCore::$styles;
        $scripts = \H5PCore::$scripts;


        //DebugUtility::debug($styles);

        $site = $request->getAttribute('site', null);

        $controller = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            $this->context,
            $site,
            $request->getAttribute('language', $site->getDefaultLanguage())
        );
        $GLOBALS['TSFE'] = $controller;

        /** @var SiteLanguage $language */
        $language = $request->getAttribute('language', $site->getDefaultLanguage());

        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();
        $h5pFramework = GeneralUtility::makeInstance(Framework::class, $storage);
        $h5pFileStorage = GeneralUtility::makeInstance(FileStorage::class, $storage);
        $h5pCore = GeneralUtility::makeInstance(CoreFactory::class, $h5pFramework, $h5pFileStorage, $language->getHreflang());

        $absoluteWebPath = PathUtility::getAbsoluteWebPath(ExtensionManagementUtility::extPath('h5p'));
        $url = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
        $cacheBuster = '?v=' . Framework::$version;
        $coreSettings = [
            'baseUrl'            => $url,
            'url'                => '/fileadmin/h5p',
            'postUserStatistics' => false,
            'ajax'               => [
                'setFinished'     => '',
                'contentUserData' => '',
            ],
            'saveFreq'           => $h5pFramework->getOption('save_content_state') ? $h5pFramework->getOption('save_content_frequency') : false,
            'siteUrl'            => $url,
            'l10n'               => [
                'H5P' => $h5pCore->getLocalization(),
            ],
            'hubIsEnabled'       => (int)$h5pFramework->getOption('hub_is_enabled') === 1,
            'reportingIsEnabled' => (int)$h5pFramework->getOption('enable_lrs_content_types') === 1,
            'libraryConfig'      => $h5pFramework->getLibraryConfig(),
            'crossorigin'        => defined('H5P_CROSSORIGIN') ? H5P_CROSSORIGIN : null,
            'pluginCacheBuster'  => $cacheBuster,
            'libraryUrl'         => $url . $absoluteWebPath . 'Resources/Public/Lib/h5p-core/js',
            'contents'           => []
        ];


        $contentSettings = [
            'url'            => '/fileadmin/h5p',
            'library'        => sprintf(
                '%s %d.%d.%d',
                $content->getLibrary()->getMachineName(),
                $content->getLibrary()->getMajorVersion(),
                $content->getLibrary()->getMinorVersion(),
                $content->getLibrary()->getPatchVersion()
            ),
            'jsonContent'    => $content->getFiltered(),
            'fullScreen'     => false,
            'exportUrl'      => '/path/to/download.h5p',
            'embedCode'      => '',
            'resizeCode'     => '',
            'mainId'         => $content->getUid(),
            'title'          => $content->getTitle(),
            'displayOptions' => [
                'frame'     => false,
                'export'    => false,
                'embed'     => false,
                'copyright' => false,
                'icon'      => false
            ]
        ];

        $contentSettings['displayOptions']['frame'] = \H5PCore::DISABLE_FRAME;
        #$contentSettings['displayOptions']['export'] = \H5PCore::DISABLE_DOWNLOAD;
        $contentSettings['displayOptions']['copyright'] = \H5PCore::DISABLE_COPYRIGHT;
        $contentSettings['displayOptions']['icon'] = \H5PCore::DISABLE_ABOUT;


        $contentLibrary = $content->getLibrary()->toAssocArray();
        $dependencyLibrary = $h5pCore->loadLibrary($contentLibrary['machineName'], $contentLibrary['majorVersion'], $contentLibrary['minorVersion']);
        $h5pCore->findLibraryDependencies($dependencies, $dependencyLibrary);
        if (is_array($dependencies)) {
            $dependencies = $h5pCore->orderDependenciesByWeight($dependencies);
            foreach ($dependencies as $key => $dependency) {
                if (strpos($key, 'preloaded-') !== 0) {
                    continue;
                }
                $this->setJsAndCss($dependency['library'], $contentSettings);
            }
        }

        $contentDependencies = $h5pFramework->loadContentDependencies($content->getUid(), 'preloaded');
        foreach ($contentDependencies as $dependency) {
            $this->setJsAndCss($dependency, $contentSettings);
        }

        $this->setJsAndCss($contentLibrary, $contentSettings);


        /** @var StandaloneView $standaloneView */
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setLayoutRootPaths(
            [GeneralUtility::getFileAbsFileName('EXT:h5p/Resources/Private/Layouts')]
        );
        $standaloneView->setPartialRootPaths(
            [GeneralUtility::getFileAbsFileName('EXT:h5p/Resources/Private/Partials')]
        );
        $standaloneView->setTemplateRootPaths(
            [GeneralUtility::getFileAbsFileName('EXT:h5p/Resources/Private/Templates')]
        );
        $standaloneView->setTemplate('View/Embed');
        $standaloneView->assignMultiple([
            'site' => $site,
            'language' => $language,
            'content' => $content,
            'styles' => $styles,
            'scripts' => $scripts,
            'h5pIntegration' => $coreSettings,
            'contentSettings' => $contentSettings
        ]);
        return new HtmlResponse($standaloneView->render());
    }


    /**
     * Set JS and CSS
     * @param array $library
     * @param array $settings
     */
    private function setJsAndCss(array $library, array &$settings)
    {
        $name = $library['machineName'] . '-' . $library['majorVersion'] . '.' . $library['minorVersion'];
        $preloadCss = explode(',', $library['preloadedCss']);
        $preloadJs = explode(',', $library['preloadedJs']);
        $cacheBuster = '?v=' . Framework::$version;

        if (!array_key_exists('scripts', $settings)) {
            $settings['scripts'] = [];
        }

        if (!array_key_exists('styles', $settings)) {
            $settings['styles'] = [];
        }

        foreach ($preloadJs as $js) {
            $js = trim($js);
            if ($js) {
                $settings['scripts'][] = '/fileadmin/h5p/libraries/' . $name . '/' . $js . $cacheBuster;
            }
        }
        foreach ($preloadCss as $css) {
            $css = trim($css);
            if ($css) {
                $settings['styles'][] = '/fileadmin/h5p/libraries/' . $name . '/' . $css . $cacheBuster;
            }
        }
    }
}
