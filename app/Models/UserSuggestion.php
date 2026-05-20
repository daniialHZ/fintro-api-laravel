<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSuggestion extends Model
{
    protected $fillable = [
        'user_id',
        'page',
        'message',
        'status',
    ];
}
