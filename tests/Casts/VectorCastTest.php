<?php

namespace Devilsberg\LaravelMariadbVector\Tests\Casts;

use Devilsberg\LaravelMariadbVector\Casts\VectorCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Devilsberg\LaravelMariadbVector\Tests\TestCase;

class VectorCastTest extends TestCase
{
    // ---------------------------------------------------------------
    // get() — reading from the database
    // ---------------------------------------------------------------

    public function test_get_converts_json_string_to_float_array(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        // VEC_ToText() returns vectors as a JSON-like string
        $result = $cast->get($model, 'embedding', '[0.1, 0.2, 0.3]', []);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEqualsWithDelta([0.1, 0.2, 0.3], $result, 0.0001);
    }

    public function test_get_returns_float_values(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $result = $cast->get($model, 'embedding', '[1.0, 2.0, 3.0]', []);

        foreach ($result as $value) {
            $this->assertIsFloat($value);
        }
    }

    public function test_get_handles_binary_vector_format(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        // MariaDB stores VECTOR columns as binary packed 32-bit IEEE 754 little-endian floats.
        // 'g*' = little-endian float (explicit byte order), matching what MariaDB actually sends.
        $binary = pack('g*', 0.1, 0.2, 0.3);

        $result = $cast->get($model, 'embedding', $binary, []);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEqualsWithDelta(0.1, $result[0], 0.0001);
        $this->assertEqualsWithDelta(0.2, $result[1], 0.0001);
        $this->assertEqualsWithDelta(0.3, $result[2], 0.0001);
    }

    public function test_get_decodes_binary_vector_whose_first_byte_is_an_opening_bracket(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        // Regression: ~1 in 256 binary vectors start with byte 0x5B (ASCII '['),
        // which the old leading-'[' check misrouted to json_decode, crashing the read.
        // Craft a float whose little-endian low byte is 0x5B by unpacking known bytes.
        $firstFloat = unpack('g', "\x5B\x00\x00\x3F")[1];

        $binary = pack('g*', $firstFloat, 0.2, 0.3);
        $this->assertSame("\x5B", $binary[0], 'Test setup: binary must start with 0x5B');

        $result = $cast->get($model, 'embedding', $binary, []);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEqualsWithDelta($firstFloat, $result[0], 0.000001);
        $this->assertEqualsWithDelta(0.2, $result[1], 0.0001);
        $this->assertEqualsWithDelta(0.3, $result[2], 0.0001);
    }

    public function test_get_decodes_four_byte_binary_that_is_also_valid_bare_json(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        // The bytes "12.5" are a valid 4-byte packed float AND valid bare JSON (a number).
        // Only strings starting with '[' may take the JSON path; this must unpack as binary.
        $binary = '12.5';
        $expected = unpack('g', $binary)[1];

        $result = $cast->get($model, 'embedding', $binary, []);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEqualsWithDelta($expected, $result[0], 0.000001);
    }

    public function test_get_throws_for_value_that_is_neither_json_nor_packed_length(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);

        // 9 bytes: not valid JSON, not a multiple of 4 — genuine corruption
        $cast->get($model, 'embedding', 'corrupt!!', []);
    }

    public function test_get_returns_null_for_null(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->assertNull($cast->get($model, 'embedding', null, []));
    }

    public function test_get_returns_null_for_empty_string(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        // Empty string: does not start with '[' and has zero length — falls through to null
        $this->assertNull($cast->get($model, 'embedding', '', []));
    }

    public function test_get_throws_for_malformed_json_string(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);

        $cast->get($model, 'embedding', '[not valid json', []);
    }

    public function test_get_throws_for_truncated_json(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);

        $cast->get($model, 'embedding', '[0.1, 0.2,', []);
    }

    // ---------------------------------------------------------------
    // set() — writing to the database
    // ---------------------------------------------------------------

    public function test_set_converts_float_array_to_vec_from_text_expression(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $result = $cast->set($model, 'embedding', [0.1, 0.2, 0.3], []);

        $this->assertInstanceOf(Expression::class, $result);

        $grammar = $this->app['db']->connection()->getQueryGrammar();
        $sql = $result->getValue($grammar);

        $this->assertStringContainsString('VEC_FromText', $sql);
        $this->assertStringNotContainsString('STRING_TO_VECTOR', $sql);
        $this->assertStringContainsString('0.1', $sql);
        $this->assertStringContainsString('0.2', $sql);
        $this->assertStringContainsString('0.3', $sql);
    }

    public function test_set_converts_integer_array_to_vec_expression(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        // Integers are valid input — floatval() converts them cleanly
        $result = $cast->set($model, 'embedding', [1, 2, 3], []);

        $this->assertInstanceOf(Expression::class, $result);

        $grammar = $this->app['db']->connection()->getQueryGrammar();
        $sql = $result->getValue($grammar);

        $this->assertStringContainsString('1', $sql);
        $this->assertStringContainsString('2', $sql);
        $this->assertStringContainsString('3', $sql);
    }

    public function test_set_returns_null_for_null(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->assertNull($cast->set($model, 'embedding', null, []));
    }

    public function test_set_returns_null_for_empty_array(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->assertNull($cast->set($model, 'embedding', [], []));
    }

    public function test_set_throws_for_non_numeric_string_values(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);

        // Without the is_numeric guard, floatval('a') would silently return 0.0
        $cast->set($model, 'embedding', ['a', 'b', 'c'], []);
    }

    public function test_set_throws_for_nan_value(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('finite');

        $cast->set($model, 'embedding', [NAN, 0.5, 0.5], []);
    }

    public function test_set_throws_for_inf_value(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('finite');

        $cast->set($model, 'embedding', [INF, 0.5, 0.5], []);
    }

    public function test_set_throws_for_negative_inf_value(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('finite');

        $cast->set($model, 'embedding', [-INF, 0.5, 0.5], []);
    }

    // ---------------------------------------------------------------
    // Round-trip
    // ---------------------------------------------------------------

    public function test_round_trip_set_then_get_returns_same_values(): void
    {
        $cast = new VectorCast();
        $model = $this->createMockModel();

        $original = [0.1, 0.2, 0.3, 0.4, 0.5];

        // set() produces a VEC_FromText expression; simulate what MariaDB
        // would return when reading the stored value back as a JSON string
        $cast->set($model, 'embedding', $original, []);
        $mariadbReturn = '[' . implode(', ', $original) . ']';

        $result = $cast->get($model, 'embedding', $mariadbReturn, []);

        $this->assertCount(count($original), $result);
        $this->assertEqualsWithDelta($original, $result, 0.0001);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createMockModel(): Model
    {
        return new class extends Model {
            protected $table = 'test_models';
        };
    }
}
