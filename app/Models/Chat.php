<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    /** @use HasFactory<\Database\Factories\ChatFactory> */
    use HasFactory;

    protected $fillable = ['application_id', 'number', 'messages_count'];
    protected $hidden = ['id', 'application_id', 'updated_at'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
