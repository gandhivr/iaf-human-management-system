<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: notifications.php");
    exit();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: notifications.php");
    exit();
}

// Handle delete notification
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: notifications.php");
    exit();
}

// Fetch notifications
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "WHERE user_id = $user_id";

if ($filter === 'unread') {
    $where_clause .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where_clause .= " AND is_read = 1";
}

$notifications_sql = "SELECT * FROM notifications $where_clause ORDER BY created_at DESC LIMIT 50";
$notifications_result = $conn->query($notifications_sql);

// Get notification counts
$total_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id")->fetch_assoc()['count'];
$unread_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Notifications - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; margin:0; background:#f4f4f9;}
  header {background:#007BFF; color:#fff; padding:15px 20px; text-align:center; font-size:1.5rem;}
  nav {background:#003366; color:#fff; width:220px; height:100vh; position:fixed; top:0; left:0; padding-top:60px; box-sizing:border-box;}
  nav a {display:block; color:#fff; padding:15px 25px; text-decoration:none; font-weight:bold; border-left:5px solid transparent; transition: background 0.3s, border-color 0.3s;}
  nav a:hover {background:#0056b3; border-left:5px solid #FFD700;}
  main {margin-left:220px; padding:25px; min-height:100vh; box-sizing:border-box;}
  
  .page-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .page-title {font-size: 2rem; color: #007BFF; margin: 0;}
  .notification-stats {display: flex; gap: 20px; align-items: center;}
  .stat-item {text-align: center;}
  .stat-number {font-size: 1.5rem; font-weight: bold; color: #007BFF;}
  .stat-label {font-size: 0.9rem; color: #666;}
  
  .filters {background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;}
  .filter-buttons {display: flex; gap: 10px; align-items: center;}
  .filter-btn {padding: 8px 16px; border: 2px solid #007BFF; background: white; color: #007BFF; border-radius: 20px; text-decoration: none; font-weight: 500; transition: all 0.3s;}
  .filter-btn:hover, .filter-btn.active {background: #007BFF; color: white;}
  
  .notifications-container {background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;}
  .notifications-header {padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: between; align-items: center;}
  .notifications-header h3 {margin: 0; color: #333;}
  .mark-all-btn {background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.9rem;}
  .mark-all-btn:hover {background: #218838;}
  
  .notification-list {max-height: 600px; overflow-y: auto;}
  .notification-item {padding: 20px; border-bottom: 1px solid #f0f0f0; transition: all 0.3s; position: relative;}
  .notification-item:hover {background: #f8f9fa;}
  .notification-item.unread {border-left: 4px solid #007BFF; background: #f8f9ff;}
  
  .notification-content {display: flex; justify-content: space-between