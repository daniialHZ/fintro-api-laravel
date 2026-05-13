<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $category) {
            Category::query()->firstOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }

    private function categories(): array
    {
        return [
            ['name' => 'salary', 'name_fa' => 'حقوق', 'icon' => '💰', 'type' => 'income', 'is_default' => true],
            ['name' => 'freelance', 'name_fa' => 'فریلنسری', 'icon' => '💻', 'type' => 'income', 'is_default' => true],
            ['name' => 'bonus', 'name_fa' => 'پاداش', 'icon' => '🎁', 'type' => 'income', 'is_default' => true],
            ['name' => 'gift', 'name_fa' => 'هدیه', 'icon' => '🎀', 'type' => 'income', 'is_default' => true],
            ['name' => 'investment', 'name_fa' => 'سرمایه‌گذاری', 'icon' => '📈', 'type' => 'income', 'is_default' => true],
            ['name' => 'other_income', 'name_fa' => 'سایر درآمدها', 'icon' => '📌', 'type' => 'income', 'is_default' => true],
            ['name' => 'food', 'name_fa' => 'خوراک', 'icon' => '🍔', 'type' => 'expense', 'is_default' => true],
            ['name' => 'housing', 'name_fa' => 'مسکن', 'icon' => '🏠', 'type' => 'expense', 'is_default' => true],
            ['name' => 'transport', 'name_fa' => 'حمل و نقل', 'icon' => '🚗', 'type' => 'expense', 'is_default' => true],
            ['name' => 'entertainment', 'name_fa' => 'تفریح', 'icon' => '🎬', 'type' => 'expense', 'is_default' => true],
            ['name' => 'shopping', 'name_fa' => 'خرید', 'icon' => '🛍️', 'type' => 'expense', 'is_default' => true],
            ['name' => 'health', 'name_fa' => 'سلامت', 'icon' => '🏥', 'type' => 'expense', 'is_default' => true],
            ['name' => 'education', 'name_fa' => 'آموزش', 'icon' => '📚', 'type' => 'expense', 'is_default' => true],
            ['name' => 'bills', 'name_fa' => 'قبوض', 'icon' => '📄', 'type' => 'expense', 'is_default' => true],
            ['name' => 'clothing', 'name_fa' => 'پوشاک', 'icon' => '👕', 'type' => 'expense', 'is_default' => true],
            ['name' => 'other_expense', 'name_fa' => 'سایر هزینه‌ها', 'icon' => '📌', 'type' => 'expense', 'is_default' => true],
        ];
    }
}
