<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

$itineraryId = (int)($_GET['itinerary_id'] ?? 0);
if (!$itineraryId) {
    echo json_encode([]);
    exit();
}

$accessStmt = $pdo->prepare("SELECT is_public, status FROM itineraries WHERE id = ?");
$accessStmt->execute([$itineraryId]);
$itinerary = $accessStmt->fetch();
$isPublicTemplate = $itinerary && $itinerary['is_public'] === 'yes' && $itinerary['status'] === 'approved';

if (!$isPublicTemplate && !isItineraryMember($pdo, $itineraryId, currentUserId())) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => '你沒有權限讀取景點。'], JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt = $pdo->prepare("
    SELECT id, itinerary_id, spot_name, place_id, address, lat, lng, day_number, sequence
    FROM spots
    WHERE itinerary_id = ?
    ORDER BY day_number ASC, sequence ASC, id ASC
");
$stmt->execute([$itineraryId]);

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
?>
