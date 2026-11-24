<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'title', 'type', 'config', 'position', 'is_enabled'])]
class HomepageBlock extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
