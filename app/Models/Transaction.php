<?php

namespace App\Models;

use App\Services\Encryption\FernetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'amount',
        'source_id',
        'category_id',
        'description',
        'description_encrypted',
        'created_at',
    ];

    protected $appends = [
        'description',
        'source_name',
        'category_name',
    ];

    protected $hidden = [
        'description_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function getSourceNameAttribute(): ?string
    {
        return $this->source?->name_fa;
    }

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category?->name_fa;
    }
}
