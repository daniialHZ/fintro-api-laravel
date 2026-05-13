<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class DefaultSourcesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sources() as $source) {
            Source::query()->firstOrCreate(['name' => $source['name']], $source);
        }
    }

    private function sources(): array
    {
        return [
            ['name' => 'bank_melli', 'name_fa' => 'بانک ملی', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_mellat', 'name_fa' => 'بانک ملت', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_saderat', 'name_fa' => 'بانک صادرات', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_tejarat', 'name_fa' => 'بانک تجارت', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_sepah', 'name_fa' => 'بانک سپه', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_parsian', 'name_fa' => 'بانک پارسیان', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_pasargad', 'name_fa' => 'بانک پاسارگاد', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_saman', 'name_fa' => 'بانک سامان', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_ayandeh', 'name_fa' => 'بانک آینده', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_eghtesad', 'name_fa' => 'بانک اقتصاد نوین', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_karafarin', 'name_fa' => 'بانک کارآفرین', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_sinap', 'name_fa' => 'بانک سینا', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_maskan', 'name_fa' => 'بانک مسکن', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'bank_keshavarzi', 'name_fa' => 'بانک کشاورزی', 'icon' => '🏦', 'is_default' => true],
            ['name' => 'cash', 'name_fa' => 'نقدی', 'icon' => '💵', 'is_default' => true],
            ['name' => 'salary', 'name_fa' => 'حقوق', 'icon' => '💰', 'is_default' => true],
            ['name' => 'freelance', 'name_fa' => 'فریلنسر', 'icon' => '💻', 'is_default' => true],
            ['name' => 'gift', 'name_fa' => 'هدیه', 'icon' => '🎁', 'is_default' => true],
            ['name' => 'transfer', 'name_fa' => 'انتقالی', 'icon' => '🔄', 'is_default' => true],
            ['name' => 'other', 'name_fa' => 'سایر', 'icon' => '📌', 'is_default' => true],
        ];
    }
}
