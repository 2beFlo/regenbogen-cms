# REGENBOGEN CMS 1.1.1

## Behoben

- `<iframe>`-Elemente, die über die Quelltextansicht des Editors eingefügt werden, werden nicht mehr beim Wechsel zurück in die visuelle Ansicht bzw. beim Speichern verworfen.
- Artikel und CMS-Seiten verwenden dafür einen eigenen TipTap-iFrame-Node.
- Im visuellen Editor wird das iFrame absichtlich als ruhiger Platzhalter dargestellt, damit eingebettete Seiten nicht den Editor übernehmen oder Klicks abfangen.
- Die öffentliche Ausgabe lässt iFrames durch den Filament/Symfony-Sanitizer, beschränkt die Quelle aber auf relative URLs sowie HTTP/HTTPS. `srcdoc`, Event-Handler und `<script>` bleiben verboten.
- `regenbogen:quality --strict` enthält jetzt einen Laufzeittest, der sicherstellt, dass ein Matomo-ähnliches Opt-out-iFrame den Sanitizer überlebt.

## Deployment

```bash
cd /regenbogen-duderstadt.de/httpdocs
unzip -o regenbogen-cms-1.1.1-hotfix.zip
php artisan filament:assets
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Keine Migration und kein Composer-Lauf erforderlich.
