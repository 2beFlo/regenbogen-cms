<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Issue extends Model
{
    protected $fillable = ['title', 'slug', 'subtitle', 'cover_image', 'pdf_file', 'published_at', 'page_count', 'format', 'is_current', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['published_at' => 'date', 'is_current' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Issue $issue): void {
            $issue->slug = $issue->slug ?: Str::slug($issue->title);
        });

        static::saved(function (Issue $issue): void {
            if ($issue->is_current) {
                static::query()->whereKeyNot($issue->getKey())->where('is_current', true)->update(['is_current' => false]);
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderByDesc('featured')->orderBy('published_at');
    }

    public function pdfDownloadFilename(): string
    {
        return 'regenbogen-'.Str::slug($this->title ?: 'ausgabe').'.pdf';
    }
}
