<?php

namespace App\Models;

use App\Services\Encryption\FernetService;
use Illuminate\Database\Eloquent\Model;

class Wealth extends Model
{
    protected $table = 'wealth';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'amount',
        'quantity',
        'unit',
        'purchase_date',
        'purchase_price',
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
            'amount' => 'float',
            'quantity' => 'float',
            'purchase_price' => 'float',
            'purchase_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
