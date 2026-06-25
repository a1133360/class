<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_templates.php');
    exit();
}

$itineraryId = (int)($_POST['itinerary_id'] ?? 0);
$status = $_POST['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected'];

if ($itineraryId && in_array($status, $allowed, true)) {
    $stmt = $pdo->prepare("UPDATE itineraries SET status = ? WHERE id = ? AND is_public = 'yes'");
    $stmt->execute([$status, $itineraryId]);
}

header('Location: admin_templates.php');
exit();
?>
