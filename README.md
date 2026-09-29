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
   und installieren. Der Installer legt zwei Apps an: **MAMP** (kostenlos)
   und **MAMP PRO** (kostenpflichtig, Testversion) - fuer dieses Projekt
   `/Applications/MAMP/MAMP.app` verwenden (nicht MAMP PRO).
2. MAMP starten, **Preferences**:
   - **Ports**: MAMP nutzt standardmaessig Port 8888 (Apache) / 8889
     (MySQL) - so ist `src/config.php` bereits eingestellt, keine Aenderung
     noetig. Wer stattdessen die Standard-Ports 80/3306 moechte, setzt in
     den Preferences das Haekchen *"Set Web & MySQL ports to 80 & 3306"*
     und passt `port` in `src/config.php` auf `3306` an.
   - **Apache**: *Document Root* auf den Ordner `public/` dieses Projekts
     stellen (also `.../Neue App/public`).
   - **PHP**: eine PHP-8.x-Version waehlen (getestet mit 8.3).
3. **Start Servers** klicken (Ampel wird gruen).
4. Im Browser `http://localhost:8888/` oeffnen -> leitet zu `/login.php`
   weiter. Datenbank und Tabellen werden beim ersten Aufruf automatisch
   angelegt.
5. Unter `/register.php` ein Benutzerkonto anlegen und gleich das erste
   Ziel erfassen.

### Admin-Bereich

- URL: `http://localhost:8888/admin/login.php`
- Zugang anlegen (es gibt keinen Standardzugang mehr):
  `/Applications/MAMP/bin/php/php8.3.30/bin/php bin/create-admin.php admin`
  (leer gelassenes Passwort = zufaelliges Passwort wird erzeugt und einmalig angezeigt).
- Admins, die noch das fruehere Standardpasswort haben, muessen beim naechsten
  Login ein neues Passwort (mind. 12 Zeichen) setzen.
- Unter *SMTP* die Mailserver-Zugangsdaten hinterlegen (inkl. Testmail-
  Button), unter *Mail-Vorlagen* die Texte anpassen, unter *Erinnerungen*
  den Inaktivitaets-Schwellenwert setzen und Erinnerungen manuell
  ausloesen oder per Cronjob automatisieren:

  ```
  0 8 * * *  /Applications/MAMP/bin/php/php8.3.30/bin/php \
             "/Users/anjajyotistiefel/Documents/Claude Apps/Neue App/cron/send_inactivity_reminders.php"
  ```

  (getestet und funktionsfaehig mit der oben installierten PHP-Version;
  bei einem Versions-Wechsel in MAMP den Pfad in `/Applications/MAMP/bin/php/`
  entsprechend anpassen).

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

## Sicherheits-Update (September 2026)

Die Datenbank-Aenderungen (`016_security.sql`) laufen beim naechsten Seitenaufruf
automatisch. Fuer bestehende Daten einmalig ausfuehren:

```bash
/Applications/MAMP/bin/php/php8.3.30/bin/php bin/migrate-files.php
```

Das verschiebt hochgeladene Bilder aus `public/uploads/` nach `storage/uploads/`
(danach nur noch mit Login ueber `/media.php` abrufbar) und lagert Bilder/PDFs
gespeicherter Visionen aus der Datenbank nach `storage/manifestations/` aus.

- **Login-Drosselung**: nach 5 Fehlversuchen pro E-Mail und IP (bzw. 30 pro IP)
  15 Minuten Sperre – fuer User-Login, Admin-Login und „Passwort vergessen“.
- **Passwort vergessen**: `/forgot_password.php` schickt einen 60 Minuten gueltigen
  Link (Vorlage „Passwort zuruecksetzen“ in der Administration).
- **Loeschen**: Beim Entfernen von Bildern, gespeicherten Visionen oder des ganzen
  Kontos werden die Dateien mitgeloescht, sobald sie nirgends mehr verwendet werden.
- **SMTP-Passwort** wird mit dem `app_key` verschluesselt gespeichert.
- **Visionboard** skaliert auf allen Bildschirmgroessen als Ganzes; „PDF oeffnen“
  zeigt nur eine Vorschau und legt keine neue gespeicherte Vision mehr an.
- **Migrationen** werden nur noch geprueft, wenn neue Dateien dazukommen
  (Merker in `storage/cache/`), und laufen unter einer Datenbank-Sperre.

## Live-Betrieb (Checkliste)

- Konfiguration ueber Umgebungsvariablen: `APP_URL` (mit `https://`), `APP_KEY`
  (`php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`), `DB_HOST`, `DB_PORT`,
  `DB_NAME`, `DB_USER`, `DB_PASS`. `APP_DEBUG` weglassen oder `0`.
- Ohne eigenen `app_key` startet die App im Live-Modus bewusst nicht.
- Fehler landen in `storage/logs/php-error.log`, Besucher sehen nur eine neutrale Seite.
- Eigener Datenbank-Benutzer statt `root`, nur mit Rechten auf `tagebuch_app`.
- `storage/` muss fuer PHP beschreibbar sein und darf nicht im Webroot liegen.
- Datenschutzerklaerung (`public/datenschutz.php`) ist eine Vorlage: alle
  `[Platzhalter]` ersetzen und rechtlich pruefen lassen.
