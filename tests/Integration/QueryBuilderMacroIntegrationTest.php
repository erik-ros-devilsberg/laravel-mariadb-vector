<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueryBuilderMacroIntegrationTest extends MariaDBTestCase
{
    private const TABLE = 'integration_macro_items';

    /**
     * Vectors used across all tests.
     *
     *   near          [1, 0, 0]         — cosine distance to query = 0.0   (identical)
     *   somewhat_near [0.866, 0.5, 0]   — cosine distance to query ≈ 0.134 (30° away)
     *   far           [0, 0, 1]         — cosine distance to query = 1.0   (orthogonal)
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
    // AC: whereVectorSimilarTo returns only rows below the threshold
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_filters_rows_by_threshold(): void
    {
        // Threshold 0.5: near (≈0.0) and somewhat_near (≈0.134) are included, far (1.0) is not
        $results = $this->makeModel()::query()
            ->whereVectorSimilarTo('embedding', [1.0, 0.0, 0.0], 0.5)
            ->pluck('name')
            ->all();

        $this->assertContains('near', $results, "'near' should be within threshold 0.5");
        $this->assertContains('somewhat_near', $results, "'somewhat_near' should be within threshold 0.5");
        $this->assertNotContains('far', $results, "'far' should be excluded by threshold 0.5");
    }

    public function test_where_vector_similar_to_with_tight_threshold_excludes_somewhat_near(): void
    {
        // Threshold 0.1: only near (distance ≈ 0) qualifies; somewhat_near (≈0.134) is excluded
        $results = $this->makeModel()::query()
            ->whereVectorSimilarTo('embedding', [1.0, 0.0, 0.0], 0.1)
            ->pluck('name')
            ->all();

        $this->assertContains('near', $results, "'near' should be within threshold 0.1");
        $this->assertNotContains('somewhat_near', $results, "'somewhat_near' should be excluded by threshold 0.1");
        $this->assertNotContains('far', $results, "'far' should be excluded by threshold 0.1");
    }

    // ---------------------------------------------------------------
    // AC: orderByVectorDistance returns rows in ascending distance order
    // ---------------------------------------------------------------

    public function test_order_by_vector_distance_returns_rows_ascending(): void
    {
        $names = $this->makeModel()::query()
            ->orderByVectorDistance('embedding', [1.0, 0.0, 0.0])
            ->pluck('name')
            ->all();

        $this->assertCount(3, $names);
        $this->assertSame('near', $names[0], "'near' should be closest (first)");
        $this->assertSame('somewhat_near', $names[1], "'somewhat_near' should be second");
        $this->assertSame('far', $names[2], "'far' should be most distant (last)");
    }

    // ---------------------------------------------------------------
    // AC: selectVectorDistance returns a numeric distance column with correct alias
    // ---------------------------------------------------------------

    public function test_select_vector_distance_returns_numeric_column_with_alias(): void
    {
        $row = $this->makeModel()::query()
            ->select('name')
            ->selectVectorDistance('embedding', [1.0, 0.0, 0.0], 'score')
            ->where('name', 'near')
            ->first();

        $this->assertNotNull($row, 'Query should return a row');
        $this->assertArrayHasKey('score', $row->getAttributes(), 'Row should have a "score" column');
        $this->assertIsNumeric($row->score, '"score" should be numeric');
        $this->assertEqualsWithDelta(0.0, (float) $row->score, 0.001, "Distance from 'near' to itself should be ~0");
    }

    public function test_select_vector_distance_default_alias_is_distance(): void
    {
        $row = $this->makeModel()::query()
            ->select('name')
            ->selectVectorDistance('embedding', [1.0, 0.0, 0.0])
            ->where('name', 'far')
            ->first();

        $this->assertNotNull($row);
        $this->assertArrayHasKey('distance', $row->getAttributes(), 'Default alias should be "distance"');
        $this->assertEqualsWithDelta(1.0, (float) $row->distance, 0.01, "Orthogonal vector cosine distance should be ~1.0");
    }

    // ---------------------------------------------------------------
    // AC: EUCLIDEAN metric produces a numeric distance
    // ---------------------------------------------------------------

    public function test_euclidean_metric_produces_numeric_distance(): void
    {
        $row = $this->makeModel()::query()
            ->select('name')
            ->selectVectorDistance('embedding', [1.0, 0.0, 0.0], 'dist', 'EUCLIDEAN')
            ->where('name', 'near')
            ->first();

        $this->assertNotNull($row);
        $this->assertIsNumeric($row->dist, 'EUCLIDEAN distance should be numeric');
        $this->assertEqualsWithDelta(0.0, (float) $row->dist, 0.001, "EUCLIDEAN distance from 'near' to itself should be ~0");
    }

    // ---------------------------------------------------------------
    // AC: whereVectorSimilarTo with EUCLIDEAN metric filters correctly
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_with_euclidean_filters_by_distance(): void
    {
        // EUCLIDEAN distances from [1, 0, 0]:
        //   near          [1, 0, 0] → 0.0
        //   somewhat_near [0.866, 0.5, 0] → sqrt((0.134)^2 + (0.5)^2) ≈ 0.518
        //   far           [0, 0, 1] → sqrt(1 + 1) ≈ 1.414
        // Threshold 1.0: near and somewhat_near pass; far is excluded.
        $results = $this->makeModel()::query()
            ->whereVectorSimilarTo('embedding', [1.0, 0.0, 0.0], 1.0, 'EUCLIDEAN')
            ->pluck('name')
            ->all();

        $this->assertContains('near', $results, "'near' should be within EUCLIDEAN threshold 1.0");
        $this->assertContains('somewhat_near', $results, "'somewhat_near' should be within EUCLIDEAN threshold 1.0");
        $this->assertNotContains('far', $results, "'far' should be excluded by EUCLIDEAN threshold 1.0");
    }

    // ---------------------------------------------------------------
    // AC: selectVectorDistance + orderByVectorDistance chained (realistic usage)
    // ---------------------------------------------------------------

    public function test_select_distance_and_order_by_distance_chained_returns_ordered_results(): void
    {
        $results = $this->makeModel()::query()
            ->select('name')
            ->selectVectorDistance('embedding', [1.0, 0.0, 0.0])
            ->orderByVectorDistance('embedding', [1.0, 0.0, 0.0])
            ->get();

        $this->assertCount(3, $results);
        $this->assertSame('near', $results[0]->name, "First result should be 'near'");
        $this->assertSame('somewhat_near', $results[1]->name, "Second result should be 'somewhat_near'");
        $this->assertSame('far', $results[2]->name, "Third result should be 'far'");

        foreach ($results as $row) {
            $this->assertArrayHasKey('distance', $row->getAttributes(), 'Each row should carry a distance column');
            $this->assertIsNumeric($row->distance);
        }

        $this->assertEqualsWithDelta(0.0, (float) $results[0]->distance, 0.001, "'near' distance should be ≈ 0");
        $this->assertEqualsWithDelta(1.0, (float) $results[2]->distance, 0.01, "'far' distance should be ≈ 1.0");
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function makeModel(): Model
    {
        return new class extends Model {
            protected $table = 'integration_macro_items';

            public $timestamps = false;
        };
    }
}
