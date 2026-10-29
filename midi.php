<?php
// MIDI store for reecemusic.com ("Play it yourself").
//   GET  midi.php?action=songs[&id=X]          → song list, or one song
//   POST midi.php?action=checkout  {id, tier}  → { url } of a Stripe Checkout page
//   GET  midi.php?action=verify&session_id=…   → purchase details and a 24-hour download link
//   GET  midi.php?action=download&free=<id>    → free preview
//   GET  midi.php?action=download&token=<t>    → paid file
// Catalog: data/midi-songs.json. Files: the private folder set in config.php (outside public_html).

declare(strict_types=1);

const PAID_TIERS = ['chorus', 'full'];
const LINK_HOURS = 24;

final class NotConfigured extends RuntimeException {}

function sendJson(int $status, array $body, string $cache = 'no-store'): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: ' . $cache);
    echo json_encode($body);
    exit;
}

function page(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Download | Reece Music</title><link rel="stylesheet" href="/css/reece.css">'
        . '<main class="section"><div class="wrap"><h1 style="font-size:2.4rem;margin-bottom:16px">Download unavailable</h1>'
        . '<p class="lede">' . $message . '</p><p><a class="btn btn-brass" href="/learn.html">Back to the songs</a></p></div></main>';
    exit;
}

function catalog(): array
{
    static $songs = null;
    $songs ??= json_decode((string) file_get_contents(__DIR__ . '/data/midi-songs.json'), true) ?: [];
    return $songs;
}

function findSong(mixed $id): ?array
{
    foreach (catalog() as $song) {
        if ($song['id'] === (string) $id) return $song;
    }
    return null;
}

// What the browser may see: never the stored file names.
function publicSong(array $s): array
{
    return [
        'id' => $s['id'],
        'title' => $s['title'],
        'artist' => $s['artist'],
        'description' => $s['description'],
        'youtubeId' => $s['youtubeId'] ?? '',
        'tiers' => [
            'free' => ['label' => $s['tiers']['free']['label'], 'price' => 0],
            'chorus' => ['label' => $s['tiers']['chorus']['label'], 'price' => $s['tiers']['chorus']['price']],
            'full' => ['label' => $s['tiers']['full']['label'], 'price' => $s['tiers']['full']['price']]
        ]
    ];
}

function midiConfig(string $key): string
{
    static $midi = null;
    if ($midi === null) {
        $path = __DIR__ . '/config.php';
        $midi = is_file($path) ? ((require $path)['midi'] ?? []) : [];
    }
    $value = (string) ($midi[$key] ?? '');
    if ($value === '' || str_contains($value, 'replace-with-')) throw new NotConfigured("midi.$key is not set in config.php");
    return $value;
}

// Absolute site URL for Stripe redirects; only trusted hosts, else reecemusic.com.
function siteUrl(): string
{
    $host = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]));
    if (preg_match('/^(www\.)?reecemusic\.com$/', $host)) return 'https://' . $host;
    if (preg_match('/^localhost(:\d+)?$/', $host)) return 'http://' . $host;
    return 'https://reecemusic.com';
}

