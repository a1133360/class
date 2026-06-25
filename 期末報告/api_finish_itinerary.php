<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: itinerary_list.php');
    exit();
}

$itineraryId = (int)($_POST['itinerary_id'] ?? 0);
if (!$itineraryId) {
    header('Location: itinerary_list.php?error=missing');
    exit();
}

$stmt = $pdo->prepare("SELECT creator_id FROM itineraries WHERE id = ?");
$stmt->execute([$itineraryId]);
$creatorId = (int)$stmt->fetchColumn();

if ($creatorId !== currentUserId()) {
    http_response_code(403);
    die('只有行程建立者可以結束行程。');
}

$update = $pdo->prepare("UPDATE itineraries SET trip_status = 'finished', finished_at = NOW() WHERE id = ?");
$update->execute([$itineraryId]);

header('Location: workspace.php?id=' . $itineraryId . '&finished=1');
exit();
?>
