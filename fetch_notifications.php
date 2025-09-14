<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT id, message, created_at FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
echo json_encode(['notifications' => $notifications]);
?>
