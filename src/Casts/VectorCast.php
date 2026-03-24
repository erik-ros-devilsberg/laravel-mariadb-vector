<?php

namespace Devilsberg\LaravelMariadbVector\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VectorCast implements CastsAttributes
{
    /**
     * Cast the given value from the database to a PHP float array.
     *
     * MariaDB returns VECTOR columns in two formats depending on context:
     *
     * - JSON string ("[0.1, 0.2, 0.3]"): returned by VEC_ToText() or when the
     *   value is read back as text. Detected by the leading "[" character.
     *
     * - Raw bytes: the native storage format returned when SELECTing a VECTOR
     *   column directly. Each float is 4 bytes; unpack('f*', ...) converts the
     *   byte string back into a PHP float array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<float>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        // JSON string format from VEC_ToText() — e.g. "[0.1, 0.2, 0.3]"
        if (is_string($value) && str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);

            if ($decoded === null) {
                throw new \InvalidArgumentException(
                    "Malformed JSON vector string for key '{$key}': {$value}"
                );
            }

            return array_map('floatval', $decoded);
        }

        // Binary format: native MariaDB VECTOR storage (32-bit IEEE 754 floats)
        if (is_string($value) && strlen($value) > 0) {
            $unpacked = unpack('f*', $value);

            return array_values($unpacked);
        }

        return null;
    }

    /**
     * Prepare the given value for storage as a MariaDB VECTOR expression.
     *
     * Converts a PHP float array to a DB::raw VEC_FromText(...) expression,
     * which MariaDB uses to convert a JSON-formatted vector string into its
     * native binary VECTOR type on write.
     *
     * Validation runs in two passes:
     *
     * 1. is_numeric() — rejects non-numeric values (e.g. "hello") that
     *    floatval() would silently coerce to 0.0, corrupting the vector.
     *
     * 2. is_finite() — rejects PHP NAN and INF constants, and numeric strings
     *    like "1e999" that pass is_numeric() but produce non-finite floats.
     *    These cannot be represented in MariaDB's VECTOR type and would cause
     *    an opaque SQL error without this guard.
     *
     * The interpolation of $vectorString into SQL is safe because all values
     * have passed is_numeric() and is_finite(), guaranteeing they are plain
     * decimal numbers with no special characters.
     *
     * @param  array<string, mixed>  $attributes
     * @return \Illuminate\Database\Query\Expression|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && count($value) === 0) {
            return null;
        }

        // Pass 1: reject non-numeric values before floatval() silently coerces them to 0.0
        foreach ($value as $v) {
            if (! is_numeric($v)) {
                throw new \InvalidArgumentException(
                    "Vector values must be numeric; non-numeric value encountered: " . print_r($v, true)
                );
            }
        }

        $floats = array_map('floatval', $value);

        // Pass 2: reject NAN and INF, which pass is_numeric() but are not valid VECTOR values
        foreach ($floats as $v) {
            if (! is_finite($v)) {
                throw new \InvalidArgumentException('Vector values must be finite floats; NAN and INF are not supported.');
            }
        }

        $vectorString = '[' . implode(', ', $floats) . ']';

        return DB::raw("VEC_FromText('$vectorString')");
    }
}
