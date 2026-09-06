<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$website = trim((string) ($_POST['website'] ?? ''));
$human = ($_POST['human'] ?? '') === 'yes';
$mailingList = ($_POST['mailing-list'] ?? '') === 'yes';
$source = trim((string) ($_POST['source'] ?? 'homepage'));

if ($website !== '') {
    echo json_encode(['success' => true]);
    exit;
}

if ($mailingList) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please provide a valid email address.']);
        exit;
    }

    $source = preg_replace('/[^a-zA-Z0-9_-]/', '', $source) ?: 'homepage';
    $configPath = __DIR__ . '/config.php';

    if (!is_file($configPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Mailing list storage is not configured yet.']);
        exit;
    }

    try {
        $config = require $configPath;
        $database = $config['database'];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $database['host'],
            $database['name'],
            $database['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $database['user'], $database['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $statement = $pdo->prepare(
            'INSERT INTO mailing_list_subscribers (email, source) VALUES (:email, :source) '
            . 'ON DUPLICATE KEY UPDATE source = VALUES(source)'
        );
        $statement->execute(['email' => $email, 'source' => $source]);
    } catch (Throwable $error) {
        error_log('Mailing list signup failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'The mailing-list database is unavailable. Check that the table exists and that the database user has access.']);
        exit;
    }

    $subject = 'New mailing list signup';
    $body = "Email: {$email}\n";
    $headers = [
        'From: Website mailing list <info@reecemusic.com>',
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8'
    ];

    if (!mail('info@reecemusic.com', $subject, $body, implode("\r\n", $headers))) {
        error_log('Mailing list signup notification email could not be sent.');
    }

    $welcomeSubject = 'Thanks for joining the Reece Music mailing list';
    $welcomeText = "Thank you for signing up to Reece Music mailing list!\n\n"
        . "Reece Music\n"
        . "info@reecemusic.com\n";
    $welcomeHtml = '<!doctype html>'
        . '<html><body style="margin:0;background:#f3efe6;color:#1a1612;font-family:Arial,sans-serif;">'
        . '<div style="max-width:600px;margin:0 auto;padding:36px 20px;">'
        . '<div style="background:#1a1612;padding:28px 30px;border-bottom:4px solid #b08d57;">'
        . '<img src="https://reecemusic.com/images/reece-logo.jpg" alt="Reece Music" width="64" height="64" style="display:inline-block;vertical-align:middle;width:64px;height:64px;border-radius:50%;">'
        . '<span style="display:inline-block;vertical-align:middle;margin-left:14px;color:#f0d49c;font-size:32px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Reece Music</span>'
        . '</div>'
        . '<div style="background:#fbf7f0;padding:36px 30px;">'
        . '<h1 style="margin:0;font-size:26px;line-height:1.25;font-weight:700;">Thank you for signing up to Reece Music mailing list!</h1>'
        . '</div>'
        . '<p style="margin:18px 0 0;color:#6a6358;font-size:13px;line-height:1.5;text-align:center;">Reece Music · <a href="mailto:info@reecemusic.com" style="color:#8c6a34;">info@reecemusic.com</a></p>'
        . '</div></body></html>';
    $boundary = '=_reece_music_' . bin2hex(random_bytes(12));
    $welcomeHeaders = [
        'From: Reece Music <info@reecemusic.com>',
        'Reply-To: info@reecemusic.com',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
    ];
    $welcomeBody = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $welcomeText . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $welcomeHtml . "\r\n"
        . '--' . $boundary . "--\r\n";

    if (!mail($email, $welcomeSubject, $welcomeBody, implode("\r\n", $welcomeHeaders))) {
        error_log('Mailing list welcome email could not be sent to ' . $email . '.');
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($name === '' || $email === '' || $message === '' || !$human || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide a valid name, email address, and message.']);
    exit;
}

$name = str_replace(["\r", "\n"], ' ', $name);
$subject = 'New website enquiry from ' . $name;
$body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}\n";
$headers = [
    'From: Website contact form <info@reecemusic.com>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8'
];

if (!mail('info@reecemusic.com', $subject, $body, implode("\r\n", $headers))) {
    http_response_code(500);
    echo json_encode(['error' => 'The message could not be sent.']);
    exit;
}

echo json_encode(['success' => true]);
