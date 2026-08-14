<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePackage extends Model
{
    protected $table = 'packages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_ks' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
