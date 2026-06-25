<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

header('Content-Type: application/json; charset=utf-8');

$keyword = trim($_GET['keyword'] ?? '');
$like = '%' . $keyword . '%';

$stmt = $pdo->prepare("
    SELECT i.id, i.title, i.creator_id, u.username AS creator_name, COUNT(s.id) AS spot_count
    FROM itineraries i
    LEFT JOIN users u ON i.creator_id = u.id
    LEFT JOIN spots s ON i.id = s.itinerary_id
    WHERE i.is_public = 'yes'
      AND i.status = 'approved'
      AND (? = '' OR i.title LIKE ? OR s.spot_name LIKE ?)
    GROUP BY i.id, i.title, i.creator_id, u.username
    ORDER BY i.id DESC
    LIMIT 20
");
$stmt->execute([$keyword, $like, $like]);

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
?>
