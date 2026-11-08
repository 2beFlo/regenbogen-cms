<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CmsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallRegenbogen extends Command
{
    protected $signature = 'regenbogen:install';

    protected $description = 'Richtet das REGENBOGEN-CMS ein und legt den ersten Admin-Zugang an.';

    public function handle(): int
    {
        $this->info('REGENBOGEN CMS 1.5.4 wird eingerichtet …');

        if (glob(database_path('migrations/*_create_passkeys_table.php')) === []) {
            Artisan::call('vendor:publish', ['--tag' => 'passkeys-migrations']);
            $this->line(Artisan::output());
        }

        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        SiteSetting::current();
        $this->seedPages();

        $name = $this->ask('Name der Administration', 'REGENBOGEN Administration');
        $email = $this->ask('E-Mail-Adresse');
        $password = $this->secret('Passwort (mindestens 10 Zeichen)');

        if (! $email || ! $password || mb_strlen($password) < 10) {
            $this->error('Bitte E-Mail und ein Passwort mit mindestens 10 Zeichen angeben.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => UserRole::Administration],
        );

        Artisan::call('optimize:clear');

        $this->newLine();
        $this->info('Grundinstallation abgeschlossen.');
        $this->line('1. php artisan filament:assets');
        $this->line('2. php artisan optimize:clear');
        $this->line('3. php artisan regenbogen:quality --strict');
        $this->newLine();
        $this->info('Anmeldung: '.rtrim((string) config('app.url'), '/').'/admin');
        $this->warn('Administration/Redaktion müssen nach der ersten Anmeldung Passkey oder Authenticator-App einrichten.');

        return self::SUCCESS;
    }

    private function seedPages(): void
    {
        $pages = [
            ['title' => 'Über uns', 'slug' => 'ueber-uns', 'content' => '<p>Der REGENBOGEN ist das Magazin der Grünen Untereichsfeld. Hier kann das Selbstverständnis des Magazins ausführlicher beschrieben werden.</p>', 'show_in_navigation' => true, 'show_in_footer' => true, 'sort_order' => 30],
            ['title' => 'Unterstütze uns', 'slug' => 'unterstuetze-uns', 'content' => '<p>Der REGENBOGEN lebt von Menschen, die hinschauen, einordnen und sich einmischen. Wenn du unsere Arbeit unterstützen möchtest, kannst du hier direkt spenden.</p>', 'show_in_navigation' => true, 'show_in_footer' => true, 'sort_order' => 25],
            ['title' => 'Redaktion', 'slug' => 'redaktion', 'content' => '<p>Hier stellt sich die REGENBOGEN-Redaktion vor.</p>', 'show_in_navigation' => false, 'show_in_footer' => true, 'sort_order' => 40],
            ['title' => 'Kontakt', 'slug' => 'kontakt', 'content' => '<p>Hier findest du Kontaktmöglichkeiten zur REGENBOGEN-Redaktion.</p>', 'show_in_navigation' => false, 'show_in_footer' => true, 'sort_order' => 50],
            ['title' => 'Impressum', 'slug' => 'impressum', 'content' => '<h2>Impressum</h2><p>Bitte die rechtlich erforderlichen Angaben ergänzen.</p>', 'show_in_navigation' => true, 'show_in_footer' => true, 'sort_order' => 60],
            ['title' => 'Datenschutz', 'slug' => 'datenschutz', 'content' => '<h2>Datenschutz</h2><p>Bitte die Datenschutzerklärung ergänzen.</p>', 'show_in_navigation' => false, 'show_in_footer' => true, 'sort_order' => 70],
        ];

        foreach ($pages as $page) {
            CmsPage::query()->updateOrCreate(['slug' => $page['slug']], $page + ['status' => 'published']);
        }
    }
}
