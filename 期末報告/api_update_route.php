<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => '請使用 POST 更新路線順序。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$spotId = (int)($_POST['spot_id'] ?? 0);
$sequence = max(0, (int)($_POST['sequence'] ?? 0));

if (!$spotId) {
    echo json_encode(['status' => 'error', 'message' => '缺少景點資料。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt = $pdo->prepare("
    SELECT s.itinerary_id, i.trip_status
    FROM spots s
    JOIN itineraries i ON s.itinerary_id = i.id
    WHERE s.id = ?
");
$stmt->execute([$spotId]);
$spot = $stmt->fetch();

if (!$spot) {
    echo json_encode(['status' => 'error', 'message' => '找不到景點。'], JSON_UNESCAPED_UNICODE);
    exit();
}

requireItineraryMember($pdo, (int)$spot['itinerary_id']);

if ($spot['trip_status'] === 'finished') {
    echo json_encode(['status' => 'error', 'message' => '行程已結束，不能更新路線。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$update = $pdo->prepare("UPDATE spots SET sequence = ? WHERE id = ?");
$update->execute([$sequence, $spotId]);

echo json_encode(['status' => 'success', 'message' => '路線順序已更新。'], JSON_UNESCAPED_UNICODE);
?>
