<?php

namespace HumamK98\LaravelFilters\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use HumamK98\LaravelFilters\Traits\Filterable;
use HumamK98\LaravelFilters\LaravelFiltersServiceProvider;
use HumamK98\LaravelFilters\Filters\ModelFilter;
use Illuminate\Http\Request;

class FilterableTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [LaravelFiltersServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a products test table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('category');
            $table->boolean('is_available')->default(true);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        // Create test products data
        Product::create([
            'name' => 'Smartphone X', 
            'description' => 'Latest model with high-end features', 
            'price' => 999.99,
            'category' => 'electronics',
            'is_available' => true,
            'stock' => 50
        ]);
        
        Product::create([
            'name' => 'Laptop Pro', 
            'description' => 'Powerful laptop for professionals', 
            'price' => 1499.99,
            'category' => 'electronics',
            'is_available' => true,
            'stock' => 25
        ]);
        
        Product::create([
            'name' => 'Desk Chair', 
            'description' => 'Ergonomic office chair', 
            'price' => 199.99,
            'category' => 'furniture',
            'is_available' => true,
            'stock' => 15
        ]);
        
        Product::create([
            'name' => 'Coffee Table', 
            'description' => 'Modern design coffee table', 
            'price' => 149.50,
            'category' => 'furniture',
            'is_available' => false,
            'stock' => 0
        ]);
        
        Product::create([
            'name' => 'Smart Watch', 
            'description' => 'Fitness tracker and smartwatch', 
            'price' => 249.99,
            'category' => 'electronics',
            'is_available' => true,
            'stock' => 30
        ]);
    }

    /** @test */
    public function it_can_filter_with_zero_configuration()
    {
        // Test the most basic usage with just the trait and no explicit filter classes
        app()->instance('request', new Request(['category' => 'electronics']));
        
        $products = Product::filter()->get();
        
        $this->assertCount(3, $products);
        $this->assertEquals(['Smartphone X', 'Laptop Pro', 'Smart Watch'], 
            $products->pluck('name')->toArray());
    }

    /** @test */
    public function it_can_filter_by_exact_match_using_filterable_trait()
    {
        app()->instance('request', new Request(['name' => 'Laptop Pro']));
        
        $products = Product::filter()->get();
        
        $this->assertCount(1, $products);
        $this->assertEquals('Laptop Pro', $products->first()->name);
    }

    /** @test */
    public function it_can_filter_by_boolean_value_using_filterable_trait()
    {
        app()->instance('request', new Request(['is_available' => false]));
        
        $products = Product::filter()->get();
        
        $this->assertCount(1, $products);
        $this->assertEquals('Coffee Table', $products->first()->name);
    }

    /** @test */
    public function it_can_filter_with_numeric_range_using_filterable_trait()
    {
        app()->instance('request', new Request(['price_min' => 200, 'price_max' => 1000]));
        
        $products = Product::filter()->get();
        
        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('name', 'Smartphone X'));
        $this->assertTrue($products->contains('name', 'Smart Watch'));
    }

    /** @test */
    public function it_can_filter_with_like_suffix_using_filterable_trait()
    {
        app()->instance('request', new Request(['name_like' => 'Smart']));
        
        $products = Product::filter()->get();
        
        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('name', 'Smartphone X'));
        $this->assertTrue($products->contains('name', 'Smart Watch'));
    }

    /** @test */
    public function it_can_combine_multiple_filters_with_filterable_trait()
    {
        app()->instance('request', new Request([
            'category' => 'electronics',
            'price_min' => 500,
            'is_available' => true
        ]));
        
        $products = Product::filter()->get();
        
        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('name', 'Smartphone X'));
        $this->assertTrue($products->contains('name', 'Laptop Pro'));
    }

    /** @test */
    public function it_can_sort_results_with_filterable_trait()
    {
        app()->instance('request', new Request([
            'category' => 'electronics',
            'sort_by' => 'price',
            'sort_direction' => 'asc'
        ]));
        
        $products = Product::filter()->get();
        
        $this->assertCount(3, $products);
        $this->assertEquals('Smart Watch', $products->first()->name); // Cheapest electronic
        $this->assertEquals('Laptop Pro', $products->last()->name);   // Most expensive electronic
    }

    /** @test */
    public function it_respects_model_filterable_property_for_allowed_filters()
    {
        // ProductWithRestrictedFilters only allows filtering by name and price
        app()->instance('request', new Request(['category' => 'electronics']));
        
        $products = ProductWithRestrictedFilters::filter()->get();
        
        // Since 'category' is not in the filterable array, it should return all products
        $this->assertCount(5, $products);
    }
    
    /** @test */
    public function it_uses_explicit_modelfilter_instance_without_custom_filter_class()
    {
        $request = new Request(['price_min' => 1000]);
        
        // Create ModelFilter instance directly and specify the model class
        $filter = app(ModelFilter::class, ['request' => $request]);
        $filter->forModel(Product::class);
        
        // Apply the filter
        $products = Product::filter($filter)->get();
        
        $this->assertCount(1, $products);
        $this->assertEquals('Laptop Pro', $products->first()->name);
    }
    
    /** @test */
    public function it_combines_multiple_filter_types_with_modelfilter_class()
    {
        // Let's create a simpler test case that more reliably tests the functionality
        $request = new Request([
            'category' => 'furniture', // Both chair and table are furniture
            'price_min' => 100 // Both are above $100
        ]);
        
        $filter = app(ModelFilter::class, ['request' => $request]);
        $filter->forModel(Product::class);
        
        $products = Product::filter($filter)->get();
        
        // Should find both furniture items above $100 (Desk Chair and Coffee Table)
        $this->assertCount(2, $products);
        $allNames = $products->pluck('name')->toArray();
        
        // Verify both expected products are in the results
        $this->assertContains('Desk Chair', $allNames, 'Results should include Desk Chair');
        $this->assertContains('Coffee Table', $allNames, 'Results should include Coffee Table');
    }

    /** @test */
    public function it_filters_using_dynamicfilterresolver_without_custom_class()
    {
        $request = new Request(['stock' => 0]);
        
        $resolver = app(\HumamK98\LaravelFilters\Filters\DynamicFilterResolver::class);
        $filter = $resolver->resolveFilterForModel(Product::class, ['request' => $request]);
        
        $products = Product::filter($filter)->get();
        
        $this->assertCount(1, $products);
        $this->assertEquals('Coffee Table', $products->first()->name);
    }
}

// Test model with Filterable trait - default configuration
class Product extends Model
{
    use Filterable;

    protected $table = 'products';
    protected $fillable = ['name', 'description', 'price', 'category', 'is_available', 'stock'];
    public $timestamps = true;
}

// Test model with restricted filterable columns
class ProductWithRestrictedFilters extends Model
{
    use Filterable;

    protected $table = 'products'; // Using the same table as Product
    protected $fillable = ['name', 'description', 'price', 'category', 'is_available', 'stock'];
    public $timestamps = true;
    
    // Only allow filtering by name and price
    protected $filterable = ['name', 'price'];
}