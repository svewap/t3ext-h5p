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
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Resolves static routes - can return configured content directly or load content from file / urls
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

    public function __construct(
        RequestFactory $requestFactory,
        LinkService $linkService
    ) {
        $this->requestFactory = $requestFactory;
        $this->linkService = $linkService;
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
            $path = ltrim($request->getUri()->getPath(), '/');
            foreach ($configuration as $route) {
                $routePath = ltrim(trim($site->getBase()->getPath(), '/') . '/' . ltrim($route['route'], '/'), '/');
                if ($route['type'] === 'h5pembed' && strpos($path, $routePath) !== false) {

                    $source = 't3://page?uid='.$site->getRootPageId().'&type=723442';
                    $params = explode('/',$path);
                    $id = (int)$params[array_key_last($params)];

                    $source .= '&tx_h5p_embedded[contentId]='.$id;

                    $urlParams = $this->linkService->resolve($source);
                    $uri = $urlParams['url'] ?? $this->getPageUri($request, $site, $urlParams);
                    [$content, $contentType] = $this->getFromUri($uri);

                    return new HtmlResponse($content, 200, ['Content-Type' => $contentType]);
                }
            }

        }
        return $handler->handle($request);
    }


    /**
     * @param File $file
     * @return array
     */
    protected function getFromFile(File $file): array
    {
        $content = $file->getContents();
        $contentType = $file->getMimeType();
        return [$content, $contentType];
    }

    /**
     * @param string $uri
     * @return array
     */
    protected function getFromUri(string $uri): array
    {
        $response = $this->requestFactory->request($uri);
        $contentType = 'text/plain; charset=utf-8';
        $content = '';
        if ($response->getStatusCode() === 200) {
            $content = $response->getBody()->getContents();
            $contentType = $response->getHeader('Content-Type');
        }

        return [$content, $contentType];
    }

    /**
     * @param ServerRequestInterface $request
     * @param Site $site
     * @param array $urlParams
     * @return string
     * @throws InvalidRouteArgumentsException
     */
    protected function getPageUri(ServerRequestInterface $request, Site $site, array $urlParams): string
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
            RouterInterface::ABSOLUTE_URL
        );
        return (string)$uri;
    }

}
