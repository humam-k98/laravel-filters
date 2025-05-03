# Laravel Filters

A powerful Laravel 8+ filtering package that provides a clean and flexible way to filter Eloquent models using the builder pattern. Now with support for Laravel 8, 9, 10, and 11! It enables developers to create robust API filtering systems with minimal code, supporting both manual filter creation and dynamic generation of filter classes based on model properties.

## Features

- **Zero Configuration** - Works out of the box with your Eloquent models
- **Flexible Filtering** - Filter by exact matches, ranges, partial matches, and more
- **Sorting Support** - Easy sorting with sort_by and sort_direction parameters
- **Framework Compatibility** - Works with Laravel 8, 9, 10, and 11
- **Custom Filters** - Create custom filter classes for complex filtering logic
- **Dynamic Filters** - Generate filters on-the-fly based on model properties
- **Parameter Aliasing** - Map request parameters to filter methods
- **Pagination Support** - Seamlessly works with Laravel's pagination

## Installation

You can install the package via composer:

```bash
composer require humam-k98/laravel-filters
```

The package will automatically register its service provider.

## Basic Usage

### 1. Add the Filterable trait to your model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use HumamK98\LaravelFilters\Traits\Filterable;

class User extends Model
{
    use Filterable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'age', 'created_at',
    ];
    
    /**
     * The attributes that can be filtered.
     *
     * @var array
     */
    protected $filterable = [
        'name', 'email', 'age', 'created_at',
    ];
}
```

### 2. Start filtering with zero configuration!

The easiest way to use this package - dynamic filtering with no extra configuration:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users with automatic filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // No filter class needed - it's created automatically!
        $users = User::filter()->paginate(10);
        
        return response()->json($users);
    }
}
```

Now you can make API requests with filter parameters:

```
GET /api/users?name=john&age_min=18&age_max=35&sort_by=created_at&sort_direction=desc
```

## Advanced Usage

### Creating a custom filter class for more control

For complex filtering needs, you can create your own filter class:

```php
<?php

namespace App\Filters;

use HumamK98\LaravelFilters\Filters\Filter;

class UserFilter extends Filter
{
    /**
     * Get the allowed filters.
     *
     * @return array
     */
    protected function getAllowedFilters(): array
    {
        return [
            'name', 
            'email', 
            'age', 
            'age_min', 
            'age_max',
            'created_at',
            'sort_by',
            'sort_direction',
            'is_active', // Custom filter parameter
        ];
    }
    
    /**
     * Filter users by name.
     *
     * @param string $name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function name($name)
    {
        return $this->builder->where('name', 'like', "%{$name}%");
    }
    
    /**
     * Filter users by minimum age.
     *
     * @param int $age
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function ageMin($age)
    {
        return $this->builder->where('age', '>=', $age);
    }
    
    /**
     * Filter users by maximum age.
     *
     * @param int $age
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function ageMax($age)
    {
        return $this->builder->where('age', '<=', $age);
    }
    
    /**
     * Filter by active status (custom complex logic).
     *
     * @param bool $isActive
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function isActive($isActive)
    {
        if ($isActive) {
            return $this->builder->whereNotNull('email_verified_at')
                ->where('banned', false);
        }
        
        return $this->builder->whereNull('email_verified_at')
            ->orWhere('banned', true);
    }
}
```

And in your controller:

```php
public function index(Request $request, UserFilter $filter)
{
    $users = User::filter($filter)->paginate(10);
    
    return response()->json($users);
}
```

## 3 Filtering Approaches

### 1. Dynamic Auto-Generated Filters (Zero Configuration)

```php
// In controller
public function index()
{
    // The filter is generated automatically based on model properties
    return User::filter()->paginate(10);
}
```

### 2. Using ModelFilter with Explicit Model

```php
use HumamK98\LaravelFilters\Filters\ModelFilter;

public function index(Request $request)
{
    $filter = app(ModelFilter::class)->forModel(User::class);
    return User::filter($filter)->paginate(10);
}
```

### 3. Custom Filter Class for Complex Logic

