<?php

namespace HumamK98\LaravelFilters\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use HumamK98\LaravelFilters\Traits\Filterable;
use HumamK98\LaravelFilters\Filters\Filter;
use HumamK98\LaravelFilters\LaravelFiltersServiceProvider;
use HumamK98\LaravelFilters\Filters\DynamicFilterResolver;
use Illuminate\Http\Request;

class FilterTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [LaravelFiltersServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test database table
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create some test data
        TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 25, 'is_active' => true]);
        TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 30, 'is_active' => true]);
        TestUser::create(['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 40, 'is_active' => false]);
        TestUser::create(['name' => 'Alice Jones', 'email' => 'alice@example.com', 'age' => 22, 'is_active' => true]);
        TestUser::create(['name' => 'Tom Johnson', 'email' => 'tom@example.com', 'age' => 35, 'is_active' => false]);
    }

    /** @test */
    public function it_can_filter_by_exact_match_using_dynamic_filter()
    {
        // Create a request with parameters
        $request = new Request(['email' => 'john@example.com']);
        
        // Get resolver and create a filter with our request
        $resolver = app(DynamicFilterResolver::class);
        $filter = $resolver->resolveFilterForModel(TestUser::class, ['request' => $request]);
        
        // Apply the filter
        $users = TestUser::filter($filter)->get();
        
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    /** @test */
    public function it_can_filter_by_range_using_dynamic_filter()
    {
        // Create a request with parameters
        $request = new Request(['age_min' => 25, 'age_max' => 35]);
        
        // Get resolver and create a filter with our request
        $resolver = app(DynamicFilterResolver::class);
        $filter = $resolver->resolveFilterForModel(TestUser::class, ['request' => $request]);
        
        // Apply the filter
        $users = TestUser::filter($filter)->get();
        
        $this->assertCount(3, $users);
    }

    /** @test */
    public function it_can_filter_using_custom_filter_class()
    {
        $request = new Request(['is_active' => true]);
        
        $filter = new TestUserFilter($request);
        $users = TestUser::filter($filter)->get();
        
        $this->assertCount(3, $users);
    }

    /** @test */
    public function it_can_sort_results()
    {
        $request = new Request(['sort_by' => 'age', 'sort_direction' => 'desc']);
        
        // Get resolver and create a filter with our request
        $resolver = app(DynamicFilterResolver::class);
        $filter = $resolver->resolveFilterForModel(TestUser::class, ['request' => $request]);
        
        // Apply the filter
        $users = TestUser::filter($filter)->get();
        
        $this->assertEquals(40, $users->first()->age);
        $this->assertEquals(22, $users->last()->age);
    }
}

// Test model with Filterable trait
class TestUser extends Model
{
    use Filterable;

    protected $table = 'test_users';
    protected $fillable = ['name', 'email', 'age', 'is_active'];
    public $timestamps = true;
    
    // Define filterable columns explicitly
    protected $filterable = ['name', 'email', 'age', 'is_active', 'created_at'];
}

// Custom filter class for testing
class TestUserFilter extends Filter
{
    protected function getAllowedFilters(): array
    {
        return [
            'name',
            'email',
            'age',
            'age_min',
            'age_max',
            'is_active',
            'sort_by',
            'sort_direction',
        ];
    }
    
    protected function name($name)
    {
        return $this->builder->where('name', 'like', "%{$name}%");
    }
    
    protected function email($email)
    {
        return $this->builder->where('email', '=', $email);
    }
    
    protected function ageMin($age)
    {
        return $this->builder->where('age', '>=', $age);
    }
    
    protected function ageMax($age)
    {
        return $this->builder->where('age', '<=', $age);
    }
    
    protected function isActive($isActive)
    {
        return $this->builder->where('is_active', $isActive);
    }
}