<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VectorColumnTest extends MariaDBTestCase
{
    // ---------------------------------------------------------------
    // AC: Integration test creates a table with a VECTOR(3) column
    //     on live MariaDB and verifies the DDL succeeds
    // ---------------------------------------------------------------

    public function test_create_table_with_vector_column_succeeds(): void
    {
        $table = 'integration_vector_ddl';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
        });

        $this->assertTrue(
            Schema::hasTable($table),
            'Table with VECTOR(3) column should be created successfully'
        );

        $this->assertTrue(
            Schema::hasColumn($table, 'embedding'),
            'Table should have an embedding column'
        );
    }

    // ---------------------------------------------------------------
    // AC: Integration test inserts a vector using VEC_FromText and
    //     verifies it can be read back
    // ---------------------------------------------------------------

    public function test_insert_and_read_vector_via_vec_from_text(): void
    {
        $table = 'integration_vector_insert';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
        });

        DB::table($table)->insert([
            'id' => 1,
            'embedding' => DB::raw("VEC_FromText('[0.1, 0.2, 0.3]')"),
        ]);

        $row = DB::table($table)->where('id', 1)->first();

        $this->assertNotNull($row, 'Row should be retrievable after insert');
        $this->assertNotNull($row->embedding, 'Embedding column should not be null');

        // MariaDB returns VECTOR as binary data; verify we got something back
        $this->assertNotEmpty($row->embedding, 'Embedding should contain data');
    }

    // ---------------------------------------------------------------
    // AC: Integration test verifies zero/negative dimensions are rejected
    //     by MariaDB at DDL execution time
    // ---------------------------------------------------------------

    public function test_zero_dimensions_throws_at_ddl_execution(): void
    {
        $table = 'integration_vector_zero_dims';
        $this->trackTable($table);

        $this->expectException(\Throwable::class);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 0);
        });
    }

    public function test_negative_dimensions_throws_at_ddl_execution(): void
    {
        $table = 'integration_vector_neg_dims';
        $this->trackTable($table);

        $this->expectException(\Throwable::class);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', -1);
        });
    }

    // ---------------------------------------------------------------
    // AC: Integration test documents MariaDB's upper dimension limit.
    //     MariaDB 11.7 supports up to 65,532 dimensions (16-bit unsigned
    //     dimension count field minus alignment bytes). 1536 (OpenAI
    //     text-embedding-3-large) and 4096 are well within the limit.
    // ---------------------------------------------------------------

    public function test_high_dimension_vector_1536_is_accepted(): void
    {
        $table = 'integration_vector_1536';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 1536);
        });

        $this->assertTrue(Schema::hasColumn($table, 'embedding'));
    }

    public function test_very_high_dimension_vector_4096_is_accepted(): void
    {
        $table = 'integration_vector_4096';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 4096);
        });

        $this->assertTrue(Schema::hasColumn($table, 'embedding'));
    }

    // ---------------------------------------------------------------
    // AC: Integration test creates a table with a nullable VECTOR(3)
    //     column, inserts a null, reads back null
    // ---------------------------------------------------------------

    public function test_nullable_vector_column_stores_and_reads_null(): void
    {
        $table = 'integration_vector_nullable';
        $this->trackTable($table);

        Schema::create($table, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3)->nullable();
        });

        DB::table($table)->insert([
            'id' => 1,
            'embedding' => null,
        ]);

        $row = DB::table($table)->where('id', 1)->first();

        $this->assertNotNull($row, 'Row should be retrievable');
        $this->assertNull($row->embedding, 'Nullable VECTOR column should store and return null');
    }
}
