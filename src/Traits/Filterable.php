<?php

namespace HumamK98\LaravelFilters\Traits;

use Illuminate\Database\Eloquent\Builder;
use HumamK98\LaravelFilters\Filters\Filter;
use HumamK98\LaravelFilters\Filters\DynamicFilterResolver;
use Illuminate\Http\Request;

trait Filterable
{
    /**
     * Apply filter to the query.
     *
     * @param  Builder  $query
     * @param  Filter|null  $filter
     * @return Builder
     */
    public function scopeFilter(Builder $query, ?Filter $filter = null): Builder
    {
        // If a filter is explicitly provided, use it
        if ($filter !== null) {
            return $filter->apply($query);
        }
        
        // Otherwise, dynamically resolve or create a filter
        $request = app(Request::class);
        $resolver = app(DynamicFilterResolver::class);
        $dynamicFilter = $resolver->resolveFilterForModel(static::class, ['request' => $request]);
        
        return $dynamicFilter->apply($query);
    }
    
    /**
     * Get model filterable columns.
     *
     * @return array
     */
    public static function getFilterableColumns(): array
    {
        $model = new static;
        
        // Get default filterable columns from model property if exists
        if (property_exists($model, 'filterable') && is_array($model->filterable)) {
            return $model->filterable;
        }
        
        // Otherwise get all fillable fields
        return $model->getFillable();
    }
}