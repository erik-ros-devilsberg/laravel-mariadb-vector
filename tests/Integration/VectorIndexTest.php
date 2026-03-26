<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VectorIndexTest extends MariaDBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireVectorSupport();
    }

    // ---------------------------------------------------------------
    // AC: vectorIndex() inside Schema::create() creates the index
    // ---------------------------------------------------------------

    public function test_vector_index_is_created_with_schema_create(): void
    {
        $table = 'integration_vec_idx_create';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
            $blueprint->vectorIndex('embedding');
        });

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name');

        // Laravel's createIndexName() generates: {table}_{column}_vectorindex
        $this->assertContains(
            "{$table}_embedding_vectorindex",
            $indexes->all(),
            'Vector index should exist after Schema::create()'
        );
    }

    // ---------------------------------------------------------------
    // AC: vectorIndex() inside Schema::table() adds index to existing table
    // ---------------------------------------------------------------

    public function test_vector_index_is_added_via_schema_table(): void
    {
        $table = 'integration_vec_idx_alter';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
        });

        Schema::table($table, function ($blueprint) {
            $blueprint->vectorIndex('embedding');
        });

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name');

        $this->assertContains(
            "{$table}_embedding_vectorindex",
            $indexes->all(),
            'Vector index should exist after Schema::table() ALTER'
        );
    }

    // ---------------------------------------------------------------
    // AC: custom index name is used when provided
    // ---------------------------------------------------------------

    public function test_custom_index_name_is_used(): void
    {
        $table = 'integration_vec_idx_named';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
            $blueprint->vectorIndex('embedding', 'my_vector_idx');
        });

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name');

        $this->assertContains(
            'my_vector_idx',
            $indexes->all(),
            'Custom named vector index should exist'
        );
    }

    // ---------------------------------------------------------------
    // AC: vector search still works correctly after adding the index
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_works_with_vector_index(): void
    {
        $table = 'integration_vec_idx_search';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
            $blueprint->vectorIndex('embedding');
        });

        DB::table($table)->insert([
            ['id' => 1, 'embedding' => DB::raw("VEC_FromText('[1.0, 0.0, 0.0]')")],
            ['id' => 2, 'embedding' => DB::raw("VEC_FromText('[0.0, 1.0, 0.0]')")],
            ['id' => 3, 'embedding' => DB::raw("VEC_FromText('[0.0, 0.0, 1.0]')")],
        ]);

        // nearestNeighbors() is on Eloquent\Builder; use raw SQL here
        // to verify the index doesn't break distance queries.
        $results = DB::select(
            "SELECT id, VEC_DISTANCE_COSINE(embedding, VEC_FromText('[1.0, 0.0, 0.0]')) as distance
             FROM `{$table}`
             ORDER BY distance ASC
             LIMIT 1"
        );

        $this->assertCount(1, $results);
        $this->assertSame(1, (int) $results[0]->id);
    }

    // ---------------------------------------------------------------
    // AC: a vector index can be dropped
    // ---------------------------------------------------------------

    public function test_vector_index_can_be_dropped(): void
    {
        $table = 'integration_vec_idx_drop';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
            $blueprint->vectorIndex('embedding', 'vec_to_drop');
        });

        $before = collect(DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name');
        $this->assertContains('vec_to_drop', $before->all());

        Schema::table($table, function ($blueprint) {
            $blueprint->dropIndex('vec_to_drop');
        });

        $after = collect(DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name');
        $this->assertNotContains('vec_to_drop', $after->all());
    }

    // ---------------------------------------------------------------
    // AC: multiple vector columns can each have their own index
    //
    // MariaDB limitation: only one VECTOR INDEX per table is supported
    // as of MariaDB 11.7. This test documents that restriction.
    // ---------------------------------------------------------------

    public function test_multiple_vector_columns_can_each_have_an_index(): void
    {
        $table = 'integration_vec_idx_multi';
        $this->trackTable($table);

        // Create the table with two vector columns and the first index.
        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding_a', 3);
            $blueprint->vector('embedding_b', 3);
            $blueprint->vectorIndex('embedding_a', 'vec_idx_a');
        });

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name');
        $this->assertContains('vec_idx_a', $indexes->all());

        // MariaDB 11.7 does not support multiple VECTOR indexes on the same table.
        // Attempting to add a second one throws a QueryException.
        $this->expectException(\Illuminate\Database\QueryException::class);

        Schema::table($table, function ($blueprint) {
            $blueprint->vectorIndex('embedding_b', 'vec_idx_b');
        });
    }

    // ---------------------------------------------------------------
    // AC: Schema::hasIndex() detects a vector index
    // ---------------------------------------------------------------

    public function test_schema_has_index_returns_true_for_vector_index(): void
    {
        $table = 'integration_vec_idx_has';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
            $blueprint->vectorIndex('embedding', 'my_vec_idx');
        });

        $this->assertTrue(Schema::hasIndex($table, 'my_vec_idx'));
    }

    public function test_schema_has_index_returns_false_before_vector_index_is_added(): void
    {
        $table = 'integration_vec_idx_has_not';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
        });

        $this->assertFalse(Schema::hasIndex($table, 'vec_idx_missing'));
    }

    // ---------------------------------------------------------------
    // AC: vector index works on high-dimensional vectors
    // ---------------------------------------------------------------

    public function test_vector_index_works_with_high_dimensional_vectors(): void
    {
        $table = 'integration_vec_idx_highdim';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 1536);
            $blueprint->vectorIndex('embedding', 'vec_idx_highdim');
        });

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name');

        $this->assertContains('vec_idx_highdim', $indexes->all());
    }
}
