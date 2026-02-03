<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // إضافة فئات افتراضية
        $categories = [
            [
                'name' => 'تجارة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'صرافة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'سفريات وسياحة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'خدمات طبية',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'نقل وتخليص جمركي',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        foreach ($categories as $category) {
            DB::table('categories')->insert($category);
        }

        // إضافة مجموعات وسوم افتراضية
        $tagGroups = [
            [
                'name' => 'اعلانات سفريات وسياحة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'جمعة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'مناسبات',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'اعلانات صرافة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'اعلانات منتجات',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        foreach ($tagGroups as $tagGroup) {
            DB::table('tags_groups')->insert($tagGroup);
        }

        // إضافة مواقع افتراضية
        $locations = [
            [
                'name' => 'جيبوتي',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'عمان',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'السعودية',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'شمالي',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'جنوبي',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        foreach ($locations as $location) {
            DB::table('locations')->insert($location);
        }

        // إضافة عملات افتراضية
        $currencies = [
            [
                'currency' => 'USD',
                'currency_name' => 'دولار أمريكي',
                'value' => '530.00',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        foreach ($currencies as $currency) {
            DB::table('currencies')->insert($currency);
        }
    }
}
