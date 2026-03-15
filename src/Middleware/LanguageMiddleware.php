<?php

declare(strict_types=1);

namespace Minoo\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;
use Waaseyaa\I18n\LanguageManager;
use Waaseyaa\Routing\Language\UrlPrefixNegotiator;

final class LanguageMiddleware implements HttpMiddlewareInterface
{
    public function __construct(
        private readonly LanguageManager $languageManager,
        private readonly UrlPrefixNegotiator $negotiator,
    ) {}

    public function process(Request $request, HttpHandlerInterface $next): Response
    {
        $pathInfo = $request->getPathInfo();
        $availableLanguages = array_keys($this->languageManager->getLanguages());

        $detected = $this->negotiator->negotiate($pathInfo, [], $availableLanguages);

        if ($detected !== null) {
            $language = $this->languageManager->getLanguage($detected);
            if ($language !== null) {
                $this->languageManager->setCurrentLanguage($language);

                // Strip the language prefix from the path for routing
                $strippedPath = substr($pathInfo, strlen('/' . $detected));
                if ($strippedPath === '' || $strippedPath === false) {
                    $strippedPath = '/';
                }

                // Create a new request with the stripped path
                $request->server->set('REQUEST_URI', $strippedPath);
                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $request->server->all(),
                    $request->getContent(),
                );
            }
        }

        // Store current path without language prefix for templates
        $request->attributes->set('_language_prefix', $detected ?? '');
        $request->attributes->set('_original_path', $pathInfo);

        return $next->handle($request);
    }
}
