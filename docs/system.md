
# System Documentation

This file is maintained by `/agile:wrap-sprint`. Read this to understand the system without reading all the code.

## VectorCast read-path format detection

`VectorCast::get()` (`src/Casts/VectorCast.php`) decodes two formats MariaDB returns for VECTOR columns: JSON strings (from `VEC_ToText()`) and raw packed binary (32-bit IEEE 754 little-endian floats, `unpack('g*', ...)`).

**Decision (sprint: fix-vectorcast-binary-detection, 2026-06-12):** a leading `[` alone cannot identify JSON — ~1 in 256 binary vectors start with byte `0x5B` (ASCII `[`), and the old check misrouted them to `json_decode`, crashing reads (data-dependent bug shipped in v1.0.0–v1.2.0). Conversely, `json_validate()` alone is also insufficient: a 4-byte binary like `"12.5"` is valid bare JSON. The detection order is therefore:

1. JSON path only when `str_starts_with($value, '[')` **and** `json_validate($value)` both hold.
2. Binary path when `strlen($value) % 4 === 0` (the cast does not know the column's dimensions, so an exact `dimensions × 4` check is not possible).
3. `InvalidArgumentException` only for values matching neither — genuine corruption.

A packed binary that is also a complete valid JSON array is practically impossible (IEEE 754 bytes are not printable JSON). Regression tests pin the `0x5B` case, the bare-JSON 4-byte case, and the corruption throw (`tests/Casts/VectorCastTest.php`). The write path (`set()`) was unaffected. CHANGELOG.md was introduced in this sprint (Keep a Changelog format); the fix awaits a v1.2.1 patch tag.

