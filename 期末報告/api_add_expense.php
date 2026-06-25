<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

function respond($payload, $redirect = null) {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($accept, 'application/json');

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }

    header('Location: ' . ($redirect ?: 'expense_list.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => '請使用表單送出。'], 'expense_list.php');
}

$itineraryId = (int)($_POST['itinerary_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$category = trim($_POST['category'] ?? '其他');
$description = trim($_POST['description'] ?? '');
$splitUsers = array_map('intval', $_POST['split_users'] ?? []);
$splitUsers = array_values(array_unique(array_filter($splitUsers)));

if (!$itineraryId || $amount <= 0 || $category === '' || empty($splitUsers)) {
    respond(['status' => 'error', 'message' => '請填寫行程、金額、類別，並至少選擇一位分攤成員。'], 'expense_list.php?error=missing&itinerary_id=' . $itineraryId);
}

requireItineraryMember($pdo, $itineraryId);

$statusStmt = $pdo->prepare("SELECT trip_status FROM itineraries WHERE id = ?");
$statusStmt->execute([$itineraryId]);
if ($statusStmt->fetchColumn() === 'finished') {
    respond(['status' => 'error', 'message' => '此行程已結束，不能再新增代墊。'], 'expense_list.php?error=finished&itinerary_id=' . $itineraryId);
}

$memberStmt = $pdo->prepare("
    SELECT user_id FROM itinerary_members WHERE itinerary_id = ?
    UNION
    SELECT creator_id FROM itineraries WHERE id = ? AND creator_id IS NOT NULL
");
$memberStmt->execute([$itineraryId, $itineraryId]);
$allowedMembers = array_map('intval', array_column($memberStmt->fetchAll(), 'user_id'));
$invalidMembers = array_diff($splitUsers, $allowedMembers);

if (!empty($invalidMembers)) {
    respond(['status' => 'error', 'message' => '分攤成員不屬於此行程。'], 'expense_list.php?error=members&itinerary_id=' . $itineraryId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO expenses (itinerary_id, paid_by, amount, category, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$itineraryId, currentUserId(), $amount, $category, $description]);
    $expenseId = (int)$pdo->lastInsertId();

    $shareAmount = round($amount / count($splitUsers), 2);
    $stmtSplit = $pdo->prepare("
        INSERT INTO expense_splits (expense_id, user_id, share_amount, is_settled, settled_at)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($splitUsers as $userId) {
        $isSettled = $userId === currentUserId() ? 1 : 0;
        $settledAt = $isSettled ? date('Y-m-d H:i:s') : null;
        $stmtSplit->execute([$expenseId, $userId, $shareAmount, $isSettled, $settledAt]);
    }

    $pdo->commit();
    respond(['status' => 'success', 'message' => '代墊已新增。'], 'expense_list.php?success=1&itinerary_id=' . $itineraryId);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['status' => 'error', 'message' => '新增失敗：' . $e->getMessage()], 'expense_list.php?error=save&itinerary_id=' . $itineraryId);
}
?>
