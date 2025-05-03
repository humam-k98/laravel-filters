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

        foreach ($this->getFilters() as $filter => $value) {
            $method = Str::camel($filter);
            
            // Handle both explicit methods and dynamic methods (__call)
            if ((method_exists($this, $method) || method_exists($this, '__call')) && !is_null($value)) {
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
        return $this->request->only($this->getAllowedFilters());
    }

    /**
     * Get the allowed filters.
     *
     * @return array
     */
    abstract protected function getAllowedFilters(): array;
}