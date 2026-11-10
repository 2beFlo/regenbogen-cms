<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CmsPage extends Model
{
    protected $table = 'pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'seo_title',
        'seo_description',
        'seo_robots',
        'seo_canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'status',
        'show_in_navigation',
        'show_in_footer',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'show_in_navigation' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CmsPage $page): void {
            $page->slug = $page->slug ?: Str::slug($page->title);

            if (in_array($page->slug, ['impressum', 'datenschutz', 'datenschutzerklaerung'], true) && $page->seo_robots === null) {
                $page->seo_robots = 'noindex,nofollow';
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isProtectedSystemPage(): bool
    {
        return in_array($this->slug, [
            'impressum',
            'datenschutz',
            'datenschutzerklaerung',
            'ueber-uns',
            'redaktion',
        ], true);
    }
}
