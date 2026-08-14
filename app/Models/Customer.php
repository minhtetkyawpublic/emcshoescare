<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    protected $fillable = ['phone', 'password_hash', 'full_name', 'address', 'is_active', 'last_login_at'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
