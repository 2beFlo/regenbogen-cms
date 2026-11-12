<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Author extends Model
{
    protected $fillable = ['name', 'slug', 'bio', 'email', 'user_id'];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issueEntries(): BelongsToMany
    {
        return $this->belongsToMany(IssueEntry::class, 'issue_entry_author')->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
