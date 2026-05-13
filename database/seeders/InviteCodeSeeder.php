<?php

namespace Database\Seeders;

use App\Models\InviteCode;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InviteCodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->codes() as $code) {
            InviteCode::query()->firstOrCreate(
                ['code' => $code],
                [
                    'created_by' => null,
                    'used_by' => null,
                    'used_at' => null,
                    'expires_at' => Carbon::now()->addDays(30),
                    'max_uses' => 1,
                    'current_uses' => 0,
                    'is_active' => true,
                    'notes' => 'Seeded invite code',
                ]
            );
        }
    }

    private function codes(): array
    {
        return [
            'FINTRO01KKStshS',
            'FINTRO02LLUDGmn',
            'FINTRO03AlkdHJN',
            'FINTRO04dSdfSwe',
            'FINTRO05jdyweLU',
            'FINTRO06JdiKLhN',
            'FINTRO07YeIiisd',
            'FINTRO08YDJJdll',
            'FINTRO09kksuemJ',
            'FINTRO10sdASfer',
        ];
    }
}
