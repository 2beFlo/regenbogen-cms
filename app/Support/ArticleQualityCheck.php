<?php

namespace App\Support;

use App\Models\Article;
use Illuminate\Support\Str;

class ArticleQualityCheck
{
    public static function run(Article $article): array
    {
        $article->loadMissing(['authors', 'rubric']);
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $article->content)) ?? '');

        $blockers = [];
        $warnings = [];
        $passed = [];

        self::check(filled($article->title), 'Überschrift vorhanden', 'Überschrift fehlt.', $passed, $blockers);
        self::check(filled($article->teaser), 'Vorspann vorhanden', 'Vorspann fehlt.', $passed, $blockers);
        self::check(mb_strlen($plainText) >= 100, 'Artikeltext vorhanden', 'Der Artikeltext ist zu kurz oder leer.', $passed, $blockers);
        self::check((bool) $article->rubric_id, 'Rubrik zugeordnet', 'Es ist keine Rubrik zugeordnet.', $passed, $blockers);

        if ($article->authors->isEmpty()) {
            $warnings[] = 'Keine Autor*innen zugeordnet; im Frontend wird ersatzweise das Benutzerkonto verwendet.';
        } else {
            $passed[] = 'Autor*innen zugeordnet';
        }

        if (! $article->hero_image) {
            $warnings[] = 'Kein Artikelbild hinterlegt.';
        } else {
            $passed[] = 'Artikelbild vorhanden';
        }

        if ($article->hero_image && blank($article->image_notice)) {
            $warnings[] = 'Zum Artikelbild fehlt ein Bildhinweis bzw. eine Quellen-/KI-Kennzeichnung.';
        }

        if (mb_strlen((string) $article->title) > 110) {
            $warnings[] = 'Die Überschrift ist sehr lang und kann in Karten ungünstig umbrechen.';
        }

        if (mb_strlen((string) $article->teaser) > 260) {
            $warnings[] = 'Der Vorspann ist sehr lang.';
        }

        if (mb_strlen($plainText) < 700) {
            $warnings[] = 'Der Artikel ist relativ kurz. Das kann bei Glossen gewollt sein.';
        }

        return compact('blockers', 'warnings', 'passed');
    }

    public static function hasBlockers(Article $article): bool
    {
        return count(self::run($article)['blockers']) > 0;
    }

    /**
     * @param  array<int, string>  $passed
     * @param  array<int, string>  $blockers
     */
    private static function check(bool $condition, string $success, string $failure, array &$passed, array &$blockers): void
    {
        if ($condition) {
            $passed[] = $success;

            return;
        }

        $blockers[] = $failure;
    }
}
