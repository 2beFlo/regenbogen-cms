# REGENBOGEN CMS 1.0.0

**Release: 4. September 2026**

Erster Produktionsrelease. Basis ist der auf Stage gehärtete Stand 0.3.3.

Für 1.0.0 wurde das Paket für eine frische Produktivinstallation bereinigt: kein zusätzlicher ZIP-Oberordner, keine Demo-Medien, keine Entwicklungs-Patchhistorie und keine ungenutzte TinyEditor-Abhängigkeit. Installer, `.env.example`, README und netcup-Deploymentanleitung sind auf den neuen Produktionsaufbau direkt in `/regenbogen-duderstadt.de/httpdocs/` abgestimmt.

Vor Freigabe muss `php artisan regenbogen:quality --strict` auf dem Zielsystem grün durchlaufen.
