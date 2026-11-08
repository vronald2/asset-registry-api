<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs the real migrations (DDL), so it does not use the transactional RefreshDatabase trait.
 */
final class MigrationTest extends TestCase
{
    private const array TABLES = ['devices', 'contracts', 'contract_device', 'api_request_logs'];

    public function testMigrationsCreateExpectedSchema(): void
    {
        $this->runCommand('migrate:fresh');

        self::assertTrue(Schema::hasColumns(
            'devices',
            ['id', 'name', 'manufacturer', 'model', 'description', 'created_at', 'updated_at'],
        ));
        self::assertTrue(Schema::hasColumns('contracts', [
            'id',
            'contract_number',
            'partner_name',
            'start_date',
            'end_date',
            'description',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns(
            'contract_device',
            ['id', 'contract_id', 'device_id', 'serial_number', 'created_at', 'updated_at'],
        ));

        self::assertTrue(Schema::hasColumns('api_request_logs', [
            'id',
            'method',
            'path',
            'route',
            'query',
            'request_headers',
            'request_body',
            'response_status',
            'response_headers',
            'response_body',
            'duration_ms',
            'ip',
            'user_agent',
            'created_at',
        ]));

        $this->assertUniqueIndex('contracts', ['contract_number']);
        $this->assertUniqueIndex('contract_device', ['serial_number']);
        $this->assertUniqueIndex('contract_device', ['contract_id', 'device_id']);

        $this->assertForeignKey('contract_device', 'contract_id', 'contracts', 'cascade');
        $this->assertForeignKey('contract_device', 'device_id', 'devices', 'restrict');
    }

    public function testMigrationsCanBeRolledBack(): void
    {
        $this->runCommand('migrate:fresh');

        $this->runCommand('migrate:reset');

        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), "The {$table} table should have been dropped.");
        }

        $this->runCommand('migrate');

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), "The {$table} table should have been recreated.");
        }
    }

    private function runCommand(string $command): void
    {
        self::assertSame(0, Artisan::call($command), Artisan::output());
    }

    /**
     * @param list<string> $columns
     */
    private function assertUniqueIndex(string $table, array $columns): void
    {
        $matching = array_filter(
            Schema::getIndexes($table),
            static fn (array $index): bool => $index['unique'] && $index['columns'] === $columns,
        );

        self::assertNotEmpty($matching, sprintf('Missing unique index on %s(%s).', $table, implode(', ', $columns)));
    }

    private function assertForeignKey(string $table, string $column, string $foreignTable, string $onDelete): void
    {
        $matching = array_filter(
            Schema::getForeignKeys($table),
            static fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]
                && $foreignKey['foreign_table'] === $foreignTable
                && strtolower((string) $foreignKey['on_delete']) === $onDelete,
        );

        self::assertNotEmpty(
            $matching,
            sprintf('Missing foreign key %s.%s -> %s (on delete %s).', $table, $column, $foreignTable, $onDelete),
        );
    }
}
