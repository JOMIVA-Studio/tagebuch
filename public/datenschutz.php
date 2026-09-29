<?php
require __DIR__ . '/../src/bootstrap.php';

// VORLAGE nach Schweizer Datenschutzgesetz (revDSG). Angaben in [eckigen Klammern] ergänzen und
// vor dem Live-Gang rechtlich prüfen lassen, v. a. wenn auch Personen aus der EU die App nutzen (DSGVO).
$pageTitle = 'Datenschutz';
require __DIR__ . '/partials/header.php';
?>

<article class="card legal-page">
  <h2>Datenschutzerklärung</h2>
  <p class="small text-muted">Stand: [Datum]</p>

  <h3>1. Verantwortliche Stelle</h3>
  <p>[Vorname Nachname], [Strasse Nr.], [PLZ Ort], Schweiz — E-Mail: [kontakt@…]</p>

  <h3>2. Welche Daten wir bearbeiten</h3>
  <ul>
    <li><strong>Konto:</strong> Name, E-Mail-Adresse, Passwort (nur als sicherer Hash gespeichert), freiwillig das Geschlecht, Zeitpunkt der letzten Aktivität.</li>
    <li><strong>Deine Einträge:</strong> Tagebuch, Ziele, Routinen, Visionboard (inkl. hochgeladener Bilder), Wasser- und Schlaf-Tracker.</li>
    <li><strong>Zyklusdaten:</strong> Wenn du den Zyklus-Tracker nutzt, speichern wir die von dir erfassten Zyklustage. Das sind <strong>Gesundheitsdaten und damit besonders schützenswerte Personendaten</strong>. Wir erfassen sie nur, wenn du sie selbst einträgst, und nutzen sie ausschliesslich, um dir deinen Zyklus und die passenden Tipps anzuzeigen.</li>
    <li><strong>Technische Daten:</strong> IP-Adresse bei Anmeldeversuchen (Schutz vor Missbrauch, nach spätestens 24 Stunden gelöscht), Server-Protokolle des Hosting-Anbieters.</li>
    <li><strong>Cookies:</strong> Nur ein technisch notwendiges Sitzungs-Cookie, damit du angemeldet bleibst. Keine Werbe- oder Analyse-Cookies.</li>
  </ul>

  <h3>3. Wer deine Daten sieht</h3>
  <p>Deine Einträge, Bilder und Zyklusdaten sind nur für dich sichtbar, wenn du angemeldet bist. Hochgeladene Bilder werden nur an dein eigenes, angemeldetes Konto ausgeliefert. Wir verkaufen keine Daten und geben sie nicht zu Werbezwecken weiter.</p>
  <p>Schriften und alle anderen Inhalte werden direkt von unserem Server geladen; beim Seitenaufruf werden keine Daten an Google oder andere Drittanbieter übermittelt. Folgende Dienstleister bearbeiten Daten in unserem Auftrag:</p>
  <ul>
    <li>Hosting: [Name des Hosting-Anbieters, Land]</li>
    <li>E-Mail-Versand: [Name des E-Mail-Anbieters, Land]</li>
  </ul>

  <h3>4. E-Mails</h3>
  <p>Wir schicken dir E-Mails zur Registrierung, zum Zurücksetzen des Passworts und – wenn du das nicht im Profil abschaltest – Erinnerungen bei längerer Inaktivität.</p>

  <h3>5. Aufbewahrung und Löschung</h3>
  <p>Deine Daten bleiben gespeichert, solange dein Konto besteht. Löschst du dein Konto im Profil, werden alle Einträge, Zyklusdaten, hochgeladenen Bilder und gespeicherten Visionen endgültig gelöscht.</p>

  <h3>6. Deine Rechte</h3>
  <p>Du kannst jederzeit Auskunft über deine Daten verlangen, sie berichtigen oder löschen lassen. Schreib uns dazu an [kontakt@…]. Du kannst dich ausserdem beim Eidgenössischen Datenschutz- und Öffentlichkeitsbeauftragten (EDÖB) beschweren.</p>

  <h3>7. Sicherheit</h3>
  <p>Passwörter werden nur als Hash gespeichert, Zugangsdaten für den E-Mail-Versand verschlüsselt, die Verbindung ist per HTTPS verschlüsselt, und wiederholte Fehlversuche beim Anmelden werden automatisch gebremst.</p>
</article>

<?php require __DIR__ . '/partials/footer.php'; ?>
