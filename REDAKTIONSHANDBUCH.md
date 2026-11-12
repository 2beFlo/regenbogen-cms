# REGENBOGEN – Kurzhandbuch fürs Backend

## Einloggen

`https://regenbogen-duderstadt.de/admin`

Nach dem Login zeigt das Dashboard vier große Wege:

- **Neuen Artikel schreiben**
- **Entwürfe weiterbearbeiten**
- **Aktuelle Ausgabe verwalten** (Redaktion)
- **Website ansehen**

## Einen Artikel schreiben

1. **Überschrift** eintragen.
2. **Kurzen Vorspann** schreiben.
3. **Rubrik** auswählen.
4. Eine oder mehrere **Autor*innen** auswählen.
5. **Artikeltext** im normalen Texteditor schreiben.
6. Optional **Artikelbild** hochladen und Bildhinweis ergänzen.
7. **Entwurf speichern**.
8. Über **Vorschau** kontrollieren.

Redakteur*innen können nicht versehentlich veröffentlichen. Das übernimmt die Rolle **Redaktion**.

## Mehrere Autor*innen

Im Feld **Autor*innen** einfach mehrere Namen auswählen. Die Reihenfolge kann später bei Bedarf noch erweitert werden.

## Ausgabe verwalten

Unter **Ausgaben**:

- Titel
- Cover
- PDF
- Erscheinungsdatum
- Seitenzahl
- Format
- **Als aktuelle Ausgabe anzeigen**

Wird eine neue Ausgabe als aktuell markiert, wird die bisherige automatisch zur normalen Archivausgabe.

## Ausgabenplanung und Heftspiegel

Die **Ausgabenplanung** ist eine interne Redaktionsseite. Die öffentliche Ausgabe und die dort verknüpften Onlineartikel bleiben davon getrennt. Der Heftplatz hält fest, was in einer Printausgabe erscheinen soll; er veröffentlicht keinen Artikel und ändert nicht dessen Zuordnung im Onlinearchiv.

### Ausgabe vorbereiten

1. Unter **Ausgabenplanung** über **Neue Ausgabe planen** eine neue Ausgabe anlegen oder eine vorhandene Ausgabe auswählen. Nach dem Anlegen öffnet sich direkt der neue Plan. Beim nächsten Öffnen ist die nächste noch nicht veröffentlichte Ausgabe mit geplantem Erscheinungstermin vorausgewählt.
2. Eine eindeutige **Ausgabennummer**, einen Arbeitstitel und bei Bedarf Redaktionsschluss sowie Drucktermin eintragen.
3. Die **Gesamtseitenzahl** festlegen. Sie ist frei wählbar; auch ungerade Zahlen sind erlaubt. Wird sie später verkleinert, blockieren belegte Seiten die Änderung. Erst nach dem Verschieben oder Lösen der betroffenen Heftplätze kann gespeichert werden. Eine Ausgabe mit Heftplätzen kann nicht gelöscht werden; verschiebe deren Einträge vorher in eine andere Ausgabe.
4. Im Heftspiegel wird Seite 1 als Titelseite geführt. Dort gibt es ausschließlich den Platzhalter **Titelseite**; CMS-Artikel werden nicht auf dem Cover eingeplant.

### Beiträge einplanen

