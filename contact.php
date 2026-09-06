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
        echo json_encode(['error' => 'The signup could not be completed.']);
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
