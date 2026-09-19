<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * What every functional test here needs: this extension and the vault it depends on.
 *
 * powermail and powermail_cond are deliberately absent. Their integrations are registered only
 * when they are installed, so the extension has to boot without them — and a suite that proves
 * that is worth more than one that needs them.
 */
abstract class AbstractJevTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['backend', 'install'];

    protected array $testExtensionsToLoad = ['nr_vault', 'webcon_jev'];

    /**
     * @return array<string, mixed>
     */
    protected function row(string $table, int $uid): array
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable($table)
            ->select(['*'], $table, ['uid' => $uid])
            ->fetchAssociative();
        self::assertIsArray($row, sprintf('%s row %d exists', $table, $uid));

        return $row;
    }
}
