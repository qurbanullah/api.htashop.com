<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'flag_emoji' => '🇬🇧',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'ur',
                'name' => 'Urdu',
                'native_name' => 'اردو',
                'flag_emoji' => '🇵🇰',
                'is_rtl' => true, // RTL language
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'de',
                'name' => 'German',
                'native_name' => 'Deutsch',
                'flag_emoji' => '🇩🇪',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }

        $this->command->info('Languages seeded successfully!');
    }
}
