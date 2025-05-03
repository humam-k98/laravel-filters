<?php

namespace HumamK98\LaravelFilters\Generators;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Illuminate\Support\Facades\File;

class FilterGenerator
{
    /**
     * Generate a filter class for the given model.
     *
     * @param string|Model $modelClass The model class or instance
     * @param string|null $namespace The namespace for the generated filter (defaults to App\Filters)
     * @param string|null $path The path where the filter should be saved
     * @return string The fully qualified class name of the generated filter
     */
    public function generateFilterForModel($modelClass, ?string $namespace = null, ?string $path = null): string
    {
        // Handle model instance or class name
        if ($modelClass instanceof Model) {
            $modelClass = get_class($modelClass);
        }
        
        // Get model reflection
        $reflection = new ReflectionClass($modelClass);
        $modelName = $reflection->getShortName();
        
        // Set default namespace if not provided
        $namespace = $namespace ?? 'App\\Filters';
        
        // Set the filter class name
        $filterClassName = "{$modelName}Filter";
        $fullFilterClassName = "{$namespace}\\{$filterClassName}";
        
        // Get model filterable columns
        $filterableColumns = $this->getFilterableColumns($modelClass);
        
        // Generate filter class content
        $content = $this->generateFilterClassContent($namespace, $filterClassName, $modelClass, $filterableColumns);
        
        // Save filter class if path provided
        if ($path) {
            $this->saveFilterClass($path, $namespace, $filterClassName, $content);
        }
        
        return $fullFilterClassName;
    }
    
    /**
     * Get filterable columns from model.
     *
     * @param string $modelClass
     * @return array
     */
    protected function getFilterableColumns(string $modelClass): array
    {
        if (method_exists($modelClass, 'getFilterableColumns')) {
            return $modelClass::getFilterableColumns();
        }
        
        // Try to get fillable fields as fallback
        $model = new $modelClass;
        return $model->getFillable();
    }
    
    /**
     * Generate filter class content.
     *
     * @param string $namespace
     * @param string $filterClassName
     * @param string $modelClass
     * @param array $filterableColumns
     * @return string
     */
    protected function generateFilterClassContent(string $namespace, string $filterClassName, string $modelClass, array $filterableColumns): string
    {
        // Start building class content
        $content = "<?php\n\n";
        $content .= "namespace {$namespace};\n\n";
        $content .= "use HumamK98\\LaravelFilters\\Filters\\Filter;\n";
        $content .= "use {$modelClass};\n\n";
        $content .= "/**\n";
        $content .= " * Filter for " . class_basename($modelClass) . " model.\n";
        $content .= " * \n";
        $content .= " * @package {$namespace}\n";
        $content .= " * @generated automatically by HumamK98\\LaravelFilters\n";
        $content .= " */\n";
        $content .= "class {$filterClassName} extends Filter\n{\n";
        
        // Add getAllowedFilters method
        $content .= "    /**\n";
        $content .= "     * Get the allowed filters.\n";
        $content .= "     *\n";
        $content .= "     * @return array\n";
        $content .= "     */\n";
        $content .= "    protected function getAllowedFilters(): array\n";
        $content .= "    {\n";
        $content .= "        return [\n";
        
        // Add all filterable columns and their variations (min, max, like, etc.)
        foreach ($filterableColumns as $column) {
            $content .= "            '{$column}',\n";
            $content .= "            '{$column}_min',\n";
            $content .= "            '{$column}_max',\n";
            $content .= "            '{$column}_like',\n";
        }
        
        // Add sorting params
        $content .= "            'sort_by',\n";
        $content .= "            'sort_direction',\n";
        $content .= "        ];\n";
        $content .= "    }\n\n";
        
        // Generate filter methods for each column based on column type if possible
        $model = new $modelClass;
        if (method_exists($model, 'getConnection')) {
            $connection = $model->getConnection();
            $table = $model->getTable();
            
            try {
                $columnListing = $connection->getSchemaBuilder()->getColumnListing($table);
                $columnTypes = [];
                
                foreach ($columnListing as $column) {
                    if (in_array($column, $filterableColumns)) {
                        $type = $connection->getSchemaBuilder()->getColumnType($table, $column);
                        $columnTypes[$column] = $type;
                        
                        // Generate specific filter methods based on type
                        $this->generateFilterMethod($content, $column, $type);
                    }
                }
            } catch (\Exception $e) {
                // If schema reflection fails, just continue without type-specific methods
            }
        }
        
        // Close the class
        $content .= "}\n";
        
        return $content;
    }
    
