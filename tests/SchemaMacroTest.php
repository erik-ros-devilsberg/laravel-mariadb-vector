<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class SchemaMacroTest extends TestCase
{
    /**
     * Create a Blueprint backed by the test database connection.
     */
    protected function createBlueprint(string $table = 'test_table'): Blueprint
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection();
        $connection->useDefaultSchemaGrammar();

        return new Blueprint($connection, $table);
    }

    // ---------------------------------------------------------------
    // Laravel 12 provides Blueprint::vector() natively — no macro
    // ---------------------------------------------------------------

    public function test_vector_is_a_native_blueprint_method_not_a_macro(): void
    {
        $this->assertFalse(Blueprint::hasMacro('vector'), 'vector() should be native, not a macro');
        $this->assertTrue(method_exists(Blueprint::class, 'vector'));
    }

    public function test_service_provider_does_not_register_vector_macro(): void
    {
        $this->assertFalse(Blueprint::hasMacro('vector'));
    }

    // ---------------------------------------------------------------
    // Column definition
    // ---------------------------------------------------------------

    public function test_vector_adds_column_with_correct_name_and_dimensions(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->vector('embedding', 768);

        $columns = $blueprint->getColumns();

        $this->assertCount(1, $columns);
        $this->assertSame('embedding', $columns[0]->get('name'));
        $this->assertSame(768, $columns[0]->get('dimensions'));
    }

    public function test_vector_works_with_384_dimensions(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->vector('embedding_small', 384);

        $this->assertSame(384, $blueprint->getColumns()[0]->get('dimensions'));
    }

    public function test_vector_works_with_768_dimensions(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->vector('embedding_medium', 768);

        $this->assertSame(768, $blueprint->getColumns()[0]->get('dimensions'));
    }

    public function test_vector_works_with_1536_dimensions(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->vector('embedding_large', 1536);

        $this->assertSame(1536, $blueprint->getColumns()[0]->get('dimensions'));
    }

    public function test_vector_supports_nullable_chaining(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->vector('embedding', 768);

        $this->assertInstanceOf(ColumnDefinition::class, $column);
        $this->assertInstanceOf(ColumnDefinition::class, $column->nullable());
        $this->assertTrue($blueprint->getColumns()[0]->get('nullable'));
    }

    // ---------------------------------------------------------------
    // Boundary dimensions
    // Blueprint does not validate dimensions — MariaDB rejects invalid
    // values at DDL execution time with a SQL error.
    // ---------------------------------------------------------------

    public function test_blueprint_accepts_zero_dimensions_without_php_exception(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->vector('embedding', 0);

        $this->assertCount(1, $blueprint->getColumns());
    }

    public function test_blueprint_accepts_negative_dimensions_without_php_exception(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->vector('embedding', -1);

        $columns = $blueprint->getColumns();
        $this->assertCount(1, $columns);
        $this->assertSame(-1, $columns[0]->get('dimensions'));
    }
}
