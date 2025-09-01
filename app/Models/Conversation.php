<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'question',
        'answer',
        'context',
        'response_type',
    ];
}
