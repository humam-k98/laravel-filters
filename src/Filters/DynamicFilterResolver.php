<?php

namespace HumamK98\LaravelFilters\Filters;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use HumamK98\LaravelFilters\Generators\FilterGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
        
        \Log::debug('Resolving filter for model', ['model' => $modelClass]);
        
        // Try to resolve existing filter class first
        $filterClass = $this->guessFilterClass($modelClass);
        
        if (class_exists($filterClass)) {
            \Log::debug('Found existing filter class', ['filter' => $filterClass]);
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
        $modelNamespace = $this->getModelNamespace($modelClass);
        
        $possibleNamespaces = [
            // Standard Laravel app structure
            'App\\Filters',
            'App\\Models\\Filters',
            
            // Add model namespace based paths
            $modelNamespace . '\\Filters',
            
            // For modular structure (Modules/*/Models)
            $this->getModularFilterNamespace($modelClass),
        ];
        
        // Add the model's own namespace
        $possibleNamespaces[] = $modelNamespace;
        
        foreach ($possibleNamespaces as $namespace) {
            if (empty($namespace)) continue;
            
            $filterClass = $namespace . '\\' . $modelName . 'Filter';
            if (class_exists($filterClass)) {
                return $filterClass;
            }
        }
        
        // Return the default expected path
        return 'App\\Filters\\' . $modelName . 'Filter';
    }
    
    /**
     * Determine a filter namespace for modular structures.
     * 
     * @param string $modelClass
     * @return string|null
     */
    protected function getModularFilterNamespace(string $modelClass): ?string
    {
        // For models in Modules/*/Models structure
        if (Str::contains($modelClass, '\\Modules\\')) {
            $parts = explode('\\', $modelClass);
            
            // Find the "Modules" part in the namespace
            $moduleIndex = array_search('Modules', $parts);
            if ($moduleIndex !== false && isset($parts[$moduleIndex + 1])) {
                $moduleName = $parts[$moduleIndex + 1];
                return implode('\\', array_slice($parts, 0, $moduleIndex + 1)) . '\\' . $moduleName . '\\Filters';
            }
        }
        
        return null;
    }
    
    /**
     * Get the namespace of the model.
     *
     * @param string $modelClass
     * @return string
     */
    protected function getModelNamespace(string $modelClass): string
    {
        try {
            $reflection = new ReflectionClass($modelClass);
            return $reflection->getNamespaceName();
        } catch (\ReflectionException $e) {
            \Log::warning('Failed to get model namespace', ['model' => $modelClass, 'error' => $e->getMessage()]);
            return '';
        }
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
        $request = $parameters['request'] ?? app(\Illuminate\Http\Request::class);
        
        // Process the request parameters for proper filtering
        $filterableColumns = $this->getFilterableColumnsFromModel($modelClass);
        
        // Check if cache config is enabled
        $cacheDuration = config('laravel-filters.cache_duration', 0);
        
        if ($cacheDuration > 0) {
            // Check if we've already generated this filter
            if (Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                
                // Make sure we have full cached data (both class and columns)
                if (is_array($cachedData) && 
                    isset($cachedData['class']) && 
                    isset($cachedData['columns']) &&
                    class_exists($cachedData['class'])) {
                    
                    \Log::debug('Retrieved filter from cache', [
                        'model' => $modelClass,
                        'filter' => $cachedData['class']
                    ]);
                    
                    $filterInstance = $this->resolveFilterInstance($cachedData['class'], $parameters);
                    
                    // Configure the filter with the cached columns if needed
                    if (method_exists($filterInstance, 'setFilterableColumns')) {
                        $filterInstance->setFilterableColumns($cachedData['columns']);
                    }
                    
                    return $filterInstance;
                }
            }
        }

        \Log::debug('Generating dynamic filter for model', ['model' => $modelClass]);
        
        // Create an instance of ModelFilter and configure it for the target model
        $baseFilter = new ModelFilter($request);
        $baseFilter->forModel($modelClass);
        
        // Store filter configuration in cache if caching is enabled
        if ($cacheDuration > 0) {
            $cacheData = [
                'class' => get_class($baseFilter),
                'columns' => $filterableColumns
            ];
            
            Cache::put($cacheKey, $cacheData, now()->addMinutes($cacheDuration));
            
            \Log::debug('Stored filter in cache', [
                'model' => $modelClass, 
                'cache_key' => $cacheKey,
                'duration' => $cacheDuration
            ]);
        }
        
        return $baseFilter;
    }
    
    /**
     * Get filterable columns from model.
     * 
     * @param string $modelClass
     * @return array
     */
    protected function getFilterableColumnsFromModel(string $modelClass): array
    {
        // First try using the Filterable trait's method
        if (method_exists($modelClass, 'getFilterableColumns')) {
            \Log::debug('Getting filterable columns from model method', ['model' => $modelClass]);
            $columns = $modelClass::getFilterableColumns();
            if (!empty($columns)) {
                return $columns;
            }
        }
        
        // Try to get fillable from model
        try {
            \Log::debug('Attempting to get fillable columns', ['model' => $modelClass]);
            $model = new $modelClass;
            if (method_exists($model, 'getFillable')) {
                $fillable = $model->getFillable();
                if (!empty($fillable)) {
                    return $fillable;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to instantiate model', [
                'model' => $modelClass, 
                'error' => $e->getMessage()
            ]);
        }
        
        // Fallback to empty array
        \Log::warning('No filterable columns found for model', ['model' => $modelClass]);
        return [];
    }
}