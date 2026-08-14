<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $fillable = ['username', 'password_hash', 'display_name', 'is_active', 'last_login_at'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }
}
