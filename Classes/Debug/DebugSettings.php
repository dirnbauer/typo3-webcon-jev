<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Reads `plugin.tx_webconjev.settings.debug` — the switch for the frontend debug panel.
 *
 * TypoScript, the way extensions have always offered this: a constant (or site setting) of the
 * same name feeds it, and a TypoScript condition can turn it on for one context, one page or one
 * logged-in backend user only.
 */
final readonly class DebugSettings
{
    public function isEnabled(ServerRequestInterface $request): bool
    {
        $typoScript = $request->getAttribute('frontend.typoscript');
        if (!$typoScript instanceof FrontendTypoScript) {
            return false;
        }
        // A page served from the page cache never computes its setup, and getSetupArray() says so
        // by throwing. Such a page never asks Jev either: conditions and routing are uncached.
        try {
            $settings = $typoScript->getSetupArray();
        } catch (RuntimeException) {
            return false;
        }
        foreach (['plugin.', 'tx_webconjev.', 'settings.'] as $key) {
            $settings = Cast::map($settings[$key] ?? null);
        }
        $value = $settings['debug'] ?? null;

        return is_scalar($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
