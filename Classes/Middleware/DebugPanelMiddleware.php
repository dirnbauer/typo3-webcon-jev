<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Middleware;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\PathUtility;
use Webconsulting\WebconJev\Debug\DebugLog;
use Webconsulting\WebconJev\Debug\DebugPanelRenderer;
use Webconsulting\WebconJev\Debug\DebugSettings;

/**
 * Shows what Jev decided, when `plugin.tx_webconjev.settings.debug` is on.
 *
 * Jev is asked in two kinds of frontend request, and the panel travels with each:
 *  - powermail_cond's condition endpoint answers JSON, so the panel goes into that JSON as
 *    `webconJevDebug.html`, and the frontend script puts it under the form as the visitor types;
 *  - a submission renders the thank-you page, so the routing panel is appended to that page.
 *
 * A page with a powermail form also gets the script that shows the panel under it. It is added
 * here, where the switch is read, and not by TypoScript: the documented way to scope the switch,
 * a condition setting plugin.tx_webconjev.settings.debug, changes the setup value only, which an
 * include gated on the constant never saw.
 *
 * Any other request that asked Jev nothing passes through untouched, whatever the setting says.
 */
final readonly class DebugPanelMiddleware implements MiddlewareInterface
{
    public const string JSON_KEY = 'webconJevDebug';

    public const string SCRIPT = 'EXT:webcon_jev/Resources/Public/JavaScript/frontend-debug.js';

    public function __construct(
        private DebugLog $log,
        private DebugSettings $settings,
        private DebugPanelRenderer $renderer,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($this->log->isEmpty()) {
            return $this->withScript($request, $response);
        }

        try {
            if (!$this->settings->isEnabled($request)) {
                return $response;
            }

            $contentType = strtolower($response->getHeaderLine('Content-Type'));
            if (str_contains($contentType, 'application/json')) {
                return $this->intoJson($response, $this->renderer->render($this->log, $request));
            }
            if (str_contains($contentType, 'text/html')) {
                return $this->intoHtml($response, $this->renderer->render($this->log, $request));
            }

            return $response;
        } finally {
            $this->log->clear();
        }
    }

    /**
     * The live panel's script, on a page with a powermail form, while the switch is on.
     */
    private function withScript(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/html')) {
            return $response;
        }
        $body = (string)$response->getBody();
        if (!str_contains($body, 'powermail_form') || !$this->settings->isEnabled($request)) {
            return $response;
        }

        $withScript = self::withScriptTag($body, PathUtility::getPublicResourceWebPath(self::SCRIPT));

        return $withScript === $body ? $response : $this->withBody($response, $withScript);
    }

    /**
     * The script tag before </body>, unless the page loads the script already.
     */
    public static function withScriptTag(string $body, string $scriptUrl): string
    {
        if (str_contains($body, 'frontend-debug.js')) {
            return $body;
        }

        $tag = '<script src="' . htmlspecialchars($scriptUrl) . '" defer></script>';
        $position = strripos($body, '</body>');

        return $position === false ? $body . $tag : substr($body, 0, $position) . $tag . substr($body, $position);
    }

    private function intoJson(ResponseInterface $response, string $panel): ResponseInterface
    {
        try {
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $response;
        }
        if (!is_array($data)) {
            return $response;
        }

        $data[self::JSON_KEY] = ['html' => $panel];

        return $this->withBody($response, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function intoHtml(ResponseInterface $response, string $panel): ResponseInterface
    {
        $body = (string)$response->getBody();
        $host = '<div class="webcon-jev-debug-host" data-webcon-jev-debug="page">' . $panel . '</div>';
        $position = strripos($body, '</body>');
        $body = $position === false
            ? $body . $host
            : substr($body, 0, $position) . $host . substr($body, $position);

        return $this->withBody($response, $body);
    }

    private function withBody(ResponseInterface $response, string $body): ResponseInterface
    {
        $response = $response->withBody($this->streamFactory->createStream($body));

        return $response->hasHeader('Content-Length')
            ? $response->withHeader('Content-Length', (string)strlen($body))
            : $response;
    }
}
