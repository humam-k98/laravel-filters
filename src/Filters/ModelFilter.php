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
        // Check if direct column match
        if (!empty($this->modelClass) && method_exists($this->modelClass, 'getFilterableColumns') && 
            in_array($method, $this->modelClass::getFilterableColumns())) {
            return $this->builder->where($method, $args[0]);
        }
        
        // Check if it's a min filter
        if (Str::endsWith($method, 'Min')) {
            $column = Str::snake(Str::before($method, 'Min'));
            return $this->handleMinFilter($column, $args[0]);
        }
        
        // Check if it's a max filter
        if (Str::endsWith($method, 'Max')) {
            $column = Str::snake(Str::before($method, 'Max'));
            return $this->handleMaxFilter($column, $args[0]);
        }
        
        // Check if it's a like filter
        if (Str::endsWith($method, 'Like')) {
            $column = Str::snake(Str::before($method, 'Like'));
            return $this->handleLikeFilter($column, $args[0]);
        }

        return $this->builder;
    }
    
    /**
     * Generic _min suffix handler for range filtering.
     * 
     * @param string $column
     * @param mixed $value
     * @return Builder
     */
    protected function handleMinFilter($column, $value)
    {
        return $this->builder->where($column, '>=', $value);
    }
    
    /**
     * Generic _max suffix handler for range filtering.
     * 
     * @param string $column
     * @param mixed $value
     * @return Builder
     */
    protected function handleMaxFilter($column, $value)
    {
        return $this->builder->where($column, '<=', $value);
    }
    
    /**
     * Generic _like suffix handler for partial matching.
     * 
     * @param string $column
     * @param mixed $value
     * @return Builder
     */
    protected function handleLikeFilter($column, $value)
    {
        return $this->builder->where($column, 'like', "%{$value}%");
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
        
        if (!empty($this->modelClass) && in_array($column, $this->modelClass::getFilterableColumns())) {
            return $this->builder->orderBy($column, $direction);
        }
        
        return $this->builder;
    }
}