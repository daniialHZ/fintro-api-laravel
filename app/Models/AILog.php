<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AILog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_logs';

    protected $fillable = [
        'user_id',
        'prompt_type',
        'prompt_text',
        'response_text',
        'parsed_response',
        'success',
        'error_message',
        'response_time_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
