<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Devilsberg\LaravelMariadbVector\Distance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @group integration
 */
class NearestNeighborsMacroIntegrationTest extends MariaDBTestCase
{
    private const TABLE = 'integration_nn_items';

    /**
     * Vectors used across all tests.
     *
     *   near          [1, 0, 0]        — cosine distance to query = 0.0   (identical → score ≈ 1.0)
     *   somewhat_near [0.866, 0.5, 0]  — cosine distance to query ≈ 0.134 (score ≈ 0.866)
     *   far           [0, 0, 1]        — cosine distance to query = 1.0   (orthogonal → score = 0.0)
     *
     * Query vector: [1, 0, 0]
     */

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireVectorSupport();

        $this->trackTable(self::TABLE);

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->vector('embedding', 3)->nullable();
        });

        DB::table(self::TABLE)->insert([
            ['name' => 'near',          'embedding' => DB::raw("VEC_FromText('[1.0, 0.0, 0.0]')")],
            ['name' => 'somewhat_near', 'embedding' => DB::raw("VEC_FromText('[0.866, 0.5, 0.0]')")],
            ['name' => 'far',           'embedding' => DB::raw("VEC_FromText('[0.0, 0.0, 1.0]')")],
        ]);
    }

    // ---------------------------------------------------------------
    // AC: nearestNeighbors returns rows in descending score order
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_returns_rows_ordered_by_score_descending(): void
    {
        $names = $this->makeModel()::query()
            ->nearestNeighbors('embedding', [1.0, 0.0, 0.0])
            ->pluck('name')
            ->all();

        $this->assertCount(3, $names);
        $this->assertSame('near', $names[0], "'near' should have the highest score (first)");
        $this->assertSame('somewhat_near', $names[1], "'somewhat_near' should be second");
        $this->assertSame('far', $names[2], "'far' should have the lowest score (last)");
    }

    // ---------------------------------------------------------------
    // AC: result rows carry a numeric 'score' column
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_score_column_is_present(): void
    {
        $results = $this->makeModel()::query()
            ->nearestNeighbors('embedding', [1.0, 0.0, 0.0])
            ->get();

        foreach ($results as $row) {
            $this->assertArrayHasKey('score', $row->getAttributes(), 'Each row should have a "score" key');
        }
    }

    public function test_nearest_neighbors_score_is_numeric(): void
    {
        $results = $this->makeModel()::query()
            ->nearestNeighbors('embedding', [1.0, 0.0, 0.0])
            ->get();

        foreach ($results as $row) {
            $this->assertIsNumeric($row->score, '"score" should be a numeric value');
        }
    }

    // ---------------------------------------------------------------
    // AC: cosine score of a vector against itself is approximately 1.0
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_cosine_score_of_identical_vector_is_approximately_one(): void
    {
        $row = $this->makeModel()::query()
            ->nearestNeighbors('embedding', [1.0, 0.0, 0.0])
            ->where('name', 'near')
            ->first();

        $this->assertNotNull($row, 'Query should return the "near" row');
        $this->assertEqualsWithDelta(1.0, (float) $row->score, 0.001, "Cosine score of identical vector should be ≈ 1.0");
    }

    // ---------------------------------------------------------------
    // AC: take() limits the result set
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_take_limits_results(): void
    {
        $results = $this->makeModel()::query()
            ->nearestNeighbors('embedding', [1.0, 0.0, 0.0])
            ->take(2)
            ->get();

        $this->assertCount(2, $results, 'take(2) should return exactly 2 rows');
    }

    // ---------------------------------------------------------------
    // AC: EUCLIDEAN distance also produces correctly ordered results
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_with_euclidean_returns_ordered_results(): void
    {
        // EUCLIDEAN distances from [1, 0, 0]:
        //   near          [1, 0, 0]      → 0.0  (score ≈ 1.0)
        //   somewhat_near [0.866, 0.5, 0] → ≈ 0.518 (score ≈ 0.634)
        //   far           [0, 0, 1]      → √2 ≈ 1.414 (score ≈ 0)
        $names = $this->makeModel()::query()
            ->nearestNeighbors('embedding', [1.0, 0.0, 0.0], Distance::Euclidean)
            ->pluck('name')
            ->all();

        $this->assertCount(3, $names);
        $this->assertSame('near', $names[0], "'near' should have the highest score (first) with EUCLIDEAN");
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function makeModel(): Model
    {
        return new class extends Model {
            protected $table = 'integration_nn_items';

            public $timestamps = false;
        };
    }
}
