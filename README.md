# Laravel MariaDB Vector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devilsberg/laravel-mariadb-vector.svg?style=flat-square)](https://packagist.org/packages/devilsberg/laravel-mariadb-vector)
[![Tests](https://img.shields.io/github/actions/workflow/status/erik-ros-devilsberg/laravel-mariadb-vector/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/erik-ros-devilsberg/laravel-mariadb-vector/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/devilsberg/laravel-mariadb-vector.svg?style=flat-square)](https://packagist.org/packages/devilsberg/laravel-mariadb-vector)
[![Ko-fi](https://img.shields.io/badge/support-ko--fi-FF5E5B?style=flat-square&logo=ko-fi&logoColor=white)](https://ko-fi.com/devilsberg)

MariaDB 11.7+ native vector storage and search for Laravel.

This package brings vector column support, binary-safe casting, and similarity search macros to Eloquent — powered by MariaDB's native `VECTOR` type and `VEC_DISTANCE_*` functions. Think of it as the MariaDB equivalent of pgvector for Laravel.

## Requirements

- PHP 8.4+
- Laravel 12+
- MariaDB 11.7+

## Installation

```bash
composer require devilsberg/laravel-mariadb-vector
```

Publish the config file:

```bash
php artisan vendor:publish --tag=mariadb-vector-config
```

## Quick Start

### 1. Create a migration

```php
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->vector('embedding', 768);
    $table->vectorIndex('embedding'); // enables fast ANN search
    $table->timestamps();
});
```

### 2. Set up your model

```php
use Devilsberg\LaravelMariadbVector\Casts\VectorCast;

class Article extends Model
{
    protected function casts(): array
    {
        return [
            'embedding' => VectorCast::class,
        ];
    }
}
```

### 3. Store a vector

```php
$embedding = $yourEmbeddingProvider->embed($article->body); // your code

$article->embedding = $embedding; // float array, e.g. [0.1, 0.2, ...]
$article->save();
```

### 4. Search by similarity

```php
use Devilsberg\LaravelMariadbVector\Distance;

$queryVector = $yourEmbeddingProvider->embed('climate change effects');

$results = Article::query()
    ->nearestNeighbors('embedding', $queryVector, Distance::Cosine)
    ->limit(10)
    ->get();

// Each result has a `score` column — higher is more similar (0–1)
foreach ($results as $article) {
    echo $article->title . ' — ' . $article->score;
}
```

## Vector Indexes

Without a vector index every similarity search performs a full table scan — fine for thousands of rows, slow at scale. MariaDB 11.7 supports `VECTOR INDEX` for approximate nearest neighbor (ANN) lookups, exposed via the standard `vectorIndex()` Blueprint method that Laravel 12 provides.

### Adding an index in a migration

```php
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->vector('embedding', 768);
    $table->vectorIndex('embedding'); // ALTER TABLE ... ADD VECTOR INDEX runs after CREATE TABLE
    $table->timestamps();
});
```

### Custom index name

By default the index name follows Laravel's standard index naming — `{table}_{column}_vectorindex`:

```php
$table->vectorIndex('embedding');                       // → articles_embedding_vectorindex
$table->vectorIndex('embedding', 'articles_vec_idx');   // → articles_vec_idx (custom)
```

### Adding an index to an existing table

```php
Schema::table('articles', function (Blueprint $table) {
    $table->vectorIndex('embedding');
});
```

> **Note:** MariaDB requires the vector column to be `NOT NULL` to add a vector index. Attempting to index a nullable vector column will raise a database error.
>
> **Note:** MariaDB 11.7 supports only **one** `VECTOR INDEX` per table. Attempting to add a second one will raise a database error.

## Bring Your Own Embeddings

This package handles **storage and search** only. You generate embeddings however you want — Ollama, Mistral, Cohere, a local Python model, or anything that produces a float array.

```php
// Ollama (local)
$response = Http::post('http://localhost:11434/api/embed', [
    'model' => 'nomic-embed-text',
    'input' => $text,
]);
$embedding = $response->json('embeddings.0');

// Mistral
$response = Http::withToken(config('services.mistral.key'))
    ->post('https://api.mistral.ai/v1/embeddings', [
        'model' => 'mistral-embed',
        'input' => [$text],
    ]);
$embedding = $response->json('data.0.embedding');

// Any source — just pass a float array
$article->embedding = $embedding;
$article->save();
```

## VectorCast

`VectorCast` handles conversion between MariaDB's binary VECTOR format and PHP float arrays.

```php
protected function casts(): array
{
    return [
        'embedding' => \Devilsberg\LaravelMariadbVector\Casts\VectorCast::class,
    ];
}
```

- **Reading:** Decodes MariaDB's binary packed floats (32-bit IEEE 754) or JSON string format to `array<float>`
- **Writing:** Converts `array<float>` to a `VEC_FromText('[0.1, 0.2, ...]')` expression
- **Null-safe:** Returns `null` for null values in both directions
- **Empty-safe:** Returns `null` for empty arrays

The column supports `->nullable()` in migrations:

```php
$table->vector('embedding', 768)->nullable();
```

## Query Builder Macros

All macros are registered on `Illuminate\Database\Eloquent\Builder`.

### whereVectorSimilarTo

Filter results by vector distance threshold.

```php
whereVectorSimilarTo(
    string $column,
    array $input,
    float $threshold,       // required — what counts as "similar" is application-specific
    ?string $metric = null  // defaults to config('vector.distance_metric')
)
```

```php
Article::query()->whereVectorSimilarTo('embedding', $queryVector, 0.5)->get();

// Euclidean distance
Article::query()->whereVectorSimilarTo('embedding', $queryVector, 0.5, metric: 'EUCLIDEAN')->get();
```

Generates: `WHERE VEC_DISTANCE_COSINE(\`embedding\`, VEC_FromText(?)) < ?`

### orderByVectorDistance

Sort results by vector distance (nearest first).

```php
orderByVectorDistance(
    string $column,
    array $input,
    ?string $metric = null
)
```

```php
Article::query()->orderByVectorDistance('embedding', $queryVector)->get();
```

Generates: `ORDER BY VEC_DISTANCE_COSINE(\`embedding\`, VEC_FromText(?)) asc`

### selectVectorDistance

Include the distance score in query results.

```php
selectVectorDistance(
    string $column,
    array $input,
    string $as = 'distance',
    ?string $metric = null
)
```

```php
Article::query()
    ->select('*')
    ->selectVectorDistance('embedding', $queryVector, as: 'score')
    ->orderByVectorDistance('embedding', $queryVector)
    ->get()
    ->each(fn ($article) => dump($article->title, $article->score));
```

Generates: `VEC_DISTANCE_COSINE(\`embedding\`, VEC_FromText(?)) as \`score\``

### nearestNeighbors

The recommended macro for most use cases. Computes the distance **once** per row as a normalized `score` column and orders results by highest score first. More efficient than chaining the three individual macros.

```php
nearestNeighbors(
    string $column,
    array $input,
    Distance $distance = Distance::Cosine
)
```

```php
use Devilsberg\LaravelMariadbVector\Distance;

Article::query()
    ->nearestNeighbors('embedding', $queryVector, Distance::Cosine)
    ->limit(10)
    ->get();

// With Euclidean distance
Article::query()
    ->nearestNeighbors('embedding', $queryVector, Distance::Euclidean)
    ->limit(5)
    ->get();
```

Generates:
```sql
SELECT *, 1.0 - (VEC_DISTANCE_COSINE(`embedding`, VEC_FromText(?))) as `score`
FROM `articles`
ORDER BY `score` desc
```

The `score` is a normalized similarity value — higher means more similar:
- **Cosine:** `1.0 - distance`, range approximately `[-1, 1]` (in practice `[0, 1]` for LLM embeddings)
- **Euclidean:** `1.0 - distance / SQRT(2)`, normalized to approximately `[0, 1]`

### Combining macros

For advanced queries where you need threshold filtering alongside ordering, use the individual macros:

```php
$results = Article::query()
    ->select('id', 'title')
    ->whereVectorSimilarTo('embedding', $queryVector, 0.4)
    ->selectVectorDistance('embedding', $queryVector, as: 'score')
    ->orderByVectorDistance('embedding', $queryVector)
    ->limit(10)
    ->get();
```

## Configuration

Published to `config/vector.php`:

```php
return [
    // Default vector dimensions (should match your embedding model output)
    'default_dimensions' => 768,

    // Distance metric: "COSINE" or "EUCLIDEAN"
    'distance_metric' => 'COSINE',
];
```

| Key | Default | Description |
|-----|---------|-------------|
| `default_dimensions` | `768` | Default dimensions for vector columns |
| `distance_metric` | `COSINE` | Distance function: `COSINE` or `EUCLIDEAN` |

> **Note:** `DOT` product distance is not supported by MariaDB. Configuring it will throw an `InvalidArgumentException`.

## Testing

Run the unit tests:

```bash
vendor/bin/phpunit --testsuite=Unit
```

Run the integration tests (requires a live MariaDB instance):

```bash
vendor/bin/phpunit --testsuite=Integration
```

Configure MariaDB credentials in `phpunit.xml`:

```xml
<php>
    <env name="DB_HOST" value="127.0.0.1"/>
    <env name="DB_PORT" value="3306"/>
    <env name="DB_DATABASE" value="your_test_db"/>
    <env name="DB_USERNAME" value="your_user"/>
    <env name="DB_PASSWORD" value="your_password"/>
</php>
```

Run the full suite:

```bash
vendor/bin/phpunit
```

## Support

If this package saves you time, consider buying me a coffee.

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/devilsberg)


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
