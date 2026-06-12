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

## Agile Workflow

This project uses the agile plugin. Follow these rules when building features.

### Flow

```
1. Human writes user stories to docs/user-stories/backlog/
2. /agile:shape <story-slug> [<story-slug2> ...]
        → product-manager reads stories and shapes a sprint plan → saved to docs/sprints/
        STOP: human reviews and approves plan
3. /agile:execute docs/sprints/<sprint-slug>.md
        → developer implements (TDD: tests first, then implement)
        STOP: human reviews the work
4. /agile:review (optional, ad-hoc)
        → reviewer reports findings inline
        → human fixes defects now or creates new user stories
5. /agile:wrap-sprint
        → documents sprint in docs/system.md
        → moves user stories to docs/user-stories/done/
        → deletes sprint plan
6. /agile:commit → commit and push
```

### Rules

- Never start building without an approved sprint plan in `docs/sprints/`
- Sprint plans are the single source of truth for the sprint — update them as execution progresses
- Developer writes tests first, then implements — never skip writing tests
- Tests live in a seperate directory structure in the project root /tests
- Review is user invoked — trigger it with `/agile:review`
- Defects found in review become new user stories
- Do not make changes outside project directory

### Directory structure

- `docs/user-stories/backlog/` — pending user stories (human-written)
- `docs/user-stories/done/` — completed user stories (moved here by `/agile:wrap-sprint`)
- `docs/sprints/` — active sprint plans (deleted after `/agile:wrap-sprint`)
- `docs/system.md` — cumulative decisions and outcomes

### User story format

File naming: `NN-story-name.md` — use a two-digit number prefix to control ordering (e.g. `01-user-authentication.md`, `02-password-reset.md`).

```markdown
---
story: <Story Name>
created: YYYY-MM-DD
---

## Description

<What needs to be built and why>

## Acceptance Criteria

- <criterion 1 — specific and testable>
- <criterion 2>
```

### Human gates

1. After `/agile:shape` — approve the sprint plan before executing
2. After `/agile:execute` — review the work and decide whether to run `/agile:review`
