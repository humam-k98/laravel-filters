<?php

namespace HumamK98\LaravelFilters\Filters;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use HumamK98\LaravelFilters\Generators\FilterGenerator;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;

class DynamicFilterResolver
{
    /**
     * The filter generator instance.
     *
     * @var FilterGenerator
     */
    protected $generator;
    
    /**
     * Initialize a new filter resolver.
     *
     * @param FilterGenerator $generator
     */
    public function __construct(FilterGenerator $generator)
    {
        $this->generator = $generator;
    }
    
    /**
     * Resolve or create a filter for the given model.
     *
     * @param string|Model $model Model class name or instance
     * @param array $parameters Additional parameters to pass to the filter constructor
     * @return Filter
     */
    public function resolveFilterForModel($model, array $parameters = []): Filter
    {
        // Get model class if an instance was provided
        $modelClass = $model instanceof Model ? get_class($model) : $model;
        
        // Try to resolve existing filter class first
        $filterClass = $this->guessFilterClass($modelClass);
        
        if (class_exists($filterClass)) {
            return $this->resolveFilterInstance($filterClass, $parameters);
        }
        
        // If filter class doesn't exist, generate one in memory
        $filter = $this->generateFilterInMemory($modelClass, $parameters);
        
        return $filter;
    }
    
    /**
     * Guess the filter class name for a model.
     *
     * @param string $modelClass
     * @return string
     */
    protected function guessFilterClass(string $modelClass): string
    {
        $modelName = class_basename($modelClass);
        
        $possibleNamespaces = [
            'App\\Filters',
            'App\\Models\\Filters',
            $this->getModelNamespace($modelClass) . '\\Filters',
        ];
        
        foreach ($possibleNamespaces as $namespace) {
            $filterClass = $namespace . '\\' . $modelName . 'Filter';
            if (class_exists($filterClass)) {
                return $filterClass;
            }
        }
        
        // Return the default expected path
        return 'App\\Filters\\' . $modelName . 'Filter';
    }
    
    /**
     * Get the namespace of the model.
     *
     * @param string $modelClass
     * @return string
     */
    protected function getModelNamespace(string $modelClass): string
    {
        $reflection = new ReflectionClass($modelClass);
        return $reflection->getNamespaceName();
    }
    
    /**
     * Resolve a filter instance from a class name.
     *
     * @param string $filterClass
     * @param array $parameters
     * @return Filter
     */
    protected function resolveFilterInstance(string $filterClass, array $parameters = []): Filter
    {
        return Container::getInstance()->make($filterClass, $parameters);
    }
    
    /**
     * Generate a filter class in memory and return an instance.
     *
     * @param string $modelClass
     * @param array $parameters
     * @return Filter
     */
    protected function generateFilterInMemory(string $modelClass, array $parameters = []): Filter
    {
        $cacheKey = 'filters_toolkit:dynamic_filter:' . md5($modelClass);
        
        // Check if we've already generated this filter
        if (Cache::has($cacheKey)) {
            $filterClass = Cache::get($cacheKey);
            
            // Verify the class still exists
            if (class_exists($filterClass)) {
                return $this->resolveFilterInstance($filterClass, $parameters);
            }
        }

        // Generate the full filter class name
        $modelName = class_basename($modelClass);
        $filterClass = 'HumamK98\\LaravelFilters\\DynamicFilters\\' . $modelName . 'Filter';
        
        // Create filter class content
        $filterableColumns = [];
        if (method_exists($modelClass, 'getFilterableColumns')) {
            $filterableColumns = $modelClass::getFilterableColumns();
        } else {
            // Try to get fillable from model
            $model = new $modelClass;
            $filterableColumns = $model->getFillable();
        }
        
        // Process the request parameters for proper filtering
        $request = $parameters['request'] ?? app(\Illuminate\Http\Request::class);
        $likeFilters = [];
        
        // Check for _like suffix parameters and add them to the request parameters
        foreach ($filterableColumns as $column) {
            $likeParam = $column . '_like';
            if ($request->has($likeParam)) {
                $likeFilters[$likeParam] = $request->input($likeParam);
            }
        }
        
        // Create an instance of ModelFilter and configure it for the target model
        $baseFilter = new ModelFilter($request);
        $baseFilter->forModel($modelClass);
        
        // Store the filter class in cache for next time
        Cache::put($cacheKey, get_class($baseFilter), now()->addHour());
        
        return $baseFilter;
    }
}