    /**
     * Generate filter methods based on column type.
     *
     * @param string &$content The class content being built
     * @param string $column The column name
     * @param string $type The column type
     * @return void
     */
    protected function generateFilterMethod(string &$content, string $column, string $type): void
    {
        $camelColumn = Str::camel($column);
        
        // Generate exact match method
        $content .= "    /**\n";
        $content .= "     * Filter by {$column}.\n";
        $content .= "     *\n";
        $content .= "     * @param mixed \${$camelColumn}\n";
        $content .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
        $content .= "     */\n";
        $content .= "    protected function {$camelColumn}(\${$camelColumn})\n";
        $content .= "    {\n";
        $content .= "        return \$this->builder->where('{$column}', \${$camelColumn});\n";
        $content .= "    }\n\n";
        
        // Generate min/max methods for numeric types
        if (in_array($type, ['integer', 'bigint', 'float', 'double', 'decimal', 'numeric'])) {
            // Min method
            $content .= "    /**\n";
            $content .= "     * Filter by minimum {$column}.\n";
            $content .= "     *\n";
            $content .= "     * @param mixed \$value\n";
            $content .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
            $content .= "     */\n";
            $content .= "    protected function {$camelColumn}Min(\$value)\n";
            $content .= "    {\n";
            $content .= "        return \$this->builder->where('{$column}', '>=', \$value);\n";
            $content .= "    }\n\n";
            
            // Max method
            $content .= "    /**\n";
            $content .= "     * Filter by maximum {$column}.\n";
            $content .= "     *\n";
            $content .= "     * @param mixed \$value\n";
            $content .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
            $content .= "     */\n";
            $content .= "    protected function {$camelColumn}Max(\$value)\n";
            $content .= "    {\n";
            $content .= "        return \$this->builder->where('{$column}', '<=', \$value);\n";
            $content .= "    }\n\n";
        }
        
        // Generate like method for string types
        if (in_array($type, ['string', 'text', 'char', 'varchar'])) {
            $content .= "    /**\n";
            $content .= "     * Filter by {$column} using LIKE.\n";
            $content .= "     *\n";
            $content .= "     * @param mixed \$value\n";
            $content .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
            $content .= "     */\n";
            $content .= "    protected function {$camelColumn}Like(\$value)\n";
            $content .= "    {\n";
            $content .= "        return \$this->builder->where('{$column}', 'like', \"%{\$value}%\");\n";
            $content .= "    }\n\n";
        }
        
        // Generate date range methods for date types
        if (in_array($type, ['date', 'datetime', 'timestamp'])) {
            // Min method (after)
            $content .= "    /**\n";
            $content .= "     * Filter by {$column} after given date.\n";
            $content .= "     *\n";
            $content .= "     * @param string \$date\n";
            $content .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
            $content .= "     */\n";
            $content .= "    protected function {$camelColumn}Min(\$date)\n";
            $content .= "    {\n";
            $content .= "        return \$this->builder->whereDate('{$column}', '>=', \$date);\n";
            $content .= "    }\n\n";
            
            // Max method (before)
            $content .= "    /**\n";
            $content .= "     * Filter by {$column} before given date.\n";
            $content .= "     *\n";
            $content .= "     * @param string \$date\n";
            $content .= "     * @return \\Illuminate\\Database\\Eloquent\\Builder\n";
            $content .= "     */\n";
            $content .= "    protected function {$camelColumn}Max(\$date)\n";
            $content .= "    {\n";
            $content .= "        return \$this->builder->whereDate('{$column}', '<=', \$date);\n";
            $content .= "    }\n\n";
        }
    }
    
    /**
     * Save filter class to disk.
     *
     * @param string $basePath
     * @param string $namespace
     * @param string $className
     * @param string $content
     * @return string The path to the saved file
     */
    protected function saveFilterClass(string $basePath, string $namespace, string $className, string $content): string
    {
        $relativePath = str_replace('\\', '/', str_replace('App\\', '', $namespace));
        $directory = $basePath . '/' . $relativePath;
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        
        $filePath = $directory . '/' . $className . '.php';
        File::put($filePath, $content);
        
        return $filePath;
    }
}