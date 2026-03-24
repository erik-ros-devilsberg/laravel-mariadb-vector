<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Devilsberg\LaravelMariadbVector\Casts\VectorCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VectorCastIntegrationTest extends MariaDBTestCase
{
    private const TABLE = 'integration_vector_cast';

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackTable(self::TABLE);

        Schema::create(self::TABLE, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3)->nullable();
        });
    }

    // ---------------------------------------------------------------
    // AC: Integration test verifies VectorCast::get() correctly
    //     decodes MariaDB's actual binary VECTOR return format
    // ---------------------------------------------------------------

    public function test_vector_cast_get_decodes_mariadb_binary_format(): void
    {
        // Insert a vector directly using VEC_FromText
        DB::table(self::TABLE)->insert([
            'id' => 1,
            'embedding' => DB::raw("VEC_FromText('[0.1, 0.2, 0.3]')"),
        ]);

        // Read it back via raw query (no Eloquent cast) to get the raw binary
        $row = DB::table(self::TABLE)->where('id', 1)->first();

        // Now pass the raw binary through VectorCast::get()
        $cast = new VectorCast();
        $model = $this->createTestModel();

        $result = $cast->get($model, 'embedding', $row->embedding, []);

        $this->assertIsArray($result, 'VectorCast::get() should decode binary VECTOR to array');
        $this->assertCount(3, $result, 'Decoded array should have 3 elements');
        $this->assertEqualsWithDelta(0.1, $result[0], 0.0001, 'First element should be ~0.1');
        $this->assertEqualsWithDelta(0.2, $result[1], 0.0001, 'Second element should be ~0.2');
        $this->assertEqualsWithDelta(0.3, $result[2], 0.0001, 'Third element should be ~0.3');
    }

    // ---------------------------------------------------------------
    // AC: Integration test verifies VectorCast round-trip: set a
    //     float array via Eloquent model, read it back, values match
    // ---------------------------------------------------------------

    public function test_vector_cast_round_trip_via_eloquent_model(): void
    {
        $model = $this->createTestModel();
        $model->embedding = [0.4, 0.5, 0.6];
        $model->save();

        // Read it back via a fresh Eloquent query
        $fresh = $this->createTestModel()::query()->find($model->id);

        $this->assertNotNull($fresh, 'Model should be retrievable');
        $this->assertIsArray($fresh->embedding, 'Embedding should be cast to array');
        $this->assertCount(3, $fresh->embedding, 'Embedding should have 3 elements');
        $this->assertEqualsWithDelta(0.4, $fresh->embedding[0], 0.0001, 'First element should match');
        $this->assertEqualsWithDelta(0.5, $fresh->embedding[1], 0.0001, 'Second element should match');
        $this->assertEqualsWithDelta(0.6, $fresh->embedding[2], 0.0001, 'Third element should match');
    }

    public function test_vector_cast_round_trip_with_null(): void
    {
        $model = $this->createTestModel();
        $model->embedding = null;
        $model->save();

        $fresh = $this->createTestModel()::query()->find($model->id);

        $this->assertNotNull($fresh, 'Model should be retrievable');
        $this->assertNull($fresh->embedding, 'Null embedding should round-trip as null');
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function createTestModel(): Model
    {
        return new class extends Model {
            protected $table = 'integration_vector_cast';

            public $timestamps = false;

            protected function casts(): array
            {
                return [
                    'embedding' => VectorCast::class,
                ];
            }
        };
    }
}
