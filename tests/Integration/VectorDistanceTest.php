<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VectorDistanceTest extends MariaDBTestCase
{
    private const TABLE = 'integration_vector_distance';

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackTable(self::TABLE);

        Schema::create(self::TABLE, function ($blueprint) {
            $blueprint->id();
            $blueprint->vector('embedding', 3);
        });

        // Insert two known vectors
        DB::table(self::TABLE)->insert([
            ['id' => 1, 'embedding' => DB::raw("VEC_FromText('[1.0, 0.0, 0.0]')")],
            ['id' => 2, 'embedding' => DB::raw("VEC_FromText('[0.0, 1.0, 0.0]')")],
        ]);
    }

    // ---------------------------------------------------------------
    // AC: Integration test runs VEC_DISTANCE_COSINE() between two
    //     stored vectors and verifies a numeric distance is returned
    // ---------------------------------------------------------------

    public function test_vec_distance_cosine_returns_numeric_distance(): void
    {
        $result = DB::selectOne(
            'SELECT VEC_DISTANCE_COSINE(
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 1),
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 2)
            ) as distance'
        );

        $this->assertNotNull($result, 'VEC_DISTANCE_COSINE query should return a result');
        $this->assertIsNumeric($result->distance, 'Cosine distance should be numeric');
        $this->assertGreaterThan(0, (float) $result->distance, 'Cosine distance between orthogonal vectors should be > 0');
    }

    public function test_vec_distance_cosine_of_identical_vectors_is_zero(): void
    {
        $result = DB::selectOne(
            'SELECT VEC_DISTANCE_COSINE(
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 1),
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 1)
            ) as distance'
        );

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.0, (float) $result->distance, 0.0001, 'Cosine distance of a vector with itself should be ~0');
    }

    // ---------------------------------------------------------------
    // AC: Integration test runs VEC_DISTANCE_EUCLIDEAN() between two
    //     stored vectors and verifies a numeric distance is returned
    // ---------------------------------------------------------------

    public function test_vec_distance_euclidean_returns_numeric_distance(): void
    {
        $result = DB::selectOne(
            'SELECT VEC_DISTANCE_EUCLIDEAN(
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 1),
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 2)
            ) as distance'
        );

        $this->assertNotNull($result, 'VEC_DISTANCE_EUCLIDEAN query should return a result');
        $this->assertIsNumeric($result->distance, 'Euclidean distance should be numeric');
        $this->assertGreaterThan(0, (float) $result->distance, 'Euclidean distance between different vectors should be > 0');
    }

    public function test_vec_distance_euclidean_of_identical_vectors_is_zero(): void
    {
        $result = DB::selectOne(
            'SELECT VEC_DISTANCE_EUCLIDEAN(
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 1),
                (SELECT embedding FROM ' . self::TABLE . ' WHERE id = 1)
            ) as distance'
        );

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.0, (float) $result->distance, 0.0001, 'Euclidean distance of a vector with itself should be ~0');
    }
}
