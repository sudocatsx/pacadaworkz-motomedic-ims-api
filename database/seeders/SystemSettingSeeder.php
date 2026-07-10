<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'business_name' => 'PacadaWorkz Moto Medic',
            'business_address' => '10A 5th St East Grace Park, Caloocan, Philippines',
            'contact_info' => 'pacadaworkz2021@gmail.com',
            'business_phone' => '0917-123-4567', // Placeholder generic phone if needed
            'currency_symbol' => '₱',
            'footer_message' => 'Thank you for your business!',
            'return_policy' => 'No return, no exchange.',
            'logo_url' => null, 
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'description' => $this->getDescription($key)
                ]
            );
        }
    }

    private function getDescription($key)
    {
        return match ($key) {
            'business_name' => 'The official name of the business used in reports and receipts.',
            'business_address' => 'The physical address displayed on receipts.',
            'contact_info' => 'Primary contact email or phone number.',
            'business_phone' => 'Contact number for inquiries.',
            'currency_symbol' => 'Currency symbol used throughout the application.',
            'footer_message' => 'Message displayed at the bottom of the receipt.',
            'return_policy' => 'Return policy disclaimer on receipts.',
            'logo_url' => 'URL or path to the business logo image.',
            default => 'System configuration setting.',
        };
    }
}
