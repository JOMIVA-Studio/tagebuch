# Mein Tagebuch

Persoenliche Tagebuch-Webapp mit Benutzerverwaltung, Kalender, zentral vom
Admin gepflegten Fragen, freien Ziel-Trackern und E-Mail-Erinnerungen.
PHP + MySQL, kein Framework, kein Composer noetig.

## Funktionsumfang

- **Registrierung/Login** pro User, Daten sind immer an den eingeloggten
  User gekoppelt.
- **Profil**: Passwort aendern, Konto inkl. aller Daten unwiderruflich
  loeschen, Erinnerungs-Mails an/aus.
- **Tagebuch**: Kalenderansicht, pro Tag ein Eintrag mit den zentralen,
  vom Admin gepflegten Fragen sowie einem Freitextfeld.
- **Ziele**: User legen eigene Ziele mit Wunsch-Haeufigkeit an (mindestens
  eines ist immer erforderlich) und tracken periodisch den Status.
- **Admin-Bereich** (`/admin/`): komplett separater, session-unabhaengiger
  Login (eigene Tabelle `admins`, eigenes Session-Cookie). Verwaltet den
  Fragen-Katalog, alle Mail-Vorlagen, SMTP-Zugangsdaten und den
  Inaktivitaets-Schwellenwert fuer Erinnerungsmails.
- **Datenbank aktualisiert sich selbst**: Beim Aufruf legt die App die
  Datenbank an, falls sie fehlt, und wendet automatisch alle offenen
  Migrationen aus `database/migrations/` an. Es sind nie manuelle SQL-
  Schritte noetig, auch nicht bei spaeteren Schema-Aenderungen.
- **Responsive**: Mobile-first CSS, funktioniert auf Handy, Tablet und
  Desktop.
- **Design/Logo**: Farben aus dem Logo (Gruen/Violett/Magenta), Layout
  angelehnt an die vorgegebene Referenz (farbige Kopf-Bloecke, Badges,
  Karten, dunkler Footer). Das Logo selbst ist als CSS/SVG-Monogramm
  nachgebaut (Ring + "A" in Schreibschrift + Wortmarke) - falls du die
  Original-Bilddateien pixelgenau willst, leg sie einfach unter
  `public/assets/img/` ab und ersetze die `<span class="logo-ring">…`
  Stellen in `public/partials/header.php` / `footer.php` sowie
  `public/admin/partials/header.php` durch `<img>`-Tags.

## Lokales Setup (MAMP)

Auf diesem Mac (Intel) unterstuetzt Homebrew keine Installation mehr,
deshalb kommt **MAMP** zum Einsatz (bringt Apache, PHP und MySQL fertig
mit, grafischer Installer):

1. MAMP herunterladen: https://www.mamp.info/en/downloads/ (Intel-x86-Paket)
   und installieren.
2. MAMP starten, **Preferences**:
   - **Ports**: Haekchen bei *"Set Web & MySQL ports to 80 & 3306"*
     setzen (damit `src/config.php` ohne Anpassung funktioniert).
   - **Apache**: *Document Root* auf den Ordner `public/` dieses Projekts
     stellen (also `.../Neue App/public`).
   - **PHP**: eine PHP-8.x-Version waehlen.
3. **Start Servers** klicken (Ampel wird gruen).
4. Im Browser `http://localhost/` oeffnen -> leitet zu `/login.php` weiter.
   Datenbank und Tabellen werden beim ersten Aufruf automatisch angelegt.
5. Unter `/register.php` ein Benutzerkonto anlegen und gleich das erste
   Ziel erfassen.

### Admin-Bereich

- URL: `http://localhost/admin/login.php`
- Standardzugang: **admin** / **admin123**
- Bitte direkt nach dem ersten Login unter *Passwort* aendern.
- Unter *SMTP* die Mailserver-Zugangsdaten hinterlegen (inkl. Testmail-
  Button), unter *Mail-Vorlagen* die Texte anpassen, unter *Erinnerungen*
  den Inaktivitaets-Schwellenwert setzen und Erinnerungen manuell
  ausloesen oder per Cronjob automatisieren:

  ```
  0 8 * * *  /Applications/MAMP/bin/php/php8.x.x/bin/php \
             "/Users/anjajyotistiefel/Documents/Neue App/cron/send_inactivity_reminders.php"
  ```

  (genaue PHP-Version im Ordner `/Applications/MAMP/bin/php/` nachsehen).

## Konfiguration

`src/config.php` ist lokal und **nicht** in Git (siehe `.gitignore`).
Vorlage: `src/config.example.php`. Auf einem anderen Rechner einfach
kopieren und Zugangsdaten eintragen.

## Projektstruktur

```
public/            Webroot (Document Root in MAMP)
  admin/           Admin-Bereich (eigener Login, eigene Session)
  partials/        Kopf-/Fusszeile der User-Seiten
  assets/          CSS, Bilder, Favicon
src/               Anwendungscode, NICHT im Webroot (Sicherheit)
database/
  migrations/      SQL-Migrationsdateien, werden automatisch ausgefuehrt
cron/              Skript fuer den Erinnerungs-Cronjob
```

## Git

Lokales Repository (`git init`), noch ohne Remote. `.gitignore` schliesst
`src/config.php` (lokale Zugangsdaten) sowie `.DS_Store`/Logs aus.
