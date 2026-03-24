<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Devilsberg\LaravelMariadbVector\Casts\VectorCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EloquentModelLifecycleIntegrationTest extends MariaDBTestCase
{
    private const TABLE = 'integration_eloquent_lifecycle';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireVectorSupport();

        $this->trackTable(self::TABLE);

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->vector('embedding', 3)->nullable();
        });
    }

    // ---------------------------------------------------------------
    // AC: Model::create() with vector attribute persists correctly
    // ---------------------------------------------------------------

    public function test_model_create_persists_vector_attribute(): void
    {
        $model = $this->makeModel();

        $created = $model::create(['name' => 'test', 'embedding' => [0.1, 0.2, 0.3]]);

        $this->assertNotNull($created->id, 'Created model should have an ID');

        $fresh = $model::find($created->id);

        $this->assertNotNull($fresh, 'Model should be retrievable after create');
        $this->assertIsArray($fresh->embedding, 'Embedding should be cast to array');
        $this->assertCount(3, $fresh->embedding);
        $this->assertEqualsWithDelta(0.1, $fresh->embedding[0], 0.0001);
        $this->assertEqualsWithDelta(0.2, $fresh->embedding[1], 0.0001);
        $this->assertEqualsWithDelta(0.3, $fresh->embedding[2], 0.0001);
    }

    // ---------------------------------------------------------------
    // AC: ->update() persists new vector value correctly
    // ---------------------------------------------------------------

    public function test_model_update_replaces_vector_attribute(): void
    {
        $model = $this->makeModel();
        $record = $model::create(['name' => 'original', 'embedding' => [0.1, 0.2, 0.3]]);

        $record->update(['embedding' => [0.7, 0.8, 0.9]]);

        $fresh = $model::find($record->id);

        $this->assertIsArray($fresh->embedding);
        $this->assertEqualsWithDelta(0.7, $fresh->embedding[0], 0.0001, 'First value should be updated');
        $this->assertEqualsWithDelta(0.8, $fresh->embedding[1], 0.0001, 'Second value should be updated');
        $this->assertEqualsWithDelta(0.9, $fresh->embedding[2], 0.0001, 'Third value should be updated');
    }

    // ---------------------------------------------------------------
    // AC: Mass-assignment via $fillable works with vector attribute
    // ---------------------------------------------------------------

    public function test_mass_assignment_via_fillable_persists_vector(): void
    {
        $model = $this->makeModel();

        // fill() + save() exercises the fillable guard
        $instance = $model->fill(['name' => 'filled', 'embedding' => [0.4, 0.5, 0.6]]);
        $instance->save();

        $fresh = $model::find($instance->id);

        $this->assertNotNull($fresh, 'Filled model should be retrievable');
        $this->assertIsArray($fresh->embedding);
        $this->assertEqualsWithDelta(0.4, $fresh->embedding[0], 0.0001);
        $this->assertEqualsWithDelta(0.5, $fresh->embedding[1], 0.0001);
        $this->assertEqualsWithDelta(0.6, $fresh->embedding[2], 0.0001);
    }

    // ---------------------------------------------------------------
    // AC: Null vector persists and round-trips as null
    // ---------------------------------------------------------------

    public function test_model_create_with_null_embedding(): void
    {
        $model = $this->makeModel();

        $created = $model::create(['name' => 'no-embedding', 'embedding' => null]);

        $fresh = $model::find($created->id);

        $this->assertNull($fresh->embedding, 'Null embedding should round-trip as null');
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function makeModel(): Model
    {
        return new class extends Model {
            protected $table = 'integration_eloquent_lifecycle';

            public $timestamps = false;

            protected $fillable = ['name', 'embedding'];

            protected function casts(): array
            {
                return [
                    'embedding' => VectorCast::class,
                ];
            }
        };
    }
}
