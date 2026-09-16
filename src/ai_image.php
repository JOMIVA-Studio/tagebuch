<?php
declare(strict_types=1);

/**
 * Erzeugt ein Bild per KI aus einem Text-Prompt und speichert es lokal.
 * Aktuell unterstuetzt: OpenAI (Modell dall-e-3). Der Zugang wird in der
 * Administration unter "KI-Bilder" hinterlegt.
 *
 * @return string Relativer Web-Pfad zum gespeicherten Bild
 * @throws RuntimeException bei fehlender Konfiguration oder API-Fehler
 */
function generate_ai_image(string $prompt, int $userId): string
{
    $pdo = db();
    $settings = $pdo->query('SELECT * FROM ai_settings WHERE id = 1')->fetch();

    if (empty($settings['api_key'])) {
        throw new RuntimeException('Es ist noch kein KI-Zugang hinterlegt. Bitte in der Administration unter "KI-Bilder" einen API-Key eintragen.');
    }

    if ($settings['provider'] !== 'openai') {
        throw new RuntimeException('Der gewaehlte KI-Anbieter wird noch nicht unterstuetzt.');
    }

    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $settings['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'b64_json',
        ]),
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Verbindung zur KI fehlgeschlagen: ' . $curlError);
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $message = $data['error']['message'] ?? 'Unbekannter Fehler.';
        throw new RuntimeException('Die KI konnte kein Bild erzeugen: ' . $message);
    }

    $b64 = $data['data'][0]['b64_json'] ?? null;
    if ($b64 === null) {
        throw new RuntimeException('Die KI-Antwort enthielt kein Bild.');
    }

    $bytes = base64_decode($b64, true);
    if ($bytes === false) {
        throw new RuntimeException('Das erzeugte Bild konnte nicht gelesen werden.');
    }

    $dir = __DIR__ . '/../public/uploads/vision/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Das Upload-Verzeichnis konnte nicht angelegt werden.');
    }

    $filename = bin2hex(random_bytes(16)) . '.png';
    if (file_put_contents($dir . '/' . $filename, $bytes) === false) {
        throw new RuntimeException('Das erzeugte Bild konnte nicht gespeichert werden.');
    }

    return '/uploads/vision/' . $userId . '/' . $filename;
}
