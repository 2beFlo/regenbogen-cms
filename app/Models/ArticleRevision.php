<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    protected $fillable = [
        'article_id', 'user_id', 'version_number', 'reason', 'snapshot',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function capture(Article $article, ?int $userId = null, string $reason = 'Gespeichert'): ?self
    {
        $article->loadMissing('authors');
        $snapshot = self::snapshotFor($article);
        $latest = $article->revisions()->latest('version_number')->first();

        if ($latest && $latest->snapshot === $snapshot) {
            return null;
        }

        return self::query()->create([
            'article_id' => $article->getKey(),
            'user_id' => $userId,
            'version_number' => ((int) $article->revisions()->max('version_number')) + 1,
            'reason' => $reason,
            'snapshot' => $snapshot,
        ]);
    }

    public static function snapshotFor(Article $article): array
    {
        return [
            'title' => $article->title,
            'card_title' => $article->card_title,
            'slug' => $article->slug,
            'teaser' => $article->teaser,
            'content' => $article->content,
            'hero_image' => $article->hero_image,
            'card_image' => $article->card_image,
            'image_notice' => $article->image_notice,
            'status' => $article->status?->value ?? $article->getRawOriginal('status'),
            'featured' => (bool) $article->featured,
            'published_at' => $article->published_at?->toIso8601String(),
            'rubric_id' => $article->rubric_id,
            'issue_id' => $article->issue_id,
            'author_ids' => $article->authors->pluck('id')->values()->all(),
        ];
    }

    public function restoreToArticle(): void
    {
        $article = $this->article;
        $snapshot = $this->snapshot;

        $article->fill([
            'title' => $snapshot['title'] ?? $article->title,
            'card_title' => $snapshot['card_title'] ?? null,
            'slug' => $snapshot['slug'] ?? $article->slug,
            'teaser' => $snapshot['teaser'] ?? '',
            'content' => $snapshot['content'] ?? '',
            'hero_image' => $snapshot['hero_image'] ?? null,
            'card_image' => $snapshot['card_image'] ?? null,
            'image_notice' => $snapshot['image_notice'] ?? null,
            'rubric_id' => $snapshot['rubric_id'] ?? null,
            'issue_id' => $snapshot['issue_id'] ?? null,
            'featured' => (bool) ($snapshot['featured'] ?? false),
            // Wiederherstellungen werden niemals automatisch veröffentlicht.
            'status' => \App\Enums\ArticleStatus::Draft->value,
            'published_at' => null,
            'review_requested_at' => null,
            'review_requested_by' => null,
            'review_message' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'review_feedback' => null,
        ]);
        $article->save();
        $article->authors()->sync($snapshot['author_ids'] ?? []);
    }
}
