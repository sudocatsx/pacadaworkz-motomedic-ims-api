<?php

use App\Models\Attribute;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

test('attribute seeder is repeatable and creates the focused demo preset', function () {
    $this->seed(AttributeSeeder::class);
    $this->seed(AttributeSeeder::class);

    expect(Attribute::count())->toBe(4)
        ->and(DB::table('attributes_values')->count())->toBe(22)
        ->and(Attribute::with('attribute_values')->where('name', 'Color')->firstOrFail()
            ->attribute_values->pluck('value')->all())
        ->toBe(['Black', 'White', 'Red', 'Blue', 'Silver', 'Gold'])
        ->and(Attribute::with('attribute_values')->where('name', 'Size')->firstOrFail()
            ->attribute_values->pluck('value')->all())
        ->toBe(['XS', 'S', 'M', 'L', 'XL', '2XL'])
        ->and(Attribute::with('attribute_values')->where('name', 'Oil Viscosity')->firstOrFail()
            ->attribute_values->pluck('value')->all())
        ->toBe(['10W-30', '10W-40', '15W-40', '20W-40', '20W-50'])
        ->and(Attribute::with('attribute_values')->where('name', 'Tire Size')->firstOrFail()
            ->attribute_values->pluck('value')->all())
        ->toBe(['70/90-17', '80/90-17', '90/80-17', '100/80-17', '110/70-17']);
});

test('default database seeder creates demo foundations without operational records', function () {
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('roles')->count())->toBe(4)
        ->and(DB::table('users')->count())->toBe(4)
        ->and(DB::table('categories')->count())->toBeGreaterThan(0)
        ->and(DB::table('brands')->count())->toBeGreaterThan(0)
        ->and(DB::table('attributes')->count())->toBe(4)
        ->and(DB::table('suppliers')->count())->toBe(0)
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('inventory')->count())->toBe(0)
        ->and(DB::table('purchase_orders')->count())->toBe(0)
        ->and(DB::table('stock_movements')->count())->toBe(0)
        ->and(DB::table('stock_adjustments')->count())->toBe(0);
});
