<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace MichielRoos\H5p\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 *
 * routeEnhancers.Suffix.map.h5pembed: 723442
 *
 * routes:
 *  -
 *    route: /h5p/embed/
 *    type: h5pRoute
 *    source: 't3://page?uid=<PAGE_ID>&type=723442'
 *
 */
class H5pRouteResolver implements MiddlewareInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var LinkService
     */
    protected $linkService;

    /**
     * @var SiteMatcher
     */
    protected $matcher;

    public function __construct(
        RequestFactory $requestFactory,
        LinkService    $linkService,
        SiteMatcher $matcher
    )
    {
        $this->requestFactory = $requestFactory;
        $this->linkService = $linkService;
        $this->matcher = $matcher;
    }

    /**
     * Checks if there is a valid site with route configuration.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        if (($site = $request->getAttribute('site', null)) instanceof Site &&
            ($configuration = $site->getConfiguration()['routes'] ?? null)
        ) {

            foreach ($configuration as $route) {
                if (($route['type'] ?? '') === 'h5pRoute' && isset($route['route']) && strpos($request->getUri()->getPath(), $route['route']) !== false) {
                    $request = $this->performRequest($request, $site, $route);
                }
            }

        }

        return $handler->handle($request);
    }


    private function performRequest(ServerRequestInterface $request, Site $site, array $route) {


        $queryParams = $request->getQueryParams();

        $params = explode('/', $request->getUri()->getPath());
        $hp5Id = (int)$params[array_key_last($params)];

        $tx_h5p_embedded = [
            'contentId' => $hp5Id
        ];
        foreach ($queryParams as $key => $value) {
            $tx_h5p_embedded[$key] = $value;
        }

        $urlParams = $this->linkService->resolve($route['source']);

        $path = $this->getPagePath($request, $site, $urlParams );

        $uri = $request->getUri();
        $uri = $uri->withPath($path);


        $request = $request->withUri($uri, true)->withQueryParams([
            'tx_h5p_embedded' => $tx_h5p_embedded,
        ]);

        /** @var SiteRouteResult $routeResult */
        $routeResult = $this->matcher->matchRequest($request);
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);
        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }

        return $request;
    }


    /**
     * @param ServerRequestInterface $request
     * @param Site $site
     * @param array $urlParams
     * @return string
     * @throws InvalidRouteArgumentsException
     */
    protected function getPagePath(ServerRequestInterface $request, Site $site, array $urlParams): string
    {
        $parameters = [];
        // Add additional parameters, if set via TypoLink
        if (isset($urlParams['parameters'])) {
            parse_str($urlParams['parameters'], $parameters);
        }
        $parameters['type'] = $urlParams['pagetype'] ?? 0;
        $parameters['_language'] = $request->getAttribute('language', null);
        $uri = $site->getRouter()->generateUri(
            (int)$urlParams['pageuid'],
            $parameters,
            '',
            RouterInterface::ABSOLUTE_PATH
        );
        return (string)$uri;
    }

}
