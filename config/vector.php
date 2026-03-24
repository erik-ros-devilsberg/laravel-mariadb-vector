<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Dimensions
    |--------------------------------------------------------------------------
    |
    | The default number of dimensions for vector embeddings. This should
    | match the output dimensions of your chosen embedding model.
    |
    */

    'default_dimensions' => 768,

    /*
    |--------------------------------------------------------------------------
    | Distance Metric
    |--------------------------------------------------------------------------
    |
    | The distance metric used for vector similarity searches.
    | Supported: "COSINE", "EUCLIDEAN"
    |
    */

    'distance_metric' => 'COSINE',


];
