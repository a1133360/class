<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => '請使用 POST 新增景點。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$itineraryId = (int)($_POST['itinerary_id'] ?? 0);
$spotName = trim($_POST['spot_name'] ?? '');
$placeId = trim($_POST['place_id'] ?? '');
$address = trim($_POST['address'] ?? '');
$lat = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
$lng = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
$dayNumber = max(1, (int)($_POST['day_number'] ?? 1));

if (!$itineraryId || $spotName === '') {
    echo json_encode(['status' => 'error', 'message' => '請輸入行程與景點名稱。'], JSON_UNESCAPED_UNICODE);
    exit();
}

requireItineraryMember($pdo, $itineraryId);

$statusStmt = $pdo->prepare("SELECT trip_status FROM itineraries WHERE id = ?");
$statusStmt->execute([$itineraryId]);
if ($statusStmt->fetchColumn() === 'finished') {
    echo json_encode(['status' => 'error', 'message' => '此行程已結束，不能再新增景點。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$seqStmt = $pdo->prepare("SELECT COALESCE(MAX(sequence), 0) + 1 FROM spots WHERE itinerary_id = ? AND day_number = ?");
$seqStmt->execute([$itineraryId, $dayNumber]);
$sequence = (int)$seqStmt->fetchColumn();

$stmt = $pdo->prepare("
    INSERT INTO spots (itinerary_id, spot_name, place_id, address, lat, lng, day_number, sequence)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$itineraryId, $spotName, $placeId ?: null, $address ?: null, $lat, $lng, $dayNumber, $sequence]);

echo json_encode(['status' => 'success', 'message' => '景點已新增。'], JSON_UNESCAPED_UNICODE);
?>
