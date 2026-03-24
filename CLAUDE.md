# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**devilsberg/laravel-mariadb-vector** -- A Spatie-style Laravel package that provides MariaDB 11.7+ native vector storage and search for Eloquent models. The MariaDB equivalent of pgvector-laravel.

### What This Package Does

- Provides `$table->vector('embedding', 768)` via Laravel 12's native Blueprint support for MariaDB VECTOR columns
- Provides `VectorCast` for Eloquent models to cast VECTOR columns between MariaDB binary format and PHP float arrays
- Provides query builder macros for vector search: `whereVectorSimilarTo`, `orderByVectorDistance`, `selectVectorDistance`
- Supports COSINE and EUCLIDEAN distance metrics via MariaDB's native `VEC_DISTANCE_*` functions

### Design Principles

- **MariaDB-first** -- MariaDB 11.7+ native VECTOR type, not JSON workarounds
- **BYOE (Bring Your Own Embeddings)** -- accepts float arrays; users generate embeddings however they want
- **Spatie-quality** -- well-documented, well-tested, follows Laravel conventions
- **Focused** -- vector storage and search only, not an embedding or AI toolkit

### Tech Stack

- **Type**: Laravel package (not an application)
- **Language**: PHP ^8.4
- **Framework**: Laravel ^12.0
- **Database**: MariaDB 11.7+ (native VECTOR type)
- **Testing**: Orchestra Testbench + PHPUnit
- **CI**: GitHub Actions with MariaDB 11.7 Docker image

### Package Structure

```
src/
  VectorServiceProvider.php
  Casts/
    VectorCast.php
config/
  vector.php
tests/
  TestCase.php
  Casts/
  Integration/
docs/
  backlog/
  sprint/
  done/
```

### Key Technical Context

- MariaDB 11.7+ VECTOR syntax: `column_name VECTOR(dimensions)`
- MariaDB 11.7+ distance function: `VEC_DISTANCE_COSINE(col, VEC_FromText(?))`
- MariaDB 11.7+ vector conversion functions: `VEC_FromText('[0.1, 0.2, ...]')` (text to vector), `VEC_ToText(col)` (vector to text)
- This is a PACKAGE, not an application. It has no models, controllers, or routes of its own.
- It provides tools (casts, macros) for other Laravel applications to use.
- Embedding generation is deliberately out of scope -- users bring their own float arrays.
- Testing uses Orchestra Testbench (standard for Laravel package testing).

### Backlog and Sprint Documents

- **Backlog**: `docs/backlog/` -- epics and user stories shaped by the product-manager agent
- **Sprint**: `docs/sprint/` -- decomposed tasks for the current sprint
- **Done**: `docs/done/` -- completed sprint artifacts
- **Retro**: `docs/retro/` -- sprint retrospectives and lessons learned
