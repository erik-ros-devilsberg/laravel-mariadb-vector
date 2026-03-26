<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

use Devilsberg\LaravelMariadbVector\Schema\Grammars\MariaDbGrammar;
use Devilsberg\LaravelMariadbVector\Schema\Grammars\MySqlGrammar;
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
    // vectorIndex — native Blueprint method, grammar subclass
    //
    // Laravel 12 provides Blueprint::vectorIndex() natively. The base
    // Grammar::compileVectorIndex() throws for non-Postgres drivers.
    // This package swaps in grammar subclasses that compile the command
    // into MariaDB's native ADD VECTOR INDEX syntax.
    // ---------------------------------------------------------------

    public function test_vector_index_is_a_native_blueprint_method_not_a_macro(): void
    {
        $this->assertFalse(Blueprint::hasMacro('vectorIndex'), 'vectorIndex() should be native, not a macro');
        $this->assertTrue(method_exists(Blueprint::class, 'vectorIndex'));
    }

    public function test_service_provider_sets_custom_grammar_on_default_connection(): void
    {
        $grammar = $this->app['db']->connection()->getSchemaGrammar();

        $this->assertTrue(
            $grammar instanceof MariaDbGrammar || $grammar instanceof MySqlGrammar,
            'The default connection grammar should be our vector-aware subclass'
        );
    }

    public function test_mariadb_grammar_compiles_vector_index_to_correct_sql(): void
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection();

        $blueprint = new Blueprint($connection, 'articles');
        $blueprint->vectorIndex('embedding');

        $grammar = $connection->getSchemaGrammar();
        $command = $blueprint->getCommands()[0];
        $sql = $grammar->compileVectorIndex($blueprint, $command);

        $this->assertStringContainsString('add vector index', $sql);
        $this->assertStringContainsString('`articles`', $sql);
        $this->assertStringContainsString('`embedding`', $sql);
    }

    public function test_mariadb_grammar_compiles_exact_sql_format(): void
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection();

        $blueprint = new Blueprint($connection, 'articles');
        $blueprint->vectorIndex('embedding', 'vec_idx');

        $grammar = $connection->getSchemaGrammar();
        $command = $blueprint->getCommands()[0];
        $sql = $grammar->compileVectorIndex($blueprint, $command);

        $this->assertSame('alter table `articles` add vector index `vec_idx` (`embedding`)', $sql);
    }

    public function test_mysql_grammar_compiles_vector_index_to_correct_sql(): void
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection();

        $blueprint = new Blueprint($connection, 'articles');
        $blueprint->vectorIndex('embedding');

        $grammar = new MySqlGrammar();
        $command = $blueprint->getCommands()[0];
        $sql = $grammar->compileVectorIndex($blueprint, $command);

        $this->assertStringContainsString('add vector index', $sql);
        $this->assertStringContainsString('`articles`', $sql);
        $this->assertStringContainsString('`embedding`', $sql);
    }

    public function test_mysql_grammar_compiles_exact_sql_format(): void
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection();

        $blueprint = new Blueprint($connection, 'articles');
        $blueprint->vectorIndex('embedding', 'vec_idx');

        $grammar = new MySqlGrammar();
        $command = $blueprint->getCommands()[0];
        $sql = $grammar->compileVectorIndex($blueprint, $command);

        $this->assertSame('alter table `articles` add vector index `vec_idx` (`embedding`)', $sql);
    }

    public function test_mysql_and_mariadb_grammars_produce_identical_sql(): void
    {
        /** @var Connection $connection */
        $connection = $this->app['db']->connection();

        $blueprint = new Blueprint($connection, 'documents');
        $blueprint->vectorIndex('content_vec', 'idx_content');

        $command = $blueprint->getCommands()[0];

        $mariaDbSql = (new MariaDbGrammar())->compileVectorIndex($blueprint, $command);
        $mySqlSql = (new MySqlGrammar())->compileVectorIndex($blueprint, $command);

        $this->assertSame($mariaDbSql, $mySqlSql);
    }

    public function test_vector_index_command_auto_generates_index_name(): void
    {
        $blueprint = $this->createBlueprint('articles');
        $blueprint->vectorIndex('embedding');

        $command = $blueprint->getCommands()[0];

        $this->assertSame('vectorIndex', $command->name);
        $this->assertStringContainsString('articles', $command->index);
        $this->assertStringContainsString('embedding', $command->index);
    }

    public function test_vector_index_command_accepts_custom_index_name(): void
    {
        $blueprint = $this->createBlueprint('articles');
        $blueprint->vectorIndex('embedding', 'my_custom_vec_idx');

        $this->assertSame('my_custom_vec_idx', $blueprint->getCommands()[0]->index);
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
