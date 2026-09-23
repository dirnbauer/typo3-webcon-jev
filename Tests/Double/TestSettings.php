<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Double;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Webconsulting\WebconJev\Configuration\Settings;

/**
 * The extension configuration a test states, instead of whatever an installation stored.
 */
final class TestSettings
{
    /**
     * @param array<string, string> $values As ext_conf_template.txt stores them: strings
     */
    public static function with(array $values): Settings
    {
        $configuration = new readonly class ($values) extends ExtensionConfiguration {
            /**
             * @param array<string, string> $values
             */
            public function __construct(private array $values) {}

            public function get(string $extension, string $path = ''): mixed
            {
                return $this->values;
            }
        };

        return new Settings($configuration);
    }
}
