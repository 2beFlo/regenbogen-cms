# REGENBOGEN CMS 1.5.5

Produktionsstand des Redaktionssystems für den **REGENBOGEN – Das Magazin der Grünen Untereichsfeld** auf Basis von **Laravel 13** und **Filament 5**.

## Funktionsumfang

- Artikel mit Entwurf → Freigabe → Veröffentlichung
- redaktioneller Qualitätscheck vor Freigabe/Veröffentlichung
- Versionierung und Wiederherstellung von Artikeln
- Veröffentlichungsprotokoll
- mehrere Autor*innen pro Artikel
- frei verwaltbare Rubriken
- Ausgaben mit Cover und PDF
- interne Ausgabenplanung mit Heftspiegel, Platzhaltern, Seitenanteilen und eigenem Printstatus
- statische CMS-Seiten und Website-Einstellungen
- Rollen **Autor*in**, **Redaktion**, **Administration**
- Passkeys und Authenticator-App; starke Anmeldung für Redaktion/Administration verpflichtend
- passwortloser Passkey-Login
- öffentliches E-Mail-Obfuscation gegen einfache Scraper
- responsives REGENBOGEN-Frontend ohne Node-Build auf dem Server

## Technische Anforderungen

- PHP 8.3+
- MySQL/MariaDB
- Composer
- Webserver mit DocumentRoot auf `public/`
- kein Node.js, Redis, Supervisor oder dauerhafter Queue-Worker erforderlich

Uploads landen direkt in `public/uploads/`; `php artisan storage:link` ist nicht erforderlich.

## Frische Produktivinstallation

Das Release-ZIP ist **ohne zusätzlichen Projekt-Unterordner** gepackt. Für `regenbogen-duderstadt.de` wird es direkt im Domain-`httpdocs` entpackt; der DocumentRoot zeigt anschließend auf `httpdocs/public`.

```bash
cp .env.example .env
# Datenbank-/Maildaten in .env eintragen
php composer.phar install --no-dev --optimize-autoloader
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/uploads public/uploads/editor
chmod -R ug+rwX storage bootstrap/cache public/uploads
php artisan key:generate
php artisan regenbogen:install
php artisan filament:assets
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Der letzte Befehl muss vor dem Go-live mit **PASS** enden.

Auf dem eingesetzten netcup-Setup **kein `php artisan optimize`** ausführen. Details stehen in `DEPLOY-NETCUP.md`.

## Wichtige Verzeichnisse

- `app/Filament/` – Redaktionsbackend
- `app/Models/` – Inhalts- und Redaktionsmodelle
- `resources/views/` – öffentliche Website und Filament-Anpassungen
- `public/css/regenbogen.css` – Frontend-CSS
- `public/uploads/` – redaktionelle Uploads
- `database/migrations/` – Datenbankschema
- `app/Console/Commands/InstallRegenbogen.php` – Erstinstallation
- `app/Console/Commands/QualityCheckRegenbogen.php` – Release-/Betriebsprüfung

## Release

**Version 1.5.5 – SEO-Hotfix**

Ergänzt frei pflegbare SEO-Titel und -Beschreibungen, Canonical- und Social-Metadaten, strukturierte Publisher-/Artikeldaten, Veröffentlichungsdaten, Alternativtexte sowie die URL-Normalisierung und eine crawlbare `robots.txt`.

**Version 1.5.4 – 5. September 2026**

Erster für die frische Produktivinstallation vorgesehener Release. Entwicklungs-Patchhistorie und Demo-Medien sind bewusst nicht Bestandteil des Produktionspakets.

Der Advanced Rich Editor enthält jetzt in Artikeln und Seiten einen Bilder-Button mit Media-Browser. Bilder können nach dem Upload wiederverwendet, mit Alternativtext versehen, verlinkt, gedreht und in der Größe angepasst werden. Die Dateien liegen im öffentlichen Verzeichnis `public/uploads/editor`.

PDF-Downloads der Ausgaben werden über einen eigenen Download-Endpunkt mit einem passenden Dateinamen ausgeliefert, zum Beispiel `regenbogen-sonderausgabe-kommunalwahl-2026.pdf`. Das funktioniert auch für bereits vorhandene Ausgaben mit älteren internen Dateinamen.

Die Seite `Unterstütze uns` bindet das Twingle-Spendenformular außerhalb des Rich-Text-Editors ein. Der Begleittext bleibt im Backend bearbeitbar; der notwendige JavaScript-Code kann nicht versehentlich durch die HTML-Bereinigung entfernt werden.

Das Frontend lädt keine Google Fonts mehr. Barlow Condensed, PT Sans und PT Serif werden lokal ausgeliefert. Rasterbilder werden über WebP-Varianten und `srcset` passend zur tatsächlichen Darstellungsgröße ausgeliefert; Bilder aus dem Rich-Text-Editor erhalten dieselbe Optimierung automatisch.


## iFrames / Einbettungen

Ab 1.1.5 werden zusätzlich versehentliche Zeilenumbrüche in iFrame-URLs vor der Sanitization entfernt. Im Quelltext eingefügte `<iframe>`-Elemente einschließlich ihres `src`-Attributs bleiben beim Wechsel zwischen Quelltext- und visueller Ansicht erhalten. Erlaubt werden nur die für iFrames benötigten Attribute; `script`, Event-Handler und `srcdoc` bleiben durch den HTML-Sanitizer gesperrt. iFrame-Quellen dürfen nur relative URLs sowie HTTP/HTTPS verwenden.
