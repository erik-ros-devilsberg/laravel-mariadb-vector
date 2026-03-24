<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

class VectorServiceProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Service provider registration
    // ---------------------------------------------------------------

    public function test_service_provider_is_registered_in_the_application(): void
    {
        $this->assertArrayHasKey(
            \Devilsberg\LaravelMariadbVector\VectorServiceProvider::class,
            $this->app->getLoadedProviders()
        );
    }

    // ---------------------------------------------------------------
    // Config
    // ---------------------------------------------------------------

    public function test_config_file_is_registered(): void
    {
        $config = $this->app['config']->get('vector');

        $this->assertNotNull($config);
        $this->assertIsArray($config);
    }

    public function test_config_has_default_dimensions(): void
    {
        $this->assertSame(768, config('vector.default_dimensions'));
    }

    public function test_config_has_distance_metric(): void
    {
        $this->assertSame('COSINE', config('vector.distance_metric'));
    }

    public function test_config_has_all_required_keys(): void
    {
        foreach (['default_dimensions', 'distance_metric'] as $key) {
            $this->assertTrue(
                $this->app['config']->has("vector.{$key}"),
                "Missing config key: vector.{$key}"
            );
        }
    }

    // ---------------------------------------------------------------
    // Config publishing
    // ---------------------------------------------------------------

    public function test_config_is_publishable_with_mariadb_vector_config_tag(): void
    {
        $this->assertContains(
            'mariadb-vector-config',
            \Illuminate\Support\ServiceProvider::publishableGroups()
        );
    }

    public function test_published_config_maps_to_correct_destination(): void
    {
        $publishable = \Illuminate\Support\ServiceProvider::pathsToPublish(
            \Devilsberg\LaravelMariadbVector\VectorServiceProvider::class,
            'mariadb-vector-config'
        );

        $this->assertNotEmpty($publishable);
        $this->assertStringEndsWith('config/vector.php', array_values($publishable)[0]);
    }

    // ---------------------------------------------------------------
    // resolveDistanceFunction()
    // ---------------------------------------------------------------

    public function test_resolve_distance_function_returns_cosine_sql_function(): void
    {
        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);

        $this->assertSame('VEC_DISTANCE_COSINE', $provider->resolveDistanceFunction('COSINE'));
    }

    public function test_resolve_distance_function_returns_euclidean_sql_function(): void
    {
        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);

        $this->assertSame('VEC_DISTANCE_EUCLIDEAN', $provider->resolveDistanceFunction('EUCLIDEAN'));
    }

    public function test_resolve_distance_function_is_case_insensitive(): void
    {
        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);

        $this->assertSame('VEC_DISTANCE_COSINE', $provider->resolveDistanceFunction('cosine'));
        $this->assertSame('VEC_DISTANCE_EUCLIDEAN', $provider->resolveDistanceFunction('euclidean'));
    }

    public function test_resolve_distance_function_throws_for_dot(): void
    {
        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOT product distance is not supported by MariaDB');

        $provider->resolveDistanceFunction('DOT');
    }

    public function test_resolve_distance_function_throws_for_unknown_metric(): void
    {
        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported distance metric');

        $provider->resolveDistanceFunction('MANHATTAN');
    }
}
