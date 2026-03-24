<?php

namespace Devilsberg\LaravelMariadbVector\Tests;

class ConfigMariaDBTest extends TestCase
{
    // ---------------------------------------------------------------
    // DOT product is rejected at boot time
    // ---------------------------------------------------------------

    public function test_dot_distance_metric_throws_on_boot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOT product distance is not supported by MariaDB');

        $this->app['config']->set('vector.distance_metric', 'DOT');

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    public function test_dot_metric_lowercase_throws_on_boot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOT product distance is not supported by MariaDB');

        $this->app['config']->set('vector.distance_metric', 'dot');

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    public function test_cosine_metric_boots_cleanly(): void
    {
        $this->app['config']->set('vector.distance_metric', 'COSINE');

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertSame('COSINE', config('vector.distance_metric'));
    }

    public function test_euclidean_metric_boots_cleanly(): void
    {
        $this->app['config']->set('vector.distance_metric', 'EUCLIDEAN');

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertSame('EUCLIDEAN', config('vector.distance_metric'));
    }

    // ---------------------------------------------------------------
    // Driver guard — accepted and rejected drivers
    // ---------------------------------------------------------------

    public function test_service_provider_boots_with_mariadb_driver(): void
    {
        $this->app['config']->set('database.default', 'mariadb_test');
        $this->app['config']->set('database.connections.mariadb_test', [
            'driver'   => 'mariadb',
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ]);

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_service_provider_boots_with_mysql_driver(): void
    {
        $this->app['config']->set('database.default', 'mysql_test');
        $this->app['config']->set('database.connections.mysql_test', [
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ]);

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_service_provider_rejects_unsupported_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('laravel-mariadb-vector requires a MySQL or MariaDB database driver');

        $this->app['config']->set('database.default', 'sqlite_test');
        $this->app['config']->set('database.connections.sqlite_test', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    public function test_service_provider_rejects_null_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('laravel-mariadb-vector requires a MySQL or MariaDB database driver');

        $this->app['config']->set('database.default', 'null_driver_conn');
        $this->app['config']->set('database.connections.null_driver_conn', [
            'driver'   => null,
            'host'     => '127.0.0.1',
            'database' => 'test',
        ]);

        $provider = new \Devilsberg\LaravelMariadbVector\VectorServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }
}
