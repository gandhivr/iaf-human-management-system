<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'];
$conn->query("DELETE FROM notifications WHERE user_id = $user_id");
echo json_encode(['success' => true]);
?>
