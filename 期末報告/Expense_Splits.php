<?php
require_once 'db_connect.php';

// 1. 查詢所有「未結清」且「扣除掉自己欠自己」的拆帳明細
$query = "
    SELECT 
        es.id AS split_id,
        es.share_amount,
        debtor.username AS debtor_name,
        debtor.email AS debtor_email,
        creditor.username AS creditor_name,
        i.title AS itinerary_title
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    JOIN itineraries i ON e.itinerary_id = i.id
    JOIN users debtor ON es.user_id = debtor.id
    JOIN users creditor ON e.paid_by = creditor.id
    WHERE es.is_settled = 0 AND es.user_id != e.paid_by
";

$stmt = $pdo->query($query);
$reminders = $stmt->fetchAll();

// 2. 巡迴發送催帳通知信件
foreach ($reminders as $row) {
    $to = $row['debtor_email'];
    $subject = "【智慧分帳平台】您有旅行未結清款項催帳通知";
    
    $message = "
    <html>
    <head>
        <title>旅行費用催帳通知</title>
    </head>
    <body>
        <p>親愛的 <b>{$row['debtor_name']}</b> 您好：</p>
        <p>您參與的共筆行程 <b>「{$row['itinerary_title']}」</b> 目前尚有未結清的款項。</p>
        <p>您需要支付給主揪 <b>{$row['creditor_name']}</b> 的金額為：
           <span style='color: red; font-size: 16px; font-weight: bold;'>NT$ " . number_format($row['share_amount'], 2) . "</span> 元。
        </p>
        <p>請儘速點擊下方連結回到平台查看明細並進行結帳：</p>
        <p><a href='http://localhost/settlement_report.php' style='padding: 10px 15px; background-color: #008cba; color: white; text-decoration: none; border-radius: 4px; text-dash: none;'>前往結算中心結帳</a></p>
        <br>
        <p>※ 本信件由系統自動發送，請勿直接回覆。</p>
    </body>
    </html>
    ";

    // 設定 HTML 郵件標頭
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: smart_split@yourdomain.com" . "\r\n";

    // 執行發信
    mail($to, $subject, $message, $headers);
}

// 輸出執行結果回報
echo "[Expense_Splits 催帳通知發送完畢] 本次共成功通知 " . count($reminders) . " 筆未結清帳目。";
?>