```php
use App\Filters\UserFilter;

public function index(Request $request, UserFilter $filter)
{
    return User::filter($filter)->paginate(10);
}
```

## Filter Parameter Types

The package supports various filter parameter formats:

| Parameter Format | Example | Description |
|------------------|---------|-------------|
| `{column}` | `name=John` | Exact match |
| `{column}_min` | `age_min=18` | Greater than or equal |
| `{column}_max` | `age_max=65` | Less than or equal |
| `{column}_like` | `name_like=John` | LIKE search with % wildcards |
| `sort_by` | `sort_by=created_at` | Column to sort by |
| `sort_direction` | `sort_direction=desc` | Sort direction (asc/desc) |

## Filtering with Static Values

Sometimes you may want to filter your models by predefined static values rather than dynamic request parameters. Here are several approaches to achieve this:

### Using Static Conditions with Filters

You can combine the filter functionality with static conditions:

```php
// In your controller
public function getAdminUsers(Request $request)
{
    // Apply both dynamic filters from request and a static condition
    $users = User::filter()
        ->where('type', 'Admin')
        ->paginate(10);
        
    return response()->json($users);
}
```

### Using Query Scopes with Filters

Laravel query scopes work seamlessly with the filters:

```php
// In your User model
class User extends Model
{
    use Filterable;
    
    // Define a query scope
    public function scopeOnlyAdmins($query)
    {
        return $query->where('type', 'Admin');
    }
}

// In your controller
public function getAdminUsers(Request $request)
{
    // Apply both the scope and dynamic filters
    $users = User::onlyAdmins()->filter()->paginate(10);
    
    return response()->json($users);
}
```

### Custom Filter Method for Static Values

Create a dedicated filter method in your custom filter class:

```php
class UserFilter extends Filter
{
    protected function getAllowedFilters(): array
    {
        return [
            'name', 
            'email',
            'only_admins', // Custom filter parameter for admins
            // other filters...
        ];
    }
    
    // Filter by admin type (static value)
    protected function onlyAdmins($value = true)
    {
        if ($value) {
            return $this->builder->where('type', 'Admin');
        }
        return $this->builder;
    }
}
```

Then in your API endpoint:
```
GET /api/users?only_admins=true
```

### Adding Static Conditions to a Dynamic Filter

```php
use HumamK98\LaravelFilters\Filters\DynamicFilterResolver;

public function index(Request $request, DynamicFilterResolver $resolver)
{
    $filter = $resolver->resolveFilterForModel(User::class);
    
    // Add a static condition to the filter
    $filter->addFilterClosure(function($builder) {
        return $builder->where('type', 'Admin');
    });
    
    $users = User::filter($filter)->paginate(10);
    return response()->json($users);
}
```

## Examples

### Simple Filtering

```
/api/users?name=John
```

### Range Filtering

```
/api/users?age_min=18&age_max=35
```

### Combined Filters with Sorting

```
/api/users?name_like=Smith&created_at_min=2023-01-01&sort_by=created_at&sort_direction=desc
```

### Using DynamicFilterResolver Directly

For advanced cases, you can use the resolver directly:

```php
use HumamK98\LaravelFilters\Filters\DynamicFilterResolver;

public function index(Request $request, DynamicFilterResolver $resolver)
{
    $filter = $resolver->resolveFilterForModel(User::class, ['request' => $request]);
    
    // Apply additional conditions to the filter if needed
    
    $users = User::filter($filter)->paginate(10);
    return response()->json($users);
}
```

## Package Configuration

You can publish the configuration file with:

```bash
php artisan vendor:publish --tag=laravel-filters-config
```

This will create a `config/laravel-filters.php` file where you can adjust settings:

```php
return [
    // Default operator for "like" searches
    'default_like_operator' => 'like', // Options: 'like', 'ilike' (PostgreSQL)
    
    // Whether to enable automatic query parameter aliasing
    'enable_param_aliasing' => true,
    
    // Common parameter mappings
    'param_mappings' => [
        'created_after' => 'created_at_min',
        'created_before' => 'created_at_max',
        // Add your own aliases here
    ],
];
```

## License

The MIT License (MIT).