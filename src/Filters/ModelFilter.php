<?php

namespace HumamK98\LaravelFilters\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Example concrete filter class
 * This serves as a reference implementation that you can copy and customize for specific models
 */
class ModelFilter extends Filter
{
    /**
     * The model class associated with this filter.
     *
     * @var string
     */
    protected $modelClass;

    /**
     * Set the model class for this filter.
     *
     * @param string $modelClass
     * @return $this
     */
    public function forModel(string $modelClass)
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Get allowed filters based on model's filterable columns.
     *
     * @return array
     */
    protected function getAllowedFilters(): array
    {
        if (empty($this->modelClass) || !class_exists($this->modelClass)) {
            return [];
        }
        
        if (method_exists($this->modelClass, 'getFilterableColumns')) {
            // Get model's filterable columns if the model uses the Filterable trait
            $baseFilters = $this->modelClass::getFilterableColumns();
        } else {
            // Fallback to empty array if model doesn't use the trait
            $baseFilters = [];
        }
        
        // Add common filter types
        $filterTypes = [];
        foreach ($baseFilters as $column) {
            // Exact match filter
            $filterTypes[] = $column;
            
            // Range filters
            $filterTypes[] = "{$column}_min";
            $filterTypes[] = "{$column}_max";
            
            // Search filters (for string columns)
            $filterTypes[] = "{$column}_like";
        }
        
        // Add sort parameter
        $filterTypes[] = 'sort_by';
        $filterTypes[] = 'sort_direction';
        
        return $filterTypes;
    }

    /**
     * Dynamic filter method for exact matching.
     * 
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        // If no arguments provided, return builder
        if (empty($args)) {
            return $this->builder;
        }
        
        $value = $args[0];
        if (is_null($value) || $value === '') {
            return $this->builder;
        }
        
        $snakeMethod = Str::snake($method);
        
        // Check if it's a like filter
        if (Str::endsWith($snakeMethod, '_like')) {
            $column = Str::before($snakeMethod, '_like');
            if ($this->isFilterableColumn($column)) {
                return $this->builder->where($column, 'like', "%{$value}%");
            }
        }
        
        // Check if it's a min filter
        if (Str::endsWith($snakeMethod, '_min')) {
            $column = Str::before($snakeMethod, '_min');
            if ($this->isFilterableColumn($column)) {
                return $this->builder->where($column, '>=', $value);
            }
        }
        
        // Check if it's a max filter
        if (Str::endsWith($snakeMethod, '_max')) {
            $column = Str::before($snakeMethod, '_max');
            if ($this->isFilterableColumn($column)) {
                return $this->builder->where($column, '<=', $value);
            }
        }
        
        // Check if direct column match
        if ($this->isFilterableColumn($snakeMethod)) {
            return $this->builder->where($snakeMethod, $value);
        }
        
        return $this->builder;
    }
    
    /**
     * Check if a column is filterable in the model.
     *
     * @param string $column
     * @return bool
     */
    protected function isFilterableColumn(string $column): bool
    {
        if (!empty($this->modelClass) && method_exists($this->modelClass, 'getFilterableColumns')) {
            return in_array($column, $this->modelClass::getFilterableColumns());
        }
        return false;
    }
    
    /**
     * Handle sorting.
     * 
     * @param string $column
     * @return Builder
     */
    public function sortBy($column)
    {
        $direction = $this->request->input('sort_direction', 'asc');
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'asc';
        
        if ($this->isFilterableColumn($column)) {
            return $this->builder->orderBy($column, $direction);
        }
        
        return $this->builder;
    }
}