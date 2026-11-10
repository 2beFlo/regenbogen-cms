<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_title', 'masthead_kicker', 'strapline', 'footer_claim', 'about_title', 'about_text',
        'about_link_label', 'about_link_url', 'publisher', 'editorial', 'contact_label', 'contact_url', 'frequency',
        'seo_site_url', 'seo_title_suffix', 'seo_default_description', 'seo_default_robots', 'seo_default_og_image',
        'seo_publisher_name', 'seo_publisher_url', 'seo_publisher_logo',
        'seo_home_title', 'seo_home_description', 'seo_home_robots', 'seo_home_canonical', 'seo_home_og_title', 'seo_home_og_description', 'seo_home_og_image',
        'seo_issues_title', 'seo_issues_description', 'seo_issues_robots', 'seo_issues_canonical', 'seo_issues_og_title', 'seo_issues_og_description', 'seo_issues_og_image',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'about_text' => 'Der REGENBOGEN ist das Magazin der Grünen Untereichsfeld. Er erzählt von Duderstadt und dem Untereichsfeld, ordnet kommunale Entscheidungen ein und macht politische Zusammenhänge sichtbar.',
            'publisher' => "BÜNDNIS 90/DIE GRÜNEN\nOV Untereichsfeld\nWestertorstraße 30\n37115 Duderstadt",
            'editorial' => "Florian Lillpopp\nJochen Mitschke",
            'contact_label' => 'gruene-untereichsfeld.de',
            'contact_url' => 'https://gruene-untereichsfeld.de',
        ]);
    }
}
