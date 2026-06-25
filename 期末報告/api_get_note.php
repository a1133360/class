<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

$itineraryId = (int)($_GET['itinerary_id'] ?? 0);
if (!$itineraryId) {
    echo json_encode(['status' => 'error', 'message' => '缺少行程 ID。'], JSON_UNESCAPED_UNICODE);
    exit();
}

requireItineraryMember($pdo, $itineraryId);

$stmt = $pdo->prepare("
    SELECT n.content, n.updated_at, u.username AS updated_by_name
    FROM itinerary_notes n
    LEFT JOIN users u ON n.updated_by = u.id
    WHERE n.itinerary_id = ?
");
$stmt->execute([$itineraryId]);
$note = $stmt->fetch() ?: ['content' => '', 'updated_at' => null, 'updated_by_name' => null];

echo json_encode(['status' => 'success', 'note' => $note], JSON_UNESCAPED_UNICODE);
?>
