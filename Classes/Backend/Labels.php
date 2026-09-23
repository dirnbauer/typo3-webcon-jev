<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The module's labels, in the backend user's language.
 *
 * They live in the "webcon_jev.module" domain (Resources/Private/Language/module.xlf), which the
 * templates read with f:translate and the JavaScript imports as "~labels/webcon_jev.module".
 */
final readonly class Labels
{
    public const string DOMAIN = 'webcon_jev.module';

    public function __construct(private LanguageServiceFactory $languageServiceFactory) {}

    /**
     * @param list<string|int|float>|array<string, string|int|float> $arguments sprintf values in order, or ICU values by name
     */
    public function get(string $key, array $arguments = []): string
    {
        $label = $this->languageService()->translate($key, self::DOMAIN, $arguments);

        return $label === null ? $key : (string)$label;
    }

    private function languageService(): LanguageService
    {
        $languageService = $GLOBALS['LANG'] ?? null;

        return $languageService instanceof LanguageService
            ? $languageService
            : $this->languageServiceFactory->create('default');
    }
}
