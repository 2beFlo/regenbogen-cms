# REGENBOGEN CMS 1.0.0 – Produktivdeployment auf netcup Webhosting 2000

Diese Anleitung ist auf die frische Installation unter `regenbogen-duderstadt.de` zugeschnitten. Es werden keine Stage-Daten übernommen.

## 1. Dateien

Das Release direkt nach

```text
/regenbogen-duderstadt.de/httpdocs/
```

entpacken. Das ZIP enthält direkt `app/`, `public/`, `resources/`, `artisan` usw. – **keinen zusätzlichen `regenbogen-cms`-Ordner**.

Der DocumentRoot der Domain muss auf

```text
/regenbogen-duderstadt.de/httpdocs/public
```

zeigen.

## 2. open_basedir

Im netcup-WCP für PHP `open_basedir` die Variante mit **`{WEBSPACEROOT}`** verwenden. `{DOCROOT}` reicht nicht, weil Laravel oberhalb von `public/` auf `vendor/`, `storage/`, `bootstrap/` und `.env` zugreifen muss.

## 3. Neue Produktionsdatenbank und .env

Eine neue MySQL/MariaDB-Datenbank anlegen. Danach:

```bash
cd /regenbogen-duderstadt.de/httpdocs
cp .env.example .env
```

Mindestens DB- und Maildaten kontrollieren. Vorgesehen sind:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://regenbogen-duderstadt.de
APP_LOCALE=de
APP_FALLBACK_LOCALE=de

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

`APP_KEY` bleibt zunächst leer und wird für Prod neu erzeugt.

## 4. Runtime-Verzeichnisse

```bash
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache \
         public/uploads \
         public/uploads/editor

chmod -R ug+rwX storage bootstrap/cache public/uploads
find storage bootstrap/cache public/uploads -type d -exec chmod 775 {} \;
find storage bootstrap/cache public/uploads -type f -exec chmod 664 {} \;
```

Keine pauschalen `777`-Rechte setzen.

## 5. Composer

Liegt Composer nicht global vor, `composer.phar` wie auf Stage im Projektstamm bereitstellen. Danach:

```bash
php composer.phar install --no-dev --optimize-autoloader
```

## 6. Installation

```bash
php artisan key:generate
php artisan regenbogen:install
php artisan filament:assets
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Der Installer legt Schema, Grundeinstellungen, Standardseiten und die erste Administration an. **Es werden keine Demo-Artikel oder Demo-Ausgaben angelegt.**

Der Qualitätslauf muss vor dem Go-live mit `PASS – keine kritischen Probleme gefunden.` enden.

## 7. Wichtig: kein php artisan optimize

Auf dem verwendeten netcup-Webhosting sehen SSH-PHP und Web-PHP denselben Webspace unter unterschiedlichen absoluten Pfaden. Ein per SSH erzeugter Konfigurationscache kann deshalb ungültige Pfade enthalten.

Daher:

```bash
php artisan optimize:clear
```

verwenden, aber **nicht** `php artisan optimize`.

## 8. Erster Browser-Test

Nach erfolgreichem Quality-Gate:

- Startseite und `/admin/login` aufrufen.
- Als erste Administration anmelden.
- Passkey und/oder Authenticator-App einrichten; für Redaktion/Administration ist starke Anmeldung verpflichtend.
- Einen Autor*innen-Testaccount anlegen und Rollenrechte prüfen.
- Artikel als Entwurf erstellen, zur Freigabe geben, zurückgeben/veröffentlichen und Vorschau testen.
- Bild-Upload, 4:3-Kartenbild, Ausgabe/PDF, Rubriken, CMS-Seiten und Website-Einstellungen prüfen.
- Versionshistorie und Veröffentlichungsprotokoll kontrollieren.

Passkeys werden auf der Produktivdomain neu registriert. Stage-Passkeys werden nicht übernommen.

## 9. Updates/Backups

Vor Updates mindestens Datenbank und `public/uploads/` sichern. Danach üblicherweise:

```bash
php composer.phar install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan filament:assets
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Erst bei grünem Quality-Gate wieder freigeben.

### SEO-Hotfix 1.5.5

Vor dem Hotfix Datenbank, `public/uploads/`, `.env` und `public/.htaccess` sichern. Das Hotfix-ZIP enthält nur geänderte Dateien mit Pfaden relativ zur Projektwurzel und kann daher direkt in `httpdocs/` entpackt werden.

```bash
cd /regenbogen-duderstadt.de/httpdocs
cp public/.htaccess public/.htaccess.backup-$(date +%Y%m%d%H%M%S)
unzip -o regenbogen-cms-1.5.5-seo-hotfix.zip
php artisan migrate --force
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Das Update ergänzt optionale SEO-Felder; die Migration ist erforderlich. Es ändert keine Artikel-, Ausgaben- oder Uploaddaten. `public/.htaccess` vereinheitlicht `www`, HTTP/HTTPS und End-Slashes, sofern die Domain `.htaccess` über Apache verarbeitet. Falls netcup Anfragen direkt durch nginx bedient, dieselben Weiterleitungen in den Apache-/nginx-Einstellungen der Domain setzen und die `curl -I`-Prüfung aus den Patchnotizen ausführen.
