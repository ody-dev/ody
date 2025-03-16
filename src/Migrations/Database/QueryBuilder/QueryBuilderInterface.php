<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Database\QueryBuilder;

use PDOStatement;
use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Migrations\Database\Element\MigrationView;

interface QueryBuilderInterface
{
    /**
     * @return string[] list of queries
     */
    public function createTable(MigrationTable $table): array;

    /**
     * @return string[] list of queries
     */
    public function dropTable(MigrationTable $table): array;

    /**
     * @return string[] list of queries
     */
    public function renameTable(MigrationTable $table): array;

    /**
     * @return array<string|PDOStatement> list of queries
     */
    public function alterTable(MigrationTable $table): array;

    /**
     * @return array<string|PDOStatement> list of queries
     */
    public function copyTable(MigrationTable $table): array;

    /**
     * @return string[] list of queries
     */
    public function truncateTable(MigrationTable $table): array;

    /**
     * @return string[] list of queries
     */
    public function createView(MigrationView $view): array;

    /**
     * @return string[] list of queries
     */
    public function replaceView(MigrationView $view): array;

    /**
     * @return string[] list of queries
     */
    public function dropView(MigrationView $view): array;

    public function escapeString(?string $string): string;
}
