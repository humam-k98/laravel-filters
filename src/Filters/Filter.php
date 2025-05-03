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
            
            if (method_exists($this, $method) && !is_null($value)) {
                $this->$method($value);
            }
        }

        return $this->builder;
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