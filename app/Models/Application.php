<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'token', 'chats_count'];
    protected $hidden = ['id', 'updated_at'];

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }
}
