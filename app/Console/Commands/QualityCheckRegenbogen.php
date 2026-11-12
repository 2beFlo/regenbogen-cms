<?php

namespace App\Console\Commands;

use App\Http\Controllers\SeoController;
use App\Models\Article;
use App\Models\SiteSetting;
use App\Support\ArticleQualityCheck;
use App\Support\SeoMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QualityCheckRegenbogen extends Command
{
    protected $signature = 'regenbogen:quality {--strict : Warnungen ebenfalls als Fehler behandeln}';

    protected $description = 'Prüft REGENBOGEN-CMS, Authentifizierung, Übersetzungen und zentrale Laufzeitvoraussetzungen.';

    /** @var array<int, string> */
    private array $failures = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->info('REGENBOGEN Qualitätsprüfung');
        $this->newLine();

        $this->checkRuntime();
        $this->checkSecurity();
        $this->checkPasskeys();
        $this->checkDatabase();
        $this->checkIssuePlanning();
        $this->checkEditorialWorkflow();
        $this->checkRichTextIframes();
        $this->checkSeo();
        $this->checkFilesystem();

        $this->newLine();

        foreach ($this->warnings as $warning) {
            $this->warn('WARNUNG: '.$warning);
        }

        foreach ($this->failures as $failure) {
            $this->error('FEHLER: '.$failure);
        }

        if ($this->failures === [] && (! $this->option('strict') || $this->warnings === [])) {
            $this->newLine();
            $this->info('PASS – keine kritischen Probleme gefunden.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('FAIL – '.count($this->failures).' Fehler, '.count($this->warnings).' Warnungen.');

        return self::FAILURE;
    }

    private function checkRuntime(): void
    {
        $this->line('• Laufzeit');

        $this->expect(config('app.locale') === 'de', 'APP_LOCALE ist nicht de.');
        $this->expect(config('app.fallback_locale') === 'de', 'APP_FALLBACK_LOCALE ist nicht de.');
        $this->expect(config('app.debug') === false, 'APP_DEBUG muss auf Stage/Produktion false sein.');
        $this->expect(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL muss HTTPS verwenden.');

        if (app()->configurationIsCached()) {
            $this->warnings[] = 'Die Laravel-Konfiguration ist gecacht. Auf diesem netcup-Setup kann ein per SSH erzeugter Config-Cache falsche absolute Pfade enthalten; bitte php artisan optimize:clear verwenden.';
        }
    }

    private function checkSecurity(): void
    {
        $this->line('• Sitzungen und Anmeldung');

        $this->expect(config('session.driver') === 'database', 'SESSION_DRIVER sollte database sein.');
        $this->expect((bool) config('session.encrypt'), 'SESSION_ENCRYPT muss true sein.');
        $this->expect((bool) config('session.secure'), 'SESSION_SECURE_COOKIE muss true sein.');
        $this->expect(config('session.same_site') === 'lax', 'SESSION_SAME_SITE sollte lax sein.');
        $this->expect(class_exists(\App\Http\Middleware\EnforcePrivilegedMfa::class), 'EnforcePrivilegedMfa fehlt.');

        $uploadRules = config('livewire.temporary_file_upload.rules', []);
        $this->expect(is_array($uploadRules) && in_array('max:122880', $uploadRules, true), 'Livewire-Uploadlimit muss auf mindestens 120 MB gesetzt sein.');

        if (class_exists(\App\Models\User::class)) {
            $this->expect(method_exists(\App\Models\User::class, 'hasStrongAuthenticationConfigured'), 'User::hasStrongAuthenticationConfigured() fehlt.');
        }
    }

    private function checkPasskeys(): void
    {
        $this->line('• Passkeys / MFA');

        $this->expect(class_exists(\AdriaanZon\FilamentPasskeys\FilamentPasskeysPlugin::class), 'adriaanzon/filament-passkeys ist nicht installiert.');
        $this->expect(class_exists(\AdriaanZon\FilamentPasskeys\PasskeyAuthentication::class), 'PasskeyAuthentication fehlt.');
        $this->expect(trait_exists(\Laravel\Passkeys\PasskeyAuthenticatable::class), 'laravel/passkeys: PasskeyAuthenticatable-Trait fehlt.');
        $this->expect(interface_exists(\Laravel\Passkeys\Contracts\PasskeyUser::class), 'laravel/passkeys: PasskeyUser-Contract fehlt.');

        $localFile = resource_path('lang/vendor/filament-passkeys/de/passkeys.php');
        $this->expect(is_file($localFile), 'Deutsche Passkey-Sprachdatei fehlt: resources/lang/vendor/filament-passkeys/de/passkeys.php');

        $criticalKeys = [
            'filament-passkeys::passkeys.login.button',
            'filament-passkeys::passkeys.login.heading',
            'filament-passkeys::passkeys.management.heading',
            'filament-passkeys::passkeys.management.enabled',
            'filament-passkeys::passkeys.management.disabled',
            'filament-passkeys::passkeys.management.columns.name',
            'filament-passkeys::passkeys.management.columns.last_used',
            'filament-passkeys::passkeys.management.default_name',
            'filament-passkeys::passkeys.management.never_used',
            'filament-passkeys::passkeys.management.add',
            'filament-passkeys::passkeys.registration.heading',
            'filament-passkeys::passkeys.authentication.button',
            'filament-passkeys::passkeys.challenge.button',
        ];

        foreach ($criticalKeys as $key) {
            $translated = Lang::get($key, [], 'de');
            $this->expect(
                is_string($translated) && $translated !== $key && ! str_starts_with($translated, 'filament-passkeys::'),
                'Fehlende oder rohe deutsche Übersetzung: '.$key,
            );
        }

        $vendorFile = base_path('vendor/adriaanzon/filament-passkeys/resources/lang/en/passkeys.php');

        if (is_file($vendorFile) && is_file($localFile)) {
            $vendor = require $vendorFile;
            $local = require $localFile;

            if (is_array($vendor) && is_array($local)) {
                $vendorKeys = array_keys(Arr::dot($vendor));
                $localKeys = array_keys(Arr::dot($local));
                $missing = array_values(array_diff($vendorKeys, $localKeys));

                foreach ($missing as $key) {
                    $this->failures[] = 'Passkey-Übersetzung fehlt gegenüber installierter Paketversion: filament-passkeys::passkeys.'.$key;
                }
            }
        } else {
            $this->warnings[] = 'Die englische Passkey-Sprachdatei des installierten Plugins konnte nicht für den vollständigen Schlüsselvergleich gelesen werden.';
        }

        $passkeyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains(strtolower((string) $route->uri()), 'passkey') || str_contains(strtolower((string) $route->getName()), 'passkey'));

        $this->expect($passkeyRoutes->isNotEmpty(), 'Keine Passkey-Routen registriert.');

        $loginRoutes = $passkeyRoutes->filter(fn ($route): bool => str_contains(strtolower((string) $route->uri()), 'login') || str_contains(strtolower((string) $route->getName()), 'login'));
        if ($loginRoutes->isEmpty()) {
            $this->warnings[] = 'Keine eindeutig als Login benannte Passkey-Route gefunden. Passwortloser Passkey-Login bitte im Browser prüfen.';
        }
    }

    private function checkDatabase(): void
    {
        $this->line('• Datenbank');

        try {
            foreach (['users', 'sessions', 'passkeys', 'articles', 'article_revisions', 'publication_events', 'rubrics', 'issues', 'issue_entries', 'issue_entry_author'] as $table) {
                $this->expect(Schema::hasTable($table), 'Datenbanktabelle fehlt: '.$table);
            }
        } catch (Throwable $exception) {
            $this->failures[] = 'Datenbankprüfung fehlgeschlagen: '.$exception->getMessage();
        }
    }

    private function checkIssuePlanning(): void
    {
        $this->line('• Ausgabenplanung');

        try {
            $this->expect(Schema::hasColumns('issues', [
                'issue_code', 'work_title', 'planned_publication_date', 'editorial_deadline', 'print_date',
                'page_count', 'production_status', 'planning_notes',
            ]), 'Ausgabenplanungsspalten fehlen in issues.');
            $this->expect(Schema::hasColumns('issue_entries', [
                'issue_id', 'article_id', 'title', 'content_type', 'page_start', 'page_offset', 'page_span',
                'placement_type', 'is_locked', 'planning_status', 'print_status', 'responsible_user_id', 'due_at', 'internal_notes',
            ]), 'Kernspalten für Heftplätze fehlen.');
            $this->expect(Schema::hasColumns('authors', ['user_id']), 'Die explizite Verknüpfung zwischen Autor*innenprofilen und Benutzerkonten fehlt.');
        } catch (Throwable $exception) {
            $this->failures[] = 'Schema-Prüfung für Ausgabenplanung fehlgeschlagen: '.$exception->getMessage();
        }

        $this->expect(Route::has('filament.admin.pages.issue-planning'), 'Interne Filament-Seite für die Ausgabenplanung fehlt.');
        $planningRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_ends_with(trim((string) $route->uri(), '/'), 'issue-planning'));
        $this->expect(
            $planningRoutes->isNotEmpty() && $planningRoutes->every(fn ($route): bool => str_starts_with((string) $route->uri(), 'admin/')),
            'Die Ausgabenplanung darf nur unter dem authentifizierten /admin-Bereich erreichbar sein.',
        );
        $this->expect(class_exists(\App\Services\IssuePlanningService::class), 'IssuePlanningService fehlt.');
        $this->expect(class_exists(\App\Support\IssuePlanning\FlatplanCalculator::class), 'FlatplanCalculator fehlt.');
        $this->expect(class_exists(\App\Support\IssuePlanning\IssuePlanningAccess::class), 'IssuePlanningAccess fehlt.');
    }


    private function checkEditorialWorkflow(): void
    {
        $this->line('• Redaktionsworkflow');

        try {
            $result = ArticleQualityCheck::run(new Article([
                'title' => 'Qualitätsprüfung',
                'teaser' => 'Automatischer Laufzeittest des Artikel-Qualitätschecks.',
                'content' => str_repeat('Testtext ', 20),
            ]));

            $this->expect(
                isset($result['blockers'], $result['warnings'], $result['passed'])
                    && is_array($result['blockers'])
                    && is_array($result['warnings'])
                    && is_array($result['passed']),
                'ArticleQualityCheck liefert kein gültiges Ergebnis.',
            );
        } catch (Throwable $exception) {
            $this->failures[] = 'Artikel-Qualitätscheck ist nicht lauffähig: '.$exception->getMessage();
        }
    }

    private function checkRichTextIframes(): void
    {
        $this->line('• Rich-Text / Bilder / iFrames');

        foreach ([
            app_path('Filament/Resources/Articles/ArticleResource.php'),
            app_path('Filament/Resources/Pages/CmsPageResource.php'),
        ] as $editorSourcePath) {
            $editorSource = (string) file_get_contents($editorSourcePath);
            $this->expect(
                str_contains($editorSource, "['image', 'table', 'embed']")
                    && str_contains($editorSource, "->fileAttachmentsDisk('uploads')")
                    && str_contains($editorSource, "->fileAttachmentsDirectory('editor')")
                    && str_contains($editorSource, "->mediaLibraryDirectory('editor')")
                    && ! str_contains($editorSource, '->mediaLibrary(false)'),
                'Der Rich-Text-Editor ist nicht vollständig für den Bilder-Media-Browser konfiguriert.',
            );
        }

        $this->expect(
            class_exists(\App\Filament\RichContent\IframeRichContentPlugin::class),
            'iFrame-Rich-Content-Plugin fehlt.',
        );
        $this->expect(
            class_exists(\App\Filament\RichContent\IframeNode::class),
            'iFrame-TipTap-Node fehlt.',
        );
        $this->expect(
            is_file(resource_path('js/dist/filament/rich-content-plugins/iframe-rich-content.js')),
            'iFrame-TipTap-JavaScript fehlt.',
        );

        $phpNodeSource = (string) file_get_contents(app_path('Filament/RichContent/IframeNode.php'));
        $jsNodeSource = (string) file_get_contents(resource_path('js/dist/filament/rich-content-plugins/iframe-rich-content.js'));
        $this->expect(
            str_contains($phpNodeSource, "['tag' => 'iframe']")
                && str_contains($jsNodeSource, "{ tag: 'iframe' }"),
            'iFrame-Parser muss das Element ohne src-Selektor erkennen, damit sourceCode-Roundtrips die URL nicht verlieren.',
        );

        try {
            $iframeProbe = '<iframe src="https://statistic.twingle.de/index.php?module=CoreAdminHome&amp;action=optOut&amp;language=de'.PHP_EOL.'&amp;backgroundColor=&amp;fontColor=&amp;fontSize=16px&amp;fontFamily=" title="Datenschutz-Opt-out" width="100%" height="200" loading="lazy"></iframe>';
            $iframeSanitized = \App\Support\RichContentSanitizer::sanitize($iframeProbe);

            $this->expect(
                str_contains($iframeSanitized, '<iframe')
                    && str_contains($iframeSanitized, 'src="https://statistic.twingle.de/index.php?module=CoreAdminHome&amp;action=optOut&amp;language=de&amp;'),
                'HTML-Sanitizer entfernt iFrames oder deren src-Attribut.',
            );
            $this->expect(
                ! str_contains($iframeSanitized, 'srcdoc='),
                'HTML-Sanitizer lässt das unerwünschte srcdoc-Attribut auf iFrames zu.',
            );
        } catch (Throwable $exception) {
            $this->failures[] = 'iFrame-Sanitizer-Laufzeittest fehlgeschlagen: '.$exception->getMessage();
        }
    }

    private function checkFilesystem(): void
    {
        $this->line('• Dateisystem / Assets');

        $this->expect(is_writable(storage_path()), 'storage/ ist nicht schreibbar.');
        $this->expect(is_writable(base_path('bootstrap/cache')), 'bootstrap/cache ist nicht schreibbar.');
        $this->expect(is_file(public_path('css/filament/filament/app.css')), 'Filament-CSS fehlt; php artisan filament:assets ausführen.');
        $this->expect(is_file(public_path('assets/regenbogen-mark.svg')), 'REGENBOGEN-Sonnenblumen-SVG fehlt.');
    }

    private function checkSeo(): void
    {
        $this->line('• SEO / Crawler');

        $this->expect(Route::has('seo.robots'), 'robots.txt-Route fehlt.');
        $this->expect(Route::has('seo.sitemap'), 'XML-Sitemap-Route fehlt.');
        $this->expect(Route::has('issues.download'), 'Ausgaben-Download-Route fehlt.');
        $this->expect(Route::has('images.variant'), 'Responsive-Bildroute fehlt.');

        $frontendCss = public_path('css/regenbogen.css');
        $this->expect(is_file($frontendCss), 'Frontend-CSS fehlt.');
        if (is_file($frontendCss)) {
            $cssSource = (string) file_get_contents($frontendCss);
            $this->expect(! str_contains($cssSource, 'fonts.googleapis.com'), 'Frontend lädt weiterhin Google Fonts.');
            $this->expect(str_contains($cssSource, 'fonts/regenbogen/'), 'Lokale Frontend-Fonts fehlen im CSS.');
        }

        $imageSupport = app_path('Support/ResponsiveImages.php');
        $this->expect(is_file($imageSupport), 'Responsive-Bildunterstützung fehlt.');
        $this->expect(
            function_exists('imagecreatefromstring') && function_exists('imagewebp'),
            'PHP-GD mit WebP-Unterstützung fehlt.',
        );

        $supportView = resource_path('views/pages/partials/twingle-support.blade.php');
        $this->expect(is_file($supportView), 'Twingle-Unterstütze-uns-Seite fehlt.');
        if (is_file($supportView)) {
            $supportSource = (string) file_get_contents($supportView);
            $this->expect(
                str_contains($supportSource, 'spenden.twingle.de/embed/')
                    && str_contains($supportSource, 'twingle-public-embed-'),
                'Twingle-Einbindung der Unterstütze-uns-Seite ist unvollständig.',
            );
        }

        try {
            $settings = SiteSetting::current();
            $robotsLines = preg_split('/\r?\n/', app(SeoController::class)->robots()->getContent()) ?: [];
            $expectedSitemap = 'Sitemap: '.SeoMetadata::siteUrl($settings).'/sitemap.xml';
            $this->expect(in_array($expectedSitemap, $robotsLines, true), 'robots.txt verweist nicht auf die konfigurierte Sitemap.');
        } catch (Throwable $exception) {
            $this->failures[] = 'robots.txt-Konfiguration konnte nicht geprüft werden: '.$exception->getMessage();
        }
    }

    private function expect(bool $condition, string $failure): void
    {
        if (! $condition) {
            $this->failures[] = $failure;
        }
    }
}
