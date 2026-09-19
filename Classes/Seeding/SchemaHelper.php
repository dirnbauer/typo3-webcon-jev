<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Seeding;

use Throwable;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Which columns a table actually has, so a seeder can write a row that names more.
 *
 * powermail and powermail_cond both gain and lose columns between versions; filtering here means
 * a missing one is a field that does not get set, rather than a fatal insert.
 */
final class SchemaHelper
{
    /** @var array<string, array<string, true>> */
    private array $columns = [];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function tableExists(string $table): bool
    {
        return $this->columnsOf($table) !== [];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function filter(string $table, array $row): array
    {
        return array_intersect_key($row, $this->columnsOf($table));
    }

    /**
     * @return array<string, true>
     */
    private function columnsOf(string $table): array
    {
        if (isset($this->columns[$table])) {
            return $this->columns[$table];
        }

        $names = [];
        try {
            $schemaManager = $this->connectionPool->getConnectionForTable($table)->createSchemaManager();
            foreach ($schemaManager->listTableColumns($table) as $column) {
                $names[$column->getName()] = true;
            }
        } catch (Throwable) {
            $names = [];
        }

        return $this->columns[$table] = $names;
    }
}
