<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Rubric extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Rubric $rubric): void {
            $rubric->slug = $rubric->slug ?: Str::slug($rubric->name);
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
