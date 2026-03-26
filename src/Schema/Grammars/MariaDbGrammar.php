<?php

namespace Devilsberg\LaravelMariadbVector\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Extends Laravel's MariaDbGrammar to compile Blueprint::vectorIndex() into
 * MariaDB's native VECTOR INDEX syntax.
 *
 * The base Grammar::compileVectorIndex() throws RuntimeException for all
 * non-Postgres drivers. This override generates the correct MariaDB DDL:
 * ALTER TABLE `t` ADD VECTOR INDEX `idx` (`col`)
 */
class MariaDbGrammar extends \Illuminate\Database\Schema\Grammars\MariaDbGrammar
{
    public function __construct(?Connection $connection = null)
    {
        if ($connection !== null) {
            parent::__construct($connection);
        }
    }

    public function wrapTable($table, $prefix = null)
    {
        // When instantiated without a connection (e.g. in tests), fall back to
        // an empty prefix rather than calling getTablePrefix() on null.
        $prefix ??= $this->connection?->getTablePrefix() ?? '';

        return parent::wrapTable($table, $prefix);
    }

    public function compileVectorIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add vector index %s (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }
}
