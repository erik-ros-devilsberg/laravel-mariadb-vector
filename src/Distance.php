<?php

namespace Devilsberg\LaravelMariadbVector;

enum Distance: string
{
    case Cosine    = 'COSINE';
    case Euclidean = 'EUCLIDEAN';

    /**
     * Return the MariaDB native distance function name for this metric.
     */
    public function toSqlFunction(): string
    {
        return match ($this) {
            self::Cosine    => 'VEC_DISTANCE_COSINE',
            self::Euclidean => 'VEC_DISTANCE_EUCLIDEAN',
        };
    }

    /**
     * Wrap a distance SQL expression as a normalized similarity score.
     *
     * Cosine distance ∈ [0, 2]; 1 - distance gives similarity ∈ [-1, 1].
     * Euclidean distance between unit vectors ∈ [0, √2]; divide by √2
     * to normalize to approximately [0, 1], then invert.
     */
    public function wrapAsScore(string $distanceSql): string
    {
        return match ($this) {
            self::Cosine    => "1.0 - ({$distanceSql})",
            self::Euclidean => "1.0 - ({$distanceSql}) / SQRT(2)",
        };
    }
}
