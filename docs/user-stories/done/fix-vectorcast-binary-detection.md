# Story: Fix VectorCast binary/JSON misdetection (1-in-256 read crash)

**Product:** laravel-mariadb-vector
**Priority:** Critical (data-dependent crash in released package, v1.2)
**Status:** ready

## Context

`VectorCast::get()` (`src/Casts/VectorCast.php:38`) routes values to JSON parsing when `is_string($value) && str_starts_with($value, '[')`. MariaDB returns VECTOR columns as packed binary 32-bit floats — any vector whose first byte is `0x5B` (ASCII `[`, ~1/256 chance) is misrouted to `json_decode`, which returns null on the binary garbage, and the cast throws `InvalidArgumentException`.

Found 2026-06-12 while running the embedding experiment (`/home/erik/git/devilsberg-code/embedding-experiment/`): writes always succeeded (writes go through `VEC_FromText`), the crash only hits on *reading* an unlucky vector back through the cast. Production apps rarely read raw vectors via the cast, which is why 96 installs never reported it.

## User Story

**As** a developer reading a model attribute backed by a MariaDB VECTOR column
**I want** `VectorCast::get()` to decode every binary vector correctly regardless of its first byte
**So that** reads don't crash on ~0.4% of stored vectors

**Acceptance criteria:**
- [ ] A binary vector whose first byte is `0x5B` decodes to the correct float array
- [ ] Genuine JSON-string vectors (e.g. `VEC_ToText` output) still decode correctly
- [ ] No exception path reachable from valid MariaDB output of either format
- [ ] Regression test: pack a float array with `pack('g*', ...)` whose first byte is `0x5B` (e.g. craft the first float so its little-endian low byte is `0x5B`), run it through `get()`, assert the round-trip matches
- [ ] Regression test: valid JSON string input still round-trips
- [ ] Existing test suite passes (`tests/Casts/`, integration tests)
- [ ] Patch release tagged + CHANGELOG entry

**Technical notes:**
- Recommended detection order: binary length check first — a packed vector is always `dimensions × 4` bytes and valid JSON of that exact length starting with `[` is practically impossible; alternatively `json_validate()` (PHP ≥ 8.3, package requires 8.4) then fall back to `unpack('g*', ...)` instead of throwing.
- Keep the throw only for values that are neither valid JSON nor a plausible float-packed length — that's genuine corruption.
- The fix is read-path only; `set()` is unaffected.

## Related

- Found during: [[mariadb-blog-article-notes|MariaDB blog article — research notes]] (article angle: "the experiment found a 1-in-256 bug in my own package")
- Repo: `/home/erik/git/open-source-code/laravel-mariadb-vector`
