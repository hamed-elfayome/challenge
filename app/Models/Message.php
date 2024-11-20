<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    protected $fillable = ['number', 'body'];
    protected $hidden = ['id', 'chat_id', 'updated_at'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
