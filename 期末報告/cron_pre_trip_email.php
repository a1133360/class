<?php
require_once 'db_connect.php';

$cronKey = 'travelhub_cron_2026';
if (($_GET['key'] ?? '') !== $cronKey) {
    http_response_code(403);
    exit('Forbidden');
}

function logTripEmail(PDO $pdo, string $recipient, string $subject, bool $sent, string $error = '') {
    $stmt = $pdo->prepare("
        INSERT INTO email_logs (recipient, subject, status, error_message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$recipient, $subject, $sent ? 'sent' : 'failed', $error ?: null]);
}

$baseUrl = 'https://chiyu.infinityfree.io';
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$query = "
    SELECT DISTINCT u.email, u.username, i.id AS itinerary_id, i.title, i.destination, i.start_date
    FROM itinerary_members im
    JOIN users u ON im.user_id = u.id
    JOIN itineraries i ON im.itinerary_id = i.id
    WHERE i.trip_status = 'active'
      AND i.start_date = ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$tomorrow]);
$rows = $stmt->fetchAll();

$sentCount = 0;
$failedCount = 0;

foreach ($rows as $row) {
    $to = $row['email'];
    $subject = 'Travel Hub 出發前提醒：' . $row['title'];
    $link = $baseUrl . '/workspace.php?id=' . (int)$row['itinerary_id'];
    $destination = $row['destination'] ?: '目的地';
    $message = "
        <html>
        <body>
            <p>{$row['username']} 你好：</p>
            <p>你的行程 <strong>{$row['title']}</strong> 將在 {$row['start_date']} 出發。</p>
            <p><strong>行李檢查清單：</strong>證件、錢包、手機與充電器、常備藥、雨具、換洗衣物、訂房與交通憑證。</p>
            <p><strong>天氣提醒：</strong>請出發前查看 {$destination} 的即時天氣與降雨機率。</p>
            <p><a href='{$link}'>開啟行程工作區</a></p>
        </body>
        </html>
    ";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Travel Hub <no-reply@chiyu.infinityfree.io>\r\n";

    $sent = @mail($to, $subject, $message, $headers);
    logTripEmail($pdo, $to, $subject, $sent, $sent ? '' : 'mail() returned false on InfinityFree.');

    $sent ? $sentCount++ : $failedCount++;
}

echo "Pre-trip emails finished. Date: {$tomorrow}. Sent: {$sentCount}, Failed: {$failedCount}\n";
?>
