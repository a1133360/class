<?php
require_once 'db_connect.php';

$cronKey = 'travelhub_cron_2026';
if (($_GET['key'] ?? '') !== $cronKey) {
    http_response_code(403);
    exit('Forbidden');
}

function logDebtEmail(PDO $pdo, string $recipient, string $subject, bool $sent, string $error = '') {
    $stmt = $pdo->prepare("
        INSERT INTO email_logs (recipient, subject, status, error_message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$recipient, $subject, $sent ? 'sent' : 'failed', $error ?: null]);
}

$baseUrl = 'https://chiyu.infinityfree.io';

$query = "
    SELECT
        u.email,
        u.username,
        i.id AS itinerary_id,
        i.title,
        COALESCE(SUM(es.share_amount), 0) AS debt_total
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    JOIN itineraries i ON e.itinerary_id = i.id
    JOIN users u ON es.user_id = u.id
    WHERE i.trip_status = 'finished'
      AND es.is_settled = 0
      AND es.user_id != e.paid_by
    GROUP BY u.id, u.email, u.username, i.id, i.title
    HAVING debt_total > 0
";
$rows = $pdo->query($query)->fetchAll();

$sentCount = 0;
$failedCount = 0;

foreach ($rows as $row) {
    $to = $row['email'];
    $subject = 'Travel Hub 催帳通知：' . $row['title'];
    $link = $baseUrl . '/settlement_report.php?itinerary_id=' . (int)$row['itinerary_id'];
    $amount = number_format((float)$row['debt_total'], 0);
    $message = "
        <html>
        <body>
            <p>{$row['username']} 你好：</p>
            <p>你的行程 <strong>{$row['title']}</strong> 尚有未結清款項 NT$ {$amount}。</p>
            <p>請點擊以下連結回平台查看明細並標記結算：</p>
            <p><a href='{$link}'>查看結算清單</a></p>
        </body>
        </html>
    ";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Travel Hub <no-reply@chiyu.infinityfree.io>\r\n";

    $sent = @mail($to, $subject, $message, $headers);
    logDebtEmail($pdo, $to, $subject, $sent, $sent ? '' : 'mail() returned false on InfinityFree.');

    $sent ? $sentCount++ : $failedCount++;
}

echo "Debt reminder emails finished. Sent: {$sentCount}, Failed: {$failedCount}\n";
?>
