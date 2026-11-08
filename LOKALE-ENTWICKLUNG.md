# Lokale Entwicklung

## Ausgangsbasis

Der Quellstand stammt aus `regenbogen-cms-1.5.4.zip`. Die SHA-256-Prüfsumme wurde gegen Übergabe und Prüfsummendatei geprüft. Der unveränderte Import ist Commit `eebfad0` auf `main`.

Das Archiv enthielt weder `vendor/` noch `composer.lock` oder eine Testsuite. Die lokale `composer.lock` pinnt deshalb die beim Setup aufgelösten Pakete. Aktuell sind Laravel 13.40.0, Filament 5.11.2, Livewire 4.4.8, der Advanced Rich Editor 1.8.0 und Filament Passkeys 0.2.3 installiert. Die Übergabe nennt als zuletzt bekannte Produktionsstände Laravel 13.30.1, Filament 5.7.8 und Livewire 4.4.3. Das sind historische Angaben; vor einer produktionsbezogenen Änderung oder Veröffentlichung muss der tatsächlich eingesetzte Stand separat geklärt werden. Die alte Auflösung wurde von Composer wegen bekannter Sicherheits-Advisories abgelehnt. `composer audit --locked` meldete für den lokalen Lock keine bekannten Advisories.

## Lokale Laufzeit

- PHP 8.5 mit PDO SQLite, GD und WebP-Unterstützung
- SQLite unter `database/database.sqlite`; keine Produktionsdaten
- `.env` mit `APP_ENV=local`, Debug-Modus, Loopback-URL, Datei-Sessions und Mailer `log`
- lokales Administrationskonto `local-admin@example.test`; das zufällige Passwort liegt nur in `storage/app/private/local-admin.txt` (Dateimodus `0600`, von Git ignoriert). Die Rolle Administration erfordert beim Anmelden weiterhin MFA.
- Filament- und Editor-Assets werden aus `vendor/` lokal nach `public/` veröffentlicht und von Git ignoriert.

Die lokale Datenbank enthält nur das CMS-Schema, die vom Installer angelegten Standardseiten/-einstellungen und das lokale Administrationskonto. Der lokale Webserver soll ausschließlich an `127.0.0.1` gebunden werden.

## Starten

Die aktuelle Arbeitskopie ist bereits eingerichtet. Zum Starten:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Danach ist das Frontend unter `http://127.0.0.1:8000` und die Redaktion unter `http://127.0.0.1:8000/admin` erreichbar.

Für eine frische lokale Kopie:

```bash
composer install
cp .env.local.example .env
mkdir -p database storage/app/private
touch database/database.sqlite
php artisan key:generate
php artisan regenbogen:install
php artisan filament:assets
php artisan optimize:clear
```

`regenbogen:install` fragt nach dem ersten lokalen Administrationskonto. Keine Produktionszugänge oder Produktionsdaten in diese lokale Installation übernehmen.

## Orientierung im Code

- `routes/web.php`: öffentliche Artikelseiten, geschützte Vorschau, Ausgaben/PDF, SEO-Routen und zuletzt die CMS-Seiten-Catch-all-Route.
- `app/Filament/Resources/`: Admin-Formulare und Tabellen für Artikel, Ausgaben, Seiten, Autor*innen, Rubriken, Benutzer*innen und Veröffentlichungsprotokoll.
- `app/Filament/Resources/Articles/Pages/`: Artikelaktionen und Workflow. Neue Artikel starten serverseitig als Entwurf; Einreichen, Zurückgeben, Veröffentlichen, Zurücknehmen und Wiederherstellen haben getrennte Aktionen.
- `app/Models/Article.php`, `ArticleRevision.php` und `PublicationEvent.php`: Status, unveränderliche Snapshots und Veröffentlichungsereignisse.
- `app/Support/ArticleQualityCheck.php`: blockierende Kriterien und redaktionelle Warnungen.
- `app/Support/RichContentSanitizer.php` und `app/Support/EmailProtector.php`: öffentliche Artikel-/Seitenausgabe; Roh-HTML wird erst beim Rendern bereinigt, E-Mail-Adressen werden clientseitig geschützt.
- `app/Http/Controllers/ImageVariantController.php` und `ResponsiveImages.php`: lokale Uploads und abrufbasierte WebP-Varianten mit Original-Fallback.
- `app/Providers/Filament/AdminPanelProvider.php` und `EnforcePrivilegedMfa.php`: Panel, Rollen-/Anmeldeintegration und verpflichtende MFA für Redaktion/Administration.

## Prüfgrenze

Beim Setup wurden Composer-Abhängigkeiten installiert, das lokale SQLite-Schema über den CMS-Installer angelegt, Filament-Assets veröffentlicht, Caches geleert und Artisan-Umgebung/Routen geladen. Es wurden keine fachlichen Tests oder Browserinteraktionen ausgeführt. Im übergebenen Archiv ist keine automatisierte Testsuite enthalten.
