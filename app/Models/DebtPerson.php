<?php

namespace App\Models;

use App\Services\Encryption\FernetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebtPerson extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'shared_with_user_id',
        'share_status',
        'share_requested_at',
        'share_responded_at',
        'name',
        'phone',
        'notes',
        'notes_encrypted',
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'notes',
    ];

    protected $hidden = [
        'notes_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'share_requested_at' => 'datetime',
            'share_responded_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DebtTransaction::class);
    }

    public function getNotesAttribute(): ?string
    {
        $value = $this->attributes['notes_encrypted'] ?? null;

        return app(FernetService::class)->decrypt($value);
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes_encrypted'] = app(FernetService::class)->encrypt($value);
    }
}
