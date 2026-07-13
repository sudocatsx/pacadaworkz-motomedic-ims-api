<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributesValue;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Seed reusable motorcycle product attributes and their values.
     */
    public function run(): void
    {
        $attributes = [
            'Color' => [
                'description' => 'Available product colors and finishes',
                'values' => ['Black', 'White', 'Red', 'Blue', 'Silver', 'Gold'],
            ],
            'Size' => [
                'description' => 'General apparel and helmet sizes',
                'values' => ['XS', 'S', 'M', 'L', 'XL', '2XL'],
            ],
            'Oil Viscosity' => [
                'description' => 'Motorcycle oil viscosity grades',
                'values' => ['10W-30', '10W-40', '15W-40', '20W-40', '20W-50'],
            ],
            'Tire Size' => [
                'description' => 'Common motorcycle tire sizes',
                'values' => ['70/90-17', '80/90-17', '90/80-17', '100/80-17', '110/70-17'],
            ],
        ];

        foreach ($attributes as $name => $definition) {
            $attribute = Attribute::withTrashed()->firstOrNew(['name' => $name]);
            $attribute->description = $definition['description'];
            $attribute->deleted_at = null;
            $attribute->save();

            foreach ($definition['values'] as $value) {
                $attributeValue = AttributesValue::withTrashed()->firstOrNew([
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                ]);
                $attributeValue->deleted_at = null;
                $attributeValue->save();
            }
        }
    }
}
