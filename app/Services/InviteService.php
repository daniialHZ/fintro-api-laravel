<?php

namespace App\Services;

use App\Models\InviteCode;
use Carbon\Carbon;

class InviteService
{
    public function generateRandomCode(int $length = 8): string
    {
        return substr(strtoupper(str_replace(['-', '_'], ['X', 'Y'], base64_encode(random_bytes($length)))), 0, $length);
    }

    public function createInviteCode(array $data): InviteCode
    {
        return InviteCode::query()->create([
            'code' => $data['code'],
            'created_by' => $data['created_by'] ?? null,
            'max_uses' => $data['max_uses'] ?? 1,
            'current_uses' => 0,
            'expires_at' => ! empty($data['expires_days']) ? Carbon::now()->addDays((int) $data['expires_days']) : null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);
    }

    public function validateInviteCode(string $code): array
    {
        $invite = InviteCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $invite) {
            return ['valid' => false, 'message' => 'کد دعوت نامعتبر است'];
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            return ['valid' => false, 'message' => 'کد دعوت منقضی شده است'];
        }

        if ($invite->current_uses >= $invite->max_uses) {
            return ['valid' => false, 'message' => 'کد دعوت قبلا استفاده شده است'];
        }

        return ['valid' => true, 'message' => 'کد دعوت معتبر است', 'code' => $invite->code];
    }

    public function useInviteCode(string $code, int $userId): bool
    {
        $invite = InviteCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $invite || ($invite->expires_at && $invite->expires_at->isPast()) || $invite->current_uses >= $invite->max_uses) {
            return false;
        }

        $invite->current_uses += 1;
        $invite->used_by = $userId;
        $invite->used_at = now();
        if ($invite->current_uses >= $invite->max_uses) {
            $invite->is_active = false;
        }
        $invite->save();

        return true;
    }
}
