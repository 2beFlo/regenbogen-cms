<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Author extends Model
{
    protected $fillable = ['name', 'slug', 'bio', 'email'];

    protected static function booted(): void
    {
        static::creating(function (Author $author): void {
            $author->slug = $author->slug ?: Str::slug($author->name);
        });
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withPivot('sort_order');
    }
}
