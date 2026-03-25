<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

use Devilsberg\LaravelMariadbVector\Distance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NearestNeighborsMacroTest extends TestCase
{
    protected function newTestModel(): Model
    {
        return new class extends Model {
            protected $table = 'test_items';
        };
    }

    // ---------------------------------------------------------------
    // Macro is registered on Eloquent Builder
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_macro_is_registered(): void
    {
        $this->assertTrue(Builder::hasGlobalMacro('nearestNeighbors'));
    }

    // ---------------------------------------------------------------
    // SQL generation — distance function selection
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_defaults_to_cosine(): void
    {
        $sql = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', [0.1, 0.2, 0.3])
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_COSINE', $sql);
    }

    public function test_nearest_neighbors_with_euclidean_uses_euclidean_function(): void
    {
        $sql = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', [0.1, 0.2, 0.3], Distance::Euclidean)
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_EUCLIDEAN', $sql);
    }

    // ---------------------------------------------------------------
    // SQL generation — SELECT and ORDER BY shape
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_selects_score_alias(): void
    {
        $sql = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', [0.1, 0.2, 0.3])
            ->toSql();

        $this->assertStringContainsString('as `score`', $sql);
    }

    public function test_nearest_neighbors_orders_by_score_desc(): void
    {
        $sql = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', [0.1, 0.2, 0.3])
            ->toSql();

        $this->assertMatchesRegularExpression('/order by `score` desc/i', $sql);
    }

    // ---------------------------------------------------------------
    // SQL generation — score expression content
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_cosine_score_expression_is_1_minus_distance(): void
    {
        $sql = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', [0.1, 0.2, 0.3], Distance::Cosine)
            ->toSql();

        // Cosine score: 1.0 - (VEC_DISTANCE_COSINE(...))
        $this->assertStringContainsString('1.0 - (', $sql);
    }

    public function test_nearest_neighbors_euclidean_score_expression_is_normalized(): void
    {
        $sql = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', [0.1, 0.2, 0.3], Distance::Euclidean)
            ->toSql();

        // Euclidean score: 1.0 - (...) / SQRT(2)
        $this->assertStringContainsString('/ SQRT(2)', $sql);
    }

    // ---------------------------------------------------------------
    // Bindings — the vector JSON string is correctly bound
    // ---------------------------------------------------------------

    public function test_nearest_neighbors_binds_vector_json_string(): void
    {
        $vector = [0.1, 0.2, 0.3];

        $bindings = $this->newTestModel()::query()
            ->nearestNeighbors('embedding', $vector)
            ->getBindings();

        $this->assertContains(json_encode($vector), $bindings);
    }
}
