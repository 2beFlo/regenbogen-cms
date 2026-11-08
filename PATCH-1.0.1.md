# REGENBOGEN CMS 1.0.1

Hotfix für den Produktionsstart.

- Behebt den 500-Fehler beim Qualitätscheck/Veröffentlichen: `ArticleQualityCheck::check()` war im 1.0.0-Stand nicht implementiert.
- Erweitert `regenbogen:quality --strict` um einen echten Laufzeittest des Artikel-Qualitätschecks, damit dieser Fehler künftig vom Release-Gate erkannt wird.
- `regenbogen:install` veröffentlicht die Laravel-Passkey-Migration bei einer Frischinstallation automatisch, sofern sie noch nicht im Projekt vorhanden ist.

## Update einer bestehenden 1.0.0-Installation

```bash
unzip -o regenbogen-cms-1.0.1-hotfix.zip
php artisan optimize:clear
php artisan regenbogen:quality --strict
```

Keine Datenbankmigration ist für dieses Hotfix erforderlich.
