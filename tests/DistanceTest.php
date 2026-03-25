<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

use Devilsberg\LaravelMariadbVector\Distance;

class DistanceTest extends TestCase
{
    // ---------------------------------------------------------------
    // Enum case values
    // ---------------------------------------------------------------

    public function test_cosine_case_has_correct_string_value(): void
    {
        $this->assertSame('COSINE', Distance::Cosine->value);
    }

    public function test_euclidean_case_has_correct_string_value(): void
    {
        $this->assertSame('EUCLIDEAN', Distance::Euclidean->value);
    }

    // ---------------------------------------------------------------
    // toSqlFunction() returns the MariaDB VEC_DISTANCE_* function name
    // ---------------------------------------------------------------

    public function test_cosine_returns_correct_sql_function(): void
    {
        $this->assertSame('VEC_DISTANCE_COSINE', Distance::Cosine->toSqlFunction());
    }

    public function test_euclidean_returns_correct_sql_function(): void
    {
        $this->assertSame('VEC_DISTANCE_EUCLIDEAN', Distance::Euclidean->toSqlFunction());
    }

    // ---------------------------------------------------------------
    // wrapAsScore() wraps a distance SQL expression as a normalized score
    // ---------------------------------------------------------------

    public function test_cosine_wraps_distance_as_score(): void
    {
        // Cosine: 1.0 - distance gives similarity in [-1, 1]
        $this->assertSame('1.0 - (dist)', Distance::Cosine->wrapAsScore('dist'));
    }

    public function test_euclidean_wraps_distance_as_score_normalized(): void
    {
        // Euclidean: divide by SQRT(2) to normalize into approximately [0, 1]
        $this->assertSame('1.0 - (dist) / SQRT(2)', Distance::Euclidean->wrapAsScore('dist'));
    }
}
