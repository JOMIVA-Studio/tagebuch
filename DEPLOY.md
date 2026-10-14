# Mein Tagebuch online bringen (tagebuch.jomiva.com)

Die App läuft auf dem Tophost-Hosting von jomiva.com als eigene Subdomain.
Sie braucht eine **eigene (Sub-)Domain**, weil alle Links vom Domain-Root ausgehen (`/login.php`, `/assets/…`).

## Einmalig einrichten (im Tophost-Kundenbereich)

1. **Subdomain anlegen:** `tagebuch.jomiva.com`, Dokumentenverzeichnis (Document Root) = `tagebuch/public`
   (also der Ordner `public` *innerhalb* der App – nicht der App-Ordner selbst).
2. **PHP-Version** für die Subdomain auf 8.1 oder neuer stellen; Erweiterungen `mbstring`, `openssl`, `gd`, `pdo_mysql` aktiv.
3. **SSL-Zertifikat** (Let's Encrypt) für die Subdomain aktivieren.
4. **MySQL-Datenbank + eigenen Datenbank-Benutzer** anlegen (Name, Benutzer, Passwort notieren).
5. **Dateien hochladen** – entweder
   - automatisch über GitHub (siehe unten), oder
   - von Hand per FTP: alles ausser `src/config.php`, `storage/`, `archiv-bilder/`, `.git`, `.claude` nach `tagebuch.jomiva.com/` kopieren.
6. **Konfiguration auf dem Server:** auf dem Server `tagebuch.jomiva.com/src/config.php` aus `src/config.example.php` kopieren und eintragen:
   - `app_url` = `https://tagebuch.jomiva.com`
   - `app_key` = zufälliger Schlüssel (am Mac: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`)
   - Datenbank-Zugangsdaten aus Schritt 4, `debug` = `false`
   - Ordner `tagebuch.jomiva.com/storage/` muss für PHP beschreibbar sein (Rechte 755/775).
7. **Admin anlegen** (per SSH/Terminal oder dem Cronjob-Dialog von Tophost einmalig ausführen):
   `php bin/create-admin.php admin` – das erzeugte Passwort wird einmal angezeigt und muss beim ersten Login geändert werden.
8. Im **Admin-Bereich** (`/admin/login.php`): SMTP-Zugang für den Mailversand eintragen und Mail-Vorlagen prüfen.
9. **Cronjob** für Erinnerungs-Mails (täglich 08:00): `php /home/BENUTZER/tagebuch.jomiva.com/cron/send_inactivity_reminders.php`.

## Automatisches Hochladen über GitHub (optional)

1. Neues GitHub-Repository anlegen und diesen Ordner dorthin pushen.
2. Im Repository unter *Settings → Secrets → Actions* das Secret `FTP_PASSWORD` mit dem FTP-Passwort von Tophost anlegen.
3. Bei jedem Push auf `main` lädt `.github/workflows/deploy.yml` die App nach `tagebuch.jomiva.com/` hoch.
   `src/config.php` und `storage/` bleiben unberührt auf dem Server.

## Danach prüfen

- `https://tagebuch.jomiva.com/login.php` lädt, `http://…` leitet auf `https://` um.
- Registrieren, einloggen, Eintrag speichern, Bild im Visionboard hochladen.
- `https://tagebuch.jomiva.com/src/config.php` und `/storage/` müssen **nicht** erreichbar sein (404).
- Fehler stehen in `storage/logs/php-error.log`.
