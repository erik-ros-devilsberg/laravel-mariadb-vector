---
story: Optional binary bind on write for faster bulk ingest
created: 2026-06-14
---

## Description

`VectorCast::set()` currently writes vectors via `VEC_FromText('[...]')`. MariaDB's
docs report that binding raw packed `float32` bytes is ~5× faster with a ~5× smaller
wire payload, and the stored bytes are byte-identical. The text path stays the sensible
default (self-documenting SQL, server-side validation, no endianness/PDO-binary
footguns), but bulk-ingest users inserting thousands of rows would benefit from an
opt-in binary fast path (`pack('g*', ...$floats)`).

Keep text as the default; add binary as an explicit opt-in (e.g. config flag or a
dedicated cast variant). The PHP-side `is_numeric`/`is_finite` guards already cover the
validation that `VEC_FromText` provides server-side.

**Risk to resolve during implementation:** returning a binary string from a cast's
`set()` flows through Laravel binding + PDO as a parameter; binary strings can trip up
PDO param handling on some setups. Needs a real round-trip test against a live MariaDB
before trusting it. Pin little-endian (`g`, which `pack` already enforces).

## Acceptance Criteria

- Text path (`VEC_FromText`) remains the default behaviour, unchanged
- An opt-in mechanism writes vectors as raw little-endian `float32` bytes
- Integration test proves a binary-written vector round-trips byte-identically to a
  `VEC_FromText`-written one (same stored bytes, same `VectorCast::get()` output)
- Integration test runs against a live MariaDB 11.7+ and confirms PDO binds the binary
  parameter correctly (no truncation, no encoding mangling)
- Documented in README with the speed/legibility trade-off so users choose consciously
