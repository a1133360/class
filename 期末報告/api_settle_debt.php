<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

function finishSettle($payload, $redirect = 'settlement_report.php') {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($accept, 'application/json');

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }

    header('Location: ' . $redirect);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finishSettle(['status' => 'error', 'message' => '請使用表單送出。']);
}

$splitId = (int)($_POST['split_id'] ?? 0);
if (!$splitId) {
    finishSettle(['status' => 'error', 'message' => '缺少結算資料。'], 'settlement_report.php?error=missing');
}

$stmt = $pdo->prepare("
    SELECT es.id, e.itinerary_id
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    WHERE es.id = ? AND es.user_id = ? AND e.paid_by != ?
");
$stmt->execute([$splitId, currentUserId(), currentUserId()]);
$split = $stmt->fetch();

if (!$split) {
    finishSettle(['status' => 'error', 'message' => '找不到可結算的欠款。'], 'settlement_report.php?error=notfound');
}

$update = $pdo->prepare("UPDATE expense_splits SET is_settled = 1, settled_at = NOW() WHERE id = ?");
$update->execute([$splitId]);

finishSettle(
    ['status' => 'success', 'message' => '已標記為已結算。'],
    'settlement_report.php?success=1&itinerary_id=' . (int)$split['itinerary_id']
);
?>
