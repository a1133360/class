<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    if (isset($_SESSION['status']) && $_SESSION['status'] === 'suspended') {
        session_destroy();
        die('此帳號已被停權，請聯絡管理員。');
    }
}

function checkAdmin() {
    checkLogin();

    if (($_SESSION['role'] ?? 'user') !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

function currentUserId() {
    return (int)($_SESSION['user_id'] ?? 0);
}

function isItineraryMember(PDO $pdo, int $itineraryId, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM itineraries i
        LEFT JOIN itinerary_members im ON i.id = im.itinerary_id AND im.user_id = ?
        WHERE i.id = ? AND (i.creator_id = ? OR im.user_id IS NOT NULL)
        LIMIT 1
    ");
    $stmt->execute([$userId, $itineraryId, $userId]);

    return (bool)$stmt->fetchColumn();
}

function requireItineraryMember(PDO $pdo, int $itineraryId) {
    checkLogin();

    if (!isItineraryMember($pdo, $itineraryId, currentUserId())) {
        http_response_code(403);
        die('你沒有權限查看這個行程。');
    }
}
?>
