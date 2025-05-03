<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filter Settings
    |--------------------------------------------------------------------------
    |
    | This option controls the default settings for all filters.
    |
    */
    
    // Default operator for "like" searches
    'default_like_operator' => 'like', // Options: 'like', 'ilike' (for PostgreSQL)
    
    // Whether to enable automatic query parameter aliasing (e.g., 'created_after' maps to 'created_at_min')
    'enable_param_aliasing' => true,
    
    // Allow all request parameters by default (be careful with this)
    'allow_all_params_by_default' => false,
    
    // Default cache duration in minutes (set to 0 to disable caching)
    'cache_duration' => 0,
    
    // Default sort column when not specified in request
    'default_sort_column' => 'created_at',
    
    // Default sort direction when not specified in request
    'default_sort_direction' => 'desc',
    
    /*
    |--------------------------------------------------------------------------
    | Framework Compatibility Settings
    |--------------------------------------------------------------------------
    |
    | These options help ensure compatibility across different Laravel versions.
    |
    */
    
    // Whether to use legacy string encoding for Laravel < 9 compatibility
    'use_legacy_encoding' => false,
    
    // Enable new features available in Laravel 11+
    'enable_laravel11_features' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Common Filter Mappings
    |--------------------------------------------------------------------------
    |
    | Common search parameter mappings that can be used by any filter.
    |
    */
    'param_mappings' => [
        'created_after' => 'created_at_min',
        'created_before' => 'created_at_max',
        'updated_after' => 'updated_at_min',
        'updated_before' => 'updated_at_max',
        'search' => 'keyword',
        'query' => 'keyword',
        'order_by' => 'sort_by',
        'order_direction' => 'sort_direction',
    ],
];