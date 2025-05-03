<?php

namespace HumamK98\LaravelFilters\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class Filter
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $builder;

    /**
     * Initialize a new filter instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply the filters on the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $builder)
    {
        $this->builder = $builder;
        
        // Add a bit of debug info
        \Log::debug('Applying filters', ['filters' => $this->getFilters()]);

        foreach ($this->getFilters() as $filter => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }
            
            $method = Str::camel($filter);
            
            // First try explicit method
            if (method_exists($this, $method)) {
                call_user_func([$this, $method], $value);
                continue;
            }
            
            // Then check for _like suffix
            if (Str::endsWith($filter, '_like')) {
                $column = Str::before($filter, '_like');
                $this->builder->where($column, 'like', "%{$value}%");
                continue;
            }
            
            // Check for _min suffix
            if (Str::endsWith($filter, '_min')) {
                $column = Str::before($filter, '_min');
                $this->builder->where($column, '>=', $value);
                continue;
            }
            
            // Check for _max suffix
            if (Str::endsWith($filter, '_max')) {
                $column = Str::before($filter, '_max');
                $this->builder->where($column, '<=', $value);
                continue;
            }
            
            // For direct column matches or other dynamic methods, 
            // delegate to __call if it exists
            if (method_exists($this, '__call')) {
                $this->$method($value);
            }
        }

        // Handle sorting if present
        if ($this->request->has('sort_by')) {
            $this->applySorting();
        }

        return $this->builder;
    }

    /**
     * Apply sorting to the builder.
     *
     * @return void
     */
    protected function applySorting()
    {
        $column = $this->request->input('sort_by');
        $direction = $this->request->input('sort_direction', 'asc');
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'asc';
        
        if (method_exists($this, 'sortBy')) {
            $this->sortBy($column);
        } else {
            $this->builder->orderBy($column, $direction);
        }
    }

    /**
     * Get all filters from the request.
     *
     * @return array
     */
    protected function getFilters(): array
    {
        $allowedFilters = $this->getAllowedFilters();
        $filters = [];
        
        // Process request inputs and match them to allowed filters
        foreach ($this->request->all() as $key => $value) {
            // Check for exact match
            if (in_array($key, $allowedFilters)) {
                $filters[$key] = $value;
                continue;
            }
            
            // Check for camelCase to snake_case conversion
            $snakeKey = Str::snake($key);
            if (in_array($snakeKey, $allowedFilters)) {
                $filters[$snakeKey] = $value;
                continue;
            }
            
            // Check for snake_case to camelCase conversion
            $camelKey = Str::camel($key);
            if (in_array($camelKey, $allowedFilters)) {
                $filters[$camelKey] = $value;
            }
        }
        
        // Filter out null values
        return array_filter($filters, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    /**
     * Get the allowed filters.
     *
     * @return array
     */
    abstract protected function getAllowedFilters(): array;
}