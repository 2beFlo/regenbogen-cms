<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $aboutExists = DB::table('pages')->where('slug', 'ueber-uns')->exists();

        if (! $aboutExists) {
            DB::table('pages')->insert([
                'title' => 'Über uns',
                'slug' => 'ueber-uns',
                'content' => '<p>Der REGENBOGEN ist das Magazin der Grünen Untereichsfeld. Hier kann das Selbstverständnis des Magazins ausführlicher beschrieben werden.</p>',
                'status' => 'published',
                'show_in_navigation' => true,
                'show_in_footer' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('pages')->where('slug', 'ueber-uns')->update([
                'title' => 'Über uns',
                'show_in_navigation' => true,
                'show_in_footer' => true,
                'sort_order' => 30,
                'updated_at' => $now,
            ]);
        }

        DB::table('pages')->where('slug', 'redaktion')->update([
            'show_in_navigation' => false,
            'show_in_footer' => true,
            'sort_order' => 40,
            'updated_at' => $now,
        ]);

        DB::table('pages')->where('slug', 'kontakt')->update(['sort_order' => 50, 'updated_at' => $now]);
        DB::table('pages')->where('slug', 'impressum')->update(['sort_order' => 60, 'updated_at' => $now]);
        DB::table('pages')->where('slug', 'datenschutz')->update(['sort_order' => 70, 'updated_at' => $now]);

        DB::table('site_settings')->where('about_link_url', '/redaktion')->update([
            'about_link_url' => '/ueber-uns',
            'updated_at' => $now,
        ]);

        DB::table('site_settings')->where('about_link_label', 'Mehr über den REGENBOGEN →')->update([
            'about_link_label' => 'Mehr über den REGENBOGEN',
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $now = now();

        DB::table('pages')->where('slug', 'redaktion')->update([
            'show_in_navigation' => true,
            'sort_order' => 30,
            'updated_at' => $now,
        ]);
        DB::table('pages')->where('slug', 'kontakt')->update(['sort_order' => 40, 'updated_at' => $now]);
        DB::table('pages')->where('slug', 'impressum')->update(['sort_order' => 50, 'updated_at' => $now]);
        DB::table('pages')->where('slug', 'datenschutz')->update(['sort_order' => 60, 'updated_at' => $now]);

        DB::table('site_settings')->where('about_link_url', '/ueber-uns')->update([
            'about_link_url' => '/redaktion',
            'updated_at' => $now,
        ]);
        DB::table('site_settings')->where('about_link_label', 'Mehr über den REGENBOGEN')->update([
            'about_link_label' => 'Mehr über den REGENBOGEN →',
            'updated_at' => $now,
        ]);
    }
};
