<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'];
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
echo json_encode(['success' => true]);
?>