// ---- download tokens (HMAC-signed, expire after LINK_HOURS) ----
function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function unb64url(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/')); }

function tokenSecret(): string
{
    $s = midiConfig('token_secret');
    if (strlen($s) < 16) throw new NotConfigured('midi.token_secret is too short');
    return $s;
}

function signToken(array $payload): string
{
    $body = b64url(json_encode($payload + ['exp' => (time() + LINK_HOURS * 3600) * 1000]));
    return $body . '.' . b64url(hash_hmac('sha256', $body, tokenSecret(), true));
}

function verifyToken(string $token): ?array
{
    [$body, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if ($body === '' || $sig === '') return null;
    if (!hash_equals(b64url(hash_hmac('sha256', $body, tokenSecret(), true)), $sig)) return null;
    $data = json_decode(unb64url($body), true);
    if (!is_array($data) || empty($data['exp']) || time() * 1000 > $data['exp']) return null;
    return $data;
}

// ---- Stripe (REST API, no SDK needed) ----
function stripe(string $method, string $path, ?array $form = null): array
{
    $ch = curl_init('https://api.stripe.com/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => midiConfig('stripe_secret_key') . ':'
    ]);
    if ($form !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $data = json_decode((string) $raw, true) ?: [];
    if ($status < 200 || $status >= 300) throw new RuntimeException($data['error']['message'] ?? "Stripe $status");
    return $data;
}

function readBody(): array
{
    $raw = (string) file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

$action = (string) ($_GET['action'] ?? '');

if ($action === 'songs') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Method not allowed.']);
    $cache = 'public, max-age=300';
    if (isset($_GET['id'])) {
        $song = findSong($_GET['id']);
        if (!$song) sendJson(404, ['error' => 'Song not found.']);
        sendJson(200, publicSong($song), $cache);
    }
    sendJson(200, ['songs' => array_map('publicSong', catalog())], $cache);
}

if ($action === 'checkout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Method not allowed.']);
    $body = readBody();
    $song = findSong($body['id'] ?? '');
    $tier = (string) ($body['tier'] ?? '');
    if (!$song) sendJson(404, ['error' => 'Song not found.']);
    if (!in_array($tier, PAID_TIERS, true)) sendJson(400, ['error' => 'Choose the chorus or the full song.']);

    $t = $song['tiers'][$tier];
    $base = siteUrl();
    try {
        $session = stripe('POST', '/checkout/sessions', [
            'mode' => 'payment',
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (string) $t['price'],
            'line_items[0][price_data][product_data][name]' => "{$song['title']} ({$t['label']})",
            'line_items[0][price_data][product_data][description]' => "By {$song['artist']}. Includes the video tutorial.",
            'metadata[songId]' => $song['id'],
            'metadata[tier]' => $tier,
            'success_url' => $base . '/purchase-complete.html?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base . '/song.html?id=' . rawurlencode($song['id'])
        ]);
        sendJson(200, ['url' => $session['url']]);
    } catch (NotConfigured $e) {
        error_log('Checkout failed: ' . $e->getMessage());
        sendJson(503, ['error' => "Payments aren't switched on yet. Please try again later."]);
    } catch (Throwable $e) {
        error_log('Checkout failed: ' . $e->getMessage());
        sendJson(502, ['error' => "Checkout couldn't start. Please try again."]);
    }
}

if ($action === 'verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Method not allowed.']);
    $sessionId = (string) ($_GET['session_id'] ?? '');
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) sendJson(400, ['error' => 'Missing payment reference.']);

    try {
        $session = stripe('GET', '/checkout/sessions/' . rawurlencode($sessionId));
        if (($session['payment_status'] ?? '') !== 'paid') sendJson(402, ['error' => "This payment hasn't completed."]);
        $meta = $session['metadata'] ?? [];
        $song = findSong($meta['songId'] ?? ($meta['productId'] ?? ''));
        $tier = (string) ($meta['tier'] ?? '');
        if (!$song || !in_array($tier, PAID_TIERS, true)) sendJson(404, ['error' => "We couldn't match this payment to a song."]);

        $token = signToken(['songId' => $song['id'], 'tier' => $tier, 'sessionId' => $sessionId]);
        sendJson(200, [
            'title' => $song['title'],
            'artist' => $song['artist'],
            'tier' => $song['tiers'][$tier]['label'],
            'youtubeId' => $song['youtubeId'] ?? '',
            'songUrl' => '/song.html?id=' . rawurlencode($song['id']),
            'downloadUrl' => '/midi.php?action=download&token=' . rawurlencode($token)
        ]);
    } catch (NotConfigured $e) {
        error_log('Verify failed: ' . $e->getMessage());
        sendJson(503, ['error' => "We couldn't prepare your download just now. Email info@reecemusic.com with your receipt and Reece will send your file."]);
    } catch (Throwable $e) {
        error_log('Verify failed: ' . $e->getMessage());
        sendJson(502, ['error' => "We couldn't confirm the payment just now. Refresh to try again."]);
    }
}

if ($action === 'download') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') page(405, 'Method not allowed.');
    try {
        if (isset($_GET['free'])) {
            $song = findSong($_GET['free']);
            $tier = 'free';
            if (!$song) page(404, "That song isn't in the store.");
        } else {
            $data = null;
            try { $data = verifyToken((string) ($_GET['token'] ?? '')); } catch (NotConfigured $e) {}
            if (!$data) page(401, "This download link has expired or isn't valid. Links last 24 hours after purchase. Email info@reecemusic.com with your receipt and Reece will send a new one.");
            $song = findSong($data['songId'] ?? '');
            $tier = (string) ($data['tier'] ?? '');
            if (!$song || !in_array($tier, PAID_TIERS, true)) page(404, "That song isn't in the store anymore. Email info@reecemusic.com with your receipt.");
        }

        $file = basename((string) $song['tiers'][$tier]['file']);
        $path = rtrim(midiConfig('files_dir'), '/') . '/' . $file;
        if (!is_file($path)) page(404, "This MIDI file is being prepared and isn't uploaded yet. Please check back soon, or email info@reecemusic.com.");

        header('Content-Type: audio/midi');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    } catch (Throwable $e) {
        error_log('Download failed: ' . $e->getMessage());
        page(503, "Downloads aren't available right now. Please try again in a few minutes.");
    }
}

sendJson(404, ['error' => 'Not found.']);
