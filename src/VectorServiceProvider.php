<?php

namespace Devilsberg\LaravelMariadbVector;

use Devilsberg\LaravelMariadbVector\Distance;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VectorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mariadb-vector')
            // Config file is named vector.php — passed explicitly because the package
            // name no longer matches the config filename after the laravel- prefix strip.
            ->hasConfigFile('vector');
    }

    public function packageBooted(): void
    {
        // Laravel 12 provides Blueprint::vector() natively — no macro needed.

        // Reject non-MariaDB/MySQL drivers early. All query macros rely on
        // MariaDB-specific functions (VEC_DISTANCE_COSINE, VEC_FromText) that
        // do not exist in PostgreSQL, SQLite, or SQL Server.
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new \InvalidArgumentException('laravel-mariadb-vector requires a MySQL or MariaDB database driver');
        }

        // Fail fast if DOT product is configured — MariaDB has no VEC_DISTANCE_DOT function.
        $metric = config('vector.distance_metric');

        if (is_string($metric) && strtoupper($metric) === 'DOT') {
            throw new \InvalidArgumentException('DOT product distance is not supported by MariaDB');
        }

        $this->registerQueryBuilderMacros();
    }

    /**
     * Resolve the MariaDB distance function name for a given metric string.
     *
     * Exposed as a public method so macros (which run in closure scope) can
     * call it via the $provider reference captured in registerQueryBuilderMacros().
     */
    public function resolveDistanceFunction(string $metric): string
    {
        return match (strtoupper($metric)) {
            'COSINE'    => 'VEC_DISTANCE_COSINE',
            'EUCLIDEAN' => 'VEC_DISTANCE_EUCLIDEAN',
            // DOT is caught at boot time when set in config, and here when passed per-call.
            'DOT'       => throw new \InvalidArgumentException('DOT product distance is not supported by MariaDB'),
            default     => throw new \InvalidArgumentException("Unsupported distance metric: {$metric}"),
        };
    }

    private function registerQueryBuilderMacros(): void
    {
        // Macros run as closures bound to the Builder instance ($this = Builder).
        // We capture $provider here so the closures can call resolveDistanceFunction()
        // without needing to re-resolve the service provider from the container.
        $provider = $this;

        // Filter rows where the vector distance is below a threshold.
        // Generates: WHERE VEC_DISTANCE_*(column, VEC_FromText(?)) < ?
        Builder::macro('whereVectorSimilarTo', function (string $column, array $input, float $threshold, ?string $metric = null) use ($provider) {
            $distanceFn = $provider->resolveDistanceFunction($metric ?? config('vector.distance_metric', 'COSINE'));
            $vectorString = json_encode($input);

            return $this->whereRaw(
                "{$distanceFn}(`{$column}`, VEC_FromText(?)) < ?",
                [$vectorString, $threshold]
            );
        });

        // Order rows by vector distance ascending (nearest first).
        // Generates: ORDER BY VEC_DISTANCE_*(column, VEC_FromText(?)) asc
        Builder::macro('orderByVectorDistance', function (string $column, array $input, ?string $metric = null) use ($provider) {
            $distanceFn = $provider->resolveDistanceFunction($metric ?? config('vector.distance_metric', 'COSINE'));
            $vectorString = json_encode($input);

            return $this->orderByRaw(
                "{$distanceFn}(`{$column}`, VEC_FromText(?)) asc",
                [$vectorString]
            );
        });

        // Include the distance score as a named column in the result set.
        // Generates: VEC_DISTANCE_*(column, VEC_FromText(?)) as `alias`
        Builder::macro('selectVectorDistance', function (string $column, array $input, string $as = 'distance', ?string $metric = null) use ($provider) {
            $distanceFn = $provider->resolveDistanceFunction($metric ?? config('vector.distance_metric', 'COSINE'));
            $vectorString = json_encode($input);

            return $this->selectRaw(
                "{$distanceFn}(`{$column}`, VEC_FromText(?)) as `{$as}`",
                [$vectorString]
            );
        });

        // Compute distance once as a normalized score alias, ordered highest score first.
        // Generates: SELECT *, <score_expr> as `score` FROM ... ORDER BY `score` desc
        Builder::macro('nearestNeighbors', function (string $column, array $input, Distance $distance = Distance::Cosine) {
            $fn = $distance->toSqlFunction();
            $vectorString = json_encode($input);
            $distanceSql = "{$fn}(`{$column}`, VEC_FromText(?))";
            $scoreSql = $distance->wrapAsScore($distanceSql);

            return $this
                ->selectRaw("*, {$scoreSql} as `score`", [$vectorString])
                ->orderByRaw('`score` desc');
        });
    }
}
