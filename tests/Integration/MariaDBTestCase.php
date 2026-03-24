<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Integration;

use Devilsberg\LaravelMariadbVector\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class MariaDBTestCase extends TestCase
{
    /**
     * Tables created during the test that should be cleaned up.
     *
     * @var array<string>
     */
    protected array $createdTables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfMariaDBNotAvailable();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTables as $table) {
            Schema::dropIfExists($table);
        }

        $this->createdTables = [];

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'mariadb');
        $app['config']->set('database.connections.mariadb', [
            'driver' => 'mariadb',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'testing'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ]);
    }

    /**
     * Skip the test if a live MariaDB connection is not available.
     */
    protected function skipIfMariaDBNotAvailable(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->markTestSkipped('MariaDB is not available: ' . $e->getMessage());
        }
    }

    /**
     * Skip the test if the connected server is not MariaDB 11.7+.
     *
     * Parses VERSION() strings like "11.7.1-MariaDB" or "11.7.2-MariaDB-log".
     */
    protected function requireVectorSupport(): void
    {
        try {
            $version = DB::selectOne('SELECT VERSION() as version')->version;
        } catch (\Exception $e) {
            $this->markTestSkipped('Cannot determine database version: ' . $e->getMessage());

            return;
        }

        if (! str_contains((string) $version, 'MariaDB')) {
            $this->markTestSkipped('Vector support requires MariaDB; got: ' . $version);

            return;
        }

        preg_match('/^(\d+)\.(\d+)/', (string) $version, $matches);
        $major = (int) ($matches[1] ?? 0);
        $minor = (int) ($matches[2] ?? 0);

        if ($major < 11 || ($major === 11 && $minor < 7)) {
            $this->markTestSkipped("Vector support requires MariaDB 11.7+; got: {$version}");
        }
    }

    /**
     * Register a table name for cleanup in tearDown.
     */
    protected function trackTable(string $table): void
    {
        $this->createdTables[] = $table;
    }
}
