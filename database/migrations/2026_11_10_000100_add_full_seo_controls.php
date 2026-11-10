<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('seo_site_url')->default('https://regenbogen-duderstadt.de');
            $table->string('seo_title_suffix')->default('REGENBOGEN');
            $table->string('seo_default_description', 320)->default('Das Magazin der Grünen Untereichsfeld: aktuelle Artikel und Ausgaben aus Duderstadt und dem Untereichsfeld.');
            $table->string('seo_default_robots', 32)->default('index,follow');
            $table->string('seo_default_og_image')->nullable();
            $table->string('seo_publisher_name')->default('REGENBOGEN – Das Magazin der Grünen Untereichsfeld');
            $table->string('seo_publisher_url')->default('https://regenbogen-duderstadt.de');
            $table->string('seo_publisher_logo')->nullable();

            $table->string('seo_home_title')->default('Das grüne Magazin aus Duderstadt');
            $table->string('seo_home_description', 320)->default('Das grüne Magazin aus Duderstadt und dem Untereichsfeld: aktuelle Beiträge und Positionen online lesen und Ausgaben entdecken.');
            $table->string('seo_home_robots', 32)->nullable();
            $table->text('seo_home_canonical')->nullable();
            $table->string('seo_home_og_title')->nullable();
            $table->string('seo_home_og_description', 320)->nullable();
            $table->string('seo_home_og_image')->nullable();

            $table->string('seo_issues_title')->default('Ausgabenarchiv aus Duderstadt');
            $table->string('seo_issues_description', 320)->default('Alle Ausgaben des REGENBOGEN aus Duderstadt und dem Untereichsfeld. Entdecke die Hefte, lies Beiträge online oder lade die Ausgabe als PDF herunter.');
            $table->string('seo_issues_robots', 32)->nullable();
            $table->text('seo_issues_canonical')->nullable();
            $table->string('seo_issues_og_title')->nullable();
            $table->string('seo_issues_og_description', 320)->nullable();
            $table->string('seo_issues_og_image')->nullable();
        });

        foreach (['articles', 'pages', 'issues'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('seo_robots', 32)->nullable();
                $table->text('seo_canonical_url')->nullable();
                $table->string('og_title')->nullable();
                $table->string('og_description', 320)->nullable();
                $table->string('og_image')->nullable();
            });
        }

        DB::table('pages')
            ->whereIn('slug', ['impressum', 'datenschutz', 'datenschutzerklaerung'])
            ->update(['seo_robots' => 'noindex,nofollow']);
    }

    public function down(): void
    {
        foreach (['articles', 'pages', 'issues'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['seo_robots', 'seo_canonical_url', 'og_title', 'og_description', 'og_image']);
            });
        }

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'seo_site_url', 'seo_title_suffix', 'seo_default_description', 'seo_default_robots', 'seo_default_og_image',
                'seo_publisher_name', 'seo_publisher_url', 'seo_publisher_logo',
                'seo_home_title', 'seo_home_description', 'seo_home_robots', 'seo_home_canonical', 'seo_home_og_title', 'seo_home_og_description', 'seo_home_og_image',
                'seo_issues_title', 'seo_issues_description', 'seo_issues_robots', 'seo_issues_canonical', 'seo_issues_og_title', 'seo_issues_og_description', 'seo_issues_og_image',
            ]);
        });
    }
};
