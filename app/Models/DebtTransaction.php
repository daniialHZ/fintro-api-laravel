<?php

namespace App\Models;

use App\Services\Encryption\FernetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'debt_person_id',
        'date',
        'type',
        'amount',
        'signed_amount',
        'status',
        'requested_by_user_id',
        'approved_by_user_id',
        'responded_at',
        'description',
        'description_encrypted',
        'created_at',
    ];

    protected $appends = [
        'description',
    ];

    protected $hidden = [
        'description_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'float',
            'signed_amount' => 'float',
            'created_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DebtPerson::class, 'debt_person_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function getDescriptionAttribute(): ?string
    {
        $value = $this->attributes['description_encrypted'] ?? null;

        return app(FernetService::class)->decrypt($value);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description_encrypted'] = app(FernetService::class)->encrypt($value);
    }
}
