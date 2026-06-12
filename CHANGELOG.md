# Changelog

All notable changes to `devilsberg/laravel-mariadb-vector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2026-06-12

### Fixed

- `VectorCast::get()` misdetected binary vectors whose first byte is `0x5B` (ASCII `[`, ~1 in 256 vectors) as JSON strings, causing an `InvalidArgumentException` when reading them back from a VECTOR column. Format detection now validates the whole string as JSON before taking the JSON path and falls back to binary unpacking for any string whose length is a multiple of 4 bytes; only values matching neither format throw.

## [1.2.0] - 2026-03-27

### Added

- Vector index support in Blueprint for MariaDB native vector indexes.

## [1.1.0] - 2026-03-25

### Added

- `nearestNeighbors` Eloquent macro for nearest-neighbor queries.

### Changed

- Incorporated MariaDB feedback on the `nearestNeighbors` macro implementation.

### Fixed

- Badge URLs in the README.

## [1.0.0] - 2026-03-24

### Added

- Initial release: `VectorCast` for casting MariaDB VECTOR columns to PHP float arrays.
- Query builder macros: `whereVectorSimilarTo`, `orderByVectorDistance`, `selectVectorDistance`.
- COSINE and EUCLIDEAN distance metrics via MariaDB's native `VEC_DISTANCE_*` functions.

[Unreleased]: https://github.com/erik-ros-devilsberg/laravel-mariadb-vector/compare/v1.2.1...HEAD
[1.2.1]: https://github.com/erik-ros-devilsberg/laravel-mariadb-vector/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/erik-ros-devilsberg/laravel-mariadb-vector/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/erik-ros-devilsberg/laravel-mariadb-vector/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/erik-ros-devilsberg/laravel-mariadb-vector/releases/tag/v1.0.0
