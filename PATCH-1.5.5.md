# REGENBOGEN CMS 1.5.5 – SEO-Hotfix

## Enthalten

- Optional pflegbare SEO-Titel und -Beschreibungen für Artikel, Ausgaben und CMS-Seiten.
- Sinnvolle individuelle Fallback-Titel und -Beschreibungen aus vorhandenen redaktionellen Inhalten.
- Canonical-, Open-Graph- und Twitter-Metadaten auf öffentlichen Seiten.
- `NewsMediaOrganization`, `WebSite` und `NewsArticle` als JSON-LD.
- Veröffentlichungsdatum im Artikel als sichtbares `<time>`-Element und als strukturierte Daten.
- Alt-Text für Artikelbilder; ohne Pflege wird die Artikelüberschrift als Fallback verwendet.
- `robots.txt` blockiert Kontakt- und Rechtsseiten nicht mehr. Diese bleiben mit `noindex,follow` aus dem Index und außerhalb der Sitemap.
- `public/.htaccess` leitet `www` und HTTP auf `https://regenbogen-duderstadt.de` um und entfernt abschließende Slashes.

Redaktionelle SEO-Titel, Beschreibungen und Bildalternativen können in den jeweiligen Artikel-, Ausgabe- und Seitenformularen ergänzt werden. Bestehende Datensätze bleiben gültig; leere Felder verwenden automatische Fallbacks.

## Update

Vorher Datenbank, `public/uploads/`, `.env` und `public/.htaccess` sichern. ZIP nach `/regenbogen-duderstadt.de/httpdocs/` hochladen und dort ausführen:

```bash
cd /regenbogen-duderstadt.de/httpdocs
cp public/.htaccess public/.htaccess.backup-$(date +%Y%m%d%H%M%S)
unzip -o regenbogen-cms-1.5.5-seo-hotfix.zip
php artisan migrate --force
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Auf diesem netcup-Webhosting kann nginx vor Apache sitzen oder Anfragen direkt ausliefern. Die `.htaccess`-Regeln greifen nur, wenn Apache sie verarbeitet. Nach dem Update kontrollieren:

```bash
curl -sSI http://www.regenbogen-duderstadt.de/artikel/beispiel
curl -sSI https://www.regenbogen-duderstadt.de/artikel/beispiel
curl -sSI https://regenbogen-duderstadt.de/artikel/beispiel/
curl -sSI https://regenbogen-duderstadt.de/artikel/beispiel
```

Die ersten drei Antworten sollen mit Status `301` zur letzten URL führen; die letzte soll `200` liefern. Wenn das nicht zutrifft, Weiterleitungen in den Webserver-Einstellungen für nginx/Apache bei netcup setzen. Den Beispielpfad durch einen tatsächlich veröffentlichten Artikel ersetzen.

Eine Migration ist enthalten und muss mit `php artisan migrate --force` ausgeführt werden. Das CMS-Abnahme-Gate bleibt `php artisan regenbogen:quality --strict`.