- Über **Beitrag einplanen** einen Platzhalter erstellen. Ein CMS-Artikel ist dafür nicht erforderlich.
- Für einen vorhandenen Artikel im Formular den CMS-Artikel auswählen. Überschrift, Autor*innen und redaktioneller Artikelstatus werden dann aus dem Artikel angezeigt; die Printproduktion bleibt separat.
- Umfang in Viertelseiten-Schritten eintragen. Mehrseitige Beiträge beginnen auf der gewählten Startseite und laufen auf die folgenden Seiten weiter. Kleine Beiträge können sich eine Seite teilen, wenn Startseite und Seitenanteil so gesetzt sind, dass sie einander nicht überlappen.
- Ein Beitrag ohne feste Position bleibt unter **Noch nicht platziert** und reserviert dort weiterhin seinen Seitenumfang.
- Eine echte **Doppelseite** belegt genau zwei gegenüberliegende Seiten und beginnt auf der geraden linken Seite. Ein normaler zweitseitiger Text bleibt davon unterscheidbar.
- Auf der letzten Seite lässt sich bei Bedarf eine generische **Schlussseite** als Platzhalter anlegen. Sie wandert bei einer Änderung der Gesamtseitenzahl automatisch auf die neue letzte Seite; eine belegte Zielseite verhindert die Änderung.
- Der WerDi-Comic lässt sich als Inhaltsart „WerDi-Comic“ mit einer ganzen A5-Seite einplanen. Er ist nicht verpflichtend.
- Die Auswahl **CMS-Artikel** enthält nicht verplante Artikel sowie beim Bearbeiten den bereits zugeordneten Artikel. Titel, Rubrik und Autor*innen eines verknüpften Artikels werden aus dem CMS übernommen.
- Die **Startposition auf der Seite** wird nur gebraucht, wenn mehrere Beiträge dieselbe Seite teilen: `0` bedeutet Seitenanfang, `0,25` Viertelseite, `0,5` Seitenmitte und `0,75` Dreiviertelseite. Für den Anfang der Folgeseite die Startseite ändern. Als verantwortliche Person kann jedes Benutzerkonto ausgewählt werden.

Im Heftspiegel können Redaktionsmitglieder Beiträge ziehen und ablegen. Über **Liste** gibt es dieselben Seitenfelder für die manuelle Platzierung und Nutzung auf kleineren Bildschirmen. Sperren schützt einen festen Platz vor versehentlichem Verschieben; zum Ändern zuerst entsperren. Platzierte Beiträge lassen sich auch aus dem Spiegel lösen, ohne sie zu löschen.

Über die Listenansicht kann ein Beitrag in eine andere Ausgabe verschoben werden. Er erscheint dort zunächst unter **Noch nicht platziert**; Artikelverknüpfung, Autor*innen, Umfang, Notizen und Status bleiben erhalten. Ein vorhandener Artikel wird dadurch nicht aus seinem Onlinearchiv verschoben.

### Status und Rechte

Der redaktionelle Stand eines verknüpften Artikels stammt weiterhin aus dem Artikelworkflow. Platzhalter verwenden den Planungsstand **Idee**, **In Planung**, **Recherche**, **In Arbeit** oder **Redaktionell fertig**. Der Printstatus läuft unabhängig davon von **Vorgesehen** über **Bereit für den Satz**, **Im Satz**, **Gesetzte Fassung vorhanden** und **Schlusskorrektur** bis zur **Druckfreigabe**. Eine Druckfreigabe veröffentlicht nichts online.

Redaktion und Administration sehen und bearbeiten alle Ausgaben. Autor*innen sehen nur Heftplätze, die ihnen direkt, über den Artikel oder über ihr ausdrücklich mit einem Benutzerkonto verknüpftes Autor*innenprofil zugeordnet sind. Interne Notizen und Bearbeitungsaktionen bleiben ihnen verborgen. Die Profilverknüpfung wird in **Autor*innen** über „Redakteur*innen-Konto“ gepflegt; eine Zuordnung wird nicht anhand eines gleichen Namens oder einer E-Mail-Adresse erraten.

Die Kapazitätsübersicht trennt platzierte Seiten, noch nicht platzierte Reservierungen und freie Kapazität. Wenn Reservierungen den verfügbaren Gesamtumfang überschreiten, wird die Überbuchung angezeigt.

## Normale Seiten

Unter **Seiten** werden z. B. gepflegt:

- Redaktion
- Kontakt
- Impressum
- Datenschutz

Es gibt keine Layout-Bausteine, die versehentlich die Website zerlegen können. Die Redaktion pflegt nur Inhalt und Sichtbarkeit.

## Website-Einstellungen

Dort stehen bewusst nur dauerhafte Angaben:

- Kopfzeile
- Über-uns-Block
- Footer-Claim
- Herausgeberin
- Redaktion
- Kontakt
- Erscheinungsweise

## Rollen

**Redakteur*in:** Artikelentwürfe.  
**Redaktion:** veröffentlicht und pflegt redaktionelle Stammdaten.  
**Administration:** verwaltet zusätzlich Benutzer*innen.
