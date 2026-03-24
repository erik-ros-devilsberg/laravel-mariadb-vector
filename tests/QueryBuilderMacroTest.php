<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QueryBuilderMacroTest extends TestCase
{
    protected function newTestModel(): Model
    {
        return new class extends Model {
            protected $table = 'test_items';
        };
    }

    // ---------------------------------------------------------------
    // Macros are registered on Eloquent Builder
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_macro_is_registered(): void
    {
        $this->assertTrue(Builder::hasGlobalMacro('whereVectorSimilarTo'));
    }

    public function test_order_by_vector_distance_macro_is_registered(): void
    {
        $this->assertTrue(Builder::hasGlobalMacro('orderByVectorDistance'));
    }

    public function test_select_vector_distance_macro_is_registered(): void
    {
        $this->assertTrue(Builder::hasGlobalMacro('selectVectorDistance'));
    }

    // ---------------------------------------------------------------
    // SQL generation
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_generates_correct_sql(): void
    {
        $query = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.4, 0.5, 0.6], 0.5);

        $sql = $query->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_COSINE(`embedding`, VEC_FromText(?))', $sql);
        $this->assertStringContainsString('< ?', $sql);
    }

    public function test_where_vector_similar_to_binds_vector_and_threshold(): void
    {
        $query = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.4, 0.5, 0.6], 0.5);

        $bindings = $query->getBindings();

        $this->assertCount(2, $bindings);
        $this->assertSame('[0.4,0.5,0.6]', $bindings[0]);
        $this->assertSame(0.5, $bindings[1]);
    }

    public function test_order_by_vector_distance_generates_correct_sql(): void
    {
        $sql = $this->newTestModel()::query()
            ->orderByVectorDistance('embedding', [0.1, 0.2, 0.3])
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_COSINE(`embedding`, VEC_FromText(?))', $sql);
        $this->assertMatchesRegularExpression('/order by.*VEC_DISTANCE_COSINE.*asc/i', $sql);
    }

    public function test_select_vector_distance_generates_correct_sql(): void
    {
        $sql = $this->newTestModel()::query()
            ->selectVectorDistance('embedding', [0.1, 0.2, 0.3], as: 'score')
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_COSINE(`embedding`, VEC_FromText(?))', $sql);
        $this->assertMatchesRegularExpression('/VEC_DISTANCE_COSINE.*as.*score/i', $sql);
    }

    // ---------------------------------------------------------------
    // Distance metric — config default and per-call override
    // ---------------------------------------------------------------

    public function test_macros_default_to_cosine_from_config(): void
    {
        $this->app['config']->set('vector.distance_metric', 'COSINE');

        $sql = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 0.5)
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_COSINE', $sql);
    }

    public function test_macros_use_euclidean_when_set_in_config(): void
    {
        $this->app['config']->set('vector.distance_metric', 'EUCLIDEAN');

        // Re-register macros so they pick up the updated config
        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $sql = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 0.5)
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_EUCLIDEAN', $sql);
    }

    public function test_where_vector_similar_to_metric_override(): void
    {
        $sql = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 0.5, metric: 'EUCLIDEAN')
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_EUCLIDEAN', $sql);
    }

    public function test_order_by_vector_distance_metric_override(): void
    {
        $sql = $this->newTestModel()::query()
            ->orderByVectorDistance('embedding', [0.1, 0.2, 0.3], metric: 'EUCLIDEAN')
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_EUCLIDEAN', $sql);
    }

    public function test_select_vector_distance_metric_override(): void
    {
        $sql = $this->newTestModel()::query()
            ->selectVectorDistance('embedding', [0.1, 0.2, 0.3], as: 'score', metric: 'EUCLIDEAN')
            ->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_EUCLIDEAN', $sql);
    }

    // ---------------------------------------------------------------
    // DOT product is not supported by MariaDB
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_throws_for_dot_metric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOT product distance is not supported by MariaDB');

        $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 0.5, metric: 'DOT');
    }

    public function test_order_by_vector_distance_throws_for_dot_metric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOT product distance is not supported by MariaDB');

        $this->newTestModel()::query()
            ->orderByVectorDistance('embedding', [0.1, 0.2, 0.3], metric: 'DOT');
    }

    public function test_select_vector_distance_throws_for_dot_metric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOT product distance is not supported by MariaDB');

        $this->newTestModel()::query()
            ->selectVectorDistance('embedding', [0.1, 0.2, 0.3], as: 'score', metric: 'DOT');
    }

    // ---------------------------------------------------------------
    // Unsupported metric names throw
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_throws_for_unsupported_metric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported distance metric');

        $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 0.5, metric: 'MANHATTAN');
    }

    public function test_order_by_vector_distance_throws_for_unsupported_metric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported distance metric');

        $this->newTestModel()::query()
            ->orderByVectorDistance('embedding', [0.1, 0.2, 0.3], metric: 'MANHATTAN');
    }

    public function test_select_vector_distance_throws_for_unsupported_metric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported distance metric');

        $this->newTestModel()::query()
            ->selectVectorDistance('embedding', [0.1, 0.2, 0.3], as: 'score', metric: 'MANHATTAN');
    }

    // ---------------------------------------------------------------
    // Threshold — the package passes values through unchanged.
    // MariaDB enforces semantic validity, not PHP.
    // ---------------------------------------------------------------

    public function test_where_vector_similar_to_passes_through_zero_threshold(): void
    {
        $bindings = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 0.0)
            ->getBindings();

        $this->assertSame(0.0, $bindings[1]);
    }

    public function test_where_vector_similar_to_passes_through_threshold_of_one(): void
    {
        $bindings = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 1.0)
            ->getBindings();

        $this->assertSame(1.0, $bindings[1]);
    }

    public function test_where_vector_similar_to_passes_through_negative_threshold(): void
    {
        $bindings = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], -0.5)
            ->getBindings();

        $this->assertSame(-0.5, $bindings[1]);
    }

    public function test_where_vector_similar_to_passes_through_threshold_greater_than_one(): void
    {
        $bindings = $this->newTestModel()::query()
            ->whereVectorSimilarTo('embedding', [0.1, 0.2, 0.3], 2.0)
            ->getBindings();

        $this->assertSame(2.0, $bindings[1]);
    }
}
