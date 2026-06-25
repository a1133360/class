<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => '請使用 POST 儲存共筆。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$itineraryId = (int)($_POST['itinerary_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$itineraryId) {
    echo json_encode(['status' => 'error', 'message' => '缺少行程 ID。'], JSON_UNESCAPED_UNICODE);
    exit();
}

requireItineraryMember($pdo, $itineraryId);

$statusStmt = $pdo->prepare("SELECT trip_status FROM itineraries WHERE id = ?");
$statusStmt->execute([$itineraryId]);
if ($statusStmt->fetchColumn() === 'finished') {
    echo json_encode(['status' => 'error', 'message' => '行程已結束，不能再編輯共筆。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt = $pdo->prepare("
    INSERT INTO itinerary_notes (itinerary_id, content, updated_by)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$itineraryId, $content, currentUserId()]);

echo json_encode(['status' => 'success', 'message' => '共筆已儲存。'], JSON_UNESCAPED_UNICODE);
?>
