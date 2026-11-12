<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IssueEntry extends Model
{
    protected $fillable = [
        'issue_id', 'article_id', 'title', 'content_type', 'page_start', 'page_offset', 'page_span',
        'placement_type', 'is_locked', 'planning_status', 'print_status', 'responsible_user_id', 'due_at', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'page_offset' => 'float',
            'page_span' => 'float',
            'is_locked' => 'boolean',
            'due_at' => 'date',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'issue_entry_author')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    protected function displayTitle(): Attribute
    {
        return Attribute::get(fn (): string => $this->article?->title ?: $this->title);
    }

    protected function displayAuthors(): Attribute
    {
        return Attribute::get(fn () => $this->article?->authors ?? $this->authors);
    }
}
