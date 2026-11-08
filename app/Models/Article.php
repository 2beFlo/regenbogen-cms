<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'issue_id', 'rubric_id', 'created_by', 'title', 'card_title', 'slug', 'category', 'teaser', 'content',
        'hero_image', 'card_image', 'image_alt', 'image_notice', 'seo_title', 'seo_description', 'status', 'featured', 'published_at',
        'review_requested_at', 'review_requested_by', 'review_message', 'reviewed_at', 'reviewed_by', 'review_feedback',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'featured' => 'boolean',
            'published_at' => 'datetime',
            'review_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article): void {
            $article->slug = $article->slug ?: Str::slug($article->title);
        });

        static::saving(function (Article $article): void {
            if ($article->rubric_id) {
                $article->category = Rubric::query()->find($article->rubric_id)?->name ?: $article->category;
            } elseif ($article->category) {
                $article->rubric_id = Rubric::query()->where('name', $article->category)->value('id');
            }
        });

        static::updated(function (Article $article): void {
            if (app()->runningInConsole()) {
                return;
            }

            $eventType = null;
            $summary = null;

            if ($article->wasChanged('status')) {
                if ($article->status === ArticleStatus::Published) {
                    $eventType = 'published';
                    $summary = $article->published_at?->isFuture()
                        ? 'Veröffentlichung geplant'
                        : 'Artikel veröffentlicht';
                } elseif ($article->getRawOriginal('status') === ArticleStatus::Published->value) {
                    $eventType = 'unpublished';
                    $summary = 'Veröffentlichung zurückgenommen';
                }
            } elseif ($article->status === ArticleStatus::Published && $article->wasChanged([
                'title', 'teaser', 'content', 'hero_image', 'card_image', 'rubric_id', 'issue_id', 'featured', 'published_at',
            ])) {
                $eventType = 'published_updated';
                $summary = 'Veröffentlichter Artikel geändert';
            }

            if (! $eventType) {
                return;
            }

            $changed = collect($article->getChanges())
                ->except(['updated_at'])
                ->keys()
                ->values()
                ->all();

            PublicationEvent::query()->create([
                'article_id' => $article->getKey(),
                'user_id' => auth()->id(),
                'event_type' => $eventType,
                'summary' => $summary,
                'changes' => ['fields' => $changed],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->orderByDesc('version_number');
    }

    public function reviewRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getRubricNameAttribute(): string
    {
        return $this->rubric?->name ?: $this->category ?: 'Ohne Rubrik';
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->card_title ?: $this->title;
    }

    public function getAuthorLineAttribute(): string
    {
        $names = $this->authors->pluck('name')->all();
        if (count($names) === 0) {
            return $this->creator?->name ?? 'REGENBOGEN Redaktion';
        }
        if (count($names) === 1) {
            return $names[0];
        }
        $last = array_pop($names);
        return implode(', ', $names).' und '.$last;
    }
}
