<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend;

use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Support\Cast;

/**
 * What the run log calls the place a run came from.
 *
 * The contexts this extension writes have labels of their own. An integration that passes its own
 * context to DecisionRunner::run() names it in its ext_localconf.php:
 *
 *     $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['webcon_jev']['runContexts']['my_extension_import']
 *         = 'LLL:EXT:my_extension/Resources/Private/Language/locallang.xlf:jev.context';
 *
 * The label is anything LanguageService::sL() resolves: an LLL: reference, a translation domain
 * reference ("my_extension.messages:jev.context"), or plain text. A context nobody labelled — or
 * whose label no longer resolves because its extension is gone — is shown as written.
 */
final readonly class RunContextLabels
{
    private const array OWN = [
        RunLogger::CONTEXT_CONDITION,
        RunLogger::CONTEXT_FINISHER,
        RunLogger::CONTEXT_PLAYGROUND,
        RunLogger::CONTEXT_CLI,
    ];

    public function __construct(private Labels $labels) {}

    public function label(string $context): string
    {
        if (in_array($context, self::OWN, true)) {
            return $this->labels->get('context.' . $context);
        }

        $reference = Cast::trimmed(self::registered()[$context] ?? null);
        $label = $reference !== '' ? trim($this->labels->resolve($reference)) : '';

        return $label !== '' ? $label : $context;
    }

    /**
     * @return array<array-key, mixed> Context => label reference, as integrations registered them
     */
    private static function registered(): array
    {
        $extensionConfiguration = Cast::map(Cast::map($GLOBALS['TYPO3_CONF_VARS'] ?? null)['EXTCONF'] ?? null);

        return Cast::map(Cast::map($extensionConfiguration['webcon_jev'] ?? null)['runContexts'] ?? null);
    }
}
