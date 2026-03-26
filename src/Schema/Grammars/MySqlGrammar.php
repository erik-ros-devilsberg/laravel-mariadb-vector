<?php

namespace Devilsberg\LaravelMariadbVector\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Extends Laravel's MySqlGrammar to compile Blueprint::vectorIndex() into
 * MariaDB's native VECTOR INDEX syntax.
 *
 * Covers users who configure MariaDB with driver = 'mysql' instead of 'mariadb'.
 * The base Grammar::compileVectorIndex() throws for all non-Postgres drivers;
 * this override generates the correct MariaDB DDL.
 */
class MySqlGrammar extends \Illuminate\Database\Schema\Grammars\MySqlGrammar
{
    public function __construct(?Connection $connection = null)
    {
        if ($connection !== null) {
            parent::__construct($connection);
        }
    }

    public function wrapTable($table, $prefix = null)
    {
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
