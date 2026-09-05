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
$humanConfirmation = !isset($_POST['human-confirmation']);

if ($website !== '') {
    echo json_encode(['success' => true]);
    exit;
}

if ($name === '' || $email === '' || $message === '' || !$human || !$humanConfirmation || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
