<?php
$host = 'sql210.infinityfree.com';
$db = 'if0_42178541_travel_platform';
$user = 'if0_42178541';
$pass = 'Ss12041104';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die('資料庫連線失敗：' . htmlspecialchars($e->getMessage()));
}
?>
