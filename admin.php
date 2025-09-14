<?php
session_start();
include 'db.php';

// Only allow HR Manager and Commander access to admin functions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR Manager', 'Commander'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$messages = [];

// Handle user management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $full_name = $conn->real_escape_string(trim($_POST['full_name']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $role = $conn->real_escape_string($_POST['role']);
        $temp_password = 'TempPass' . random_int(1000, 9999);
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $messages[] = ['type' => 'error', 'text' => 'Email already exists in the system.'];
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);
            
            if ($stmt->execute()) {
                $messages[] = ['type' => 'success', 'text' => "User created successfully. Temporary password: $temp_password"];
                
                // Send notification to new user (in production, send via email)
                $new_user_id = $conn->insert_id;
                $notification_title = "Welcome to IAF Human Management System";
                $notification_message = "Your account has been created. Please login and change your password immediately. Temporary password: $temp_password";
                
                $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                $notify_stmt->bind_param("iss", $new_user_id, $notification_title, $notification_message);
                $notify_stmt->execute();
                $notify_stmt->close();
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Failed to create user.'];
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    if (isset($_POST['reset_user_password'])) {
        $target_user_id = intval($_POST['user_id']);
        $new_temp_password = 'Reset' . random_int(10000, 99999);
        $password_hash = password_hash($new_temp_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $target_user_id);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => "Password reset successfully. New temporary password: $new_temp_password"];
            
            // Send notification to user
            $notification_title = "Password Reset";
            $notification_message = "Your password has been reset by an administrator. New temporary password: $new_temp_password";
            
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'warning')");
            $notify_stmt->bind_param("iss", $target_user_id, $notification_title, $notification_message);
            $notify_stmt->execute();
            $notify_stmt->close();
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to reset password.'];
        }
        $stmt->close();
    }
    
    if (isset($_POST['send_notification'])) {
        $notification_type = $_POST['notification_type'];
        $target_users = $_POST['target_users']; // 'all' or specific user IDs
        $title = $conn->real_escape_string(trim($_POST['notification_title']));
        $message = $conn->real_escape_string(trim($_POST['notification_message']));
        $notify_type = $conn->real_escape_string($_POST['notify_type']);
        
        if ($target_users === 'all') {
            $users_result = $conn->query("SELECT id FROM users");
            $sent_count = 0;
            while ($user = $users_result->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $user['id'], $title, $message, $notify_type);
                if ($stmt->execute()) $sent_count++;
                $stmt->close();
            }
            $messages[] = ['type' => 'success', 'text' => "Notification sent to $sent_count users."];
        } else {
            // Handle specific users (could be expanded for role-based targeting)
            $messages[] = ['type' => 'info', 'text' => 'Specific user targeting not implemented yet.'];
        }
    }
}

// Fetch all users for management
$users_result = $conn->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM audit_logs WHERE user_id = u.id) as activity_count,
    (SELECT COUNT(*) FROM notifications WHERE user_id = u.id AND is_read = 0) as unread_notifications
    FROM users u 
    ORDER BY u.created_at DESC
");

// System statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_personnel' => $conn->query("SELECT COUNT(*) as count FROM personnel")->fetch_assoc()['count'],
    'active_deployments' => $conn->query("SELECT COUNT(*) as count FROM deployments WHERE status = 'Active'")->fetch_assoc()['count'],
    'pending_notifications' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count']
];

// Recent audit logs
$audit_logs = [];
$audit_result = $conn->query("
    SELECT al.*, u.full_name, p.full_name as personnel_name 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    LEFT JOIN personnel p ON al.personnel_id = p.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
while ($log = $audit_result->fetch_assoc()) {
    $audit_logs[] = $log;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>System Administration - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; margin:0; background:#f4f4f9;}
  header {background:#007BFF; color:#fff; padding:15px 20px; text-align:center; font-size:1.5rem;}
  nav {background:#003366; color:#fff; width:220px; height:100vh; position:fixed; top:0; left:0; padding-top:60px; box-sizing:border-box;}
  nav a {display:block; color:#fff; padding:15px 25px; text-decoration:none; font-weight:bold; border-left:5px solid transparent; transition: background 0.3s, border-color 0.3s;}
  nav a:hover {background:#0056b3; border-left:5px solid #FFD700;}
  main {margin-left:220px; padding:25px; min-height:100vh; box-sizing:border-box;}
  
  .admin-header {background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center;}
  .admin-header h1 {margin: 0; font-size: 2rem;}
  .admin-header p {margin: 10px 0 0; opacity: 0.9;}
  
  .stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;}
  .stat-card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #007BFF;}
  .stat-value {font-size: 2.5rem; font-weight: bold; color: #007BFF; margin-bottom: 10px;}
  .stat-label {color: #666; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 1px;}
  
  .admin-sections {display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;}
  .admin-card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .admin-card h3 {color: #007BFF; margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;}
  
  .form-group {margin-bottom: 20px;}
  .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333;}
  .form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;
  }
  .form-group textarea {resize: vertical; min-height: 80px;}
  
  .btn {padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: bold; transition: all 0.3s; text-decoration: none; display: inline-block;}
  .btn-primary {background: #007BFF; color: white;}
  .btn-primary:hover {background: #0056b3;}
  .btn-success {background: #28a745; color: white;}
  .btn-success:hover {background: #218838;}
  .btn-warning {background: #ffc107; color: #212529;}
  .btn-warning:hover {background: #e0a800;}
  .btn-danger {background: #dc3545; color: white;}
  .btn-danger:hover {background: #c82333;}
  
  .users-table {width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .users-table th, .users-table td {padding: 12px; text-align: left; border-bottom: 1px solid #eee;}
  .users-table th {background: #f8f9fa; font-weight: bold; color: #333;}
  .users-table tr:hover {background: #f1f3f4;}
  
  .role-badge {padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase;}
  .role-commander {background: #6f42c1; color: white;}
  .role-hr {background: #007BFF; color: white;}
  .role-medical {background: #28a745; color: white;}
  .role-training {background: #17a2b8; color: white;}
  .role-ground {background: #6c757d; color: white;}
  
  .message {padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid;}
  .message.success {background: #d4edda; color: #155724; border-left-color: #28a745;}
  .message.error {background: #f8d7da; color: #721c24; border-left-color: #dc3545;}
  .message.info {background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8;}
  
  .audit-log {max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 4px;}
  .audit-item {padding: 10px 0; border-bottom: 1px solid #e9ecef; font-size: 0.9rem;}
  .audit-item:last-child {border-bottom: none;}
  .audit-user {font-weight: bold; color: #007BFF;}
  .audit-time {color: #666; font-size: 0.8rem;}
  
  @media (max-width: 768px) {
    nav {position: relative; width: 100%; height: auto; padding-top: 10px;}
    main {margin-left: 0; padding: 15px;}
    .admin-sections {grid-template-columns: 1fr;}
    .stats-grid {grid-template-columns: repeat(2, 1fr);}
  }
</style>
</head>
<body>

<header>
  Indian Air Force Human Management System - Administration
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="personnel_list.php">Personnel List</a>
  <?php if (in_array($user_role, ['HR Manager', 'Training Department'])): ?>
    <a href="training_management.php">Training Management</a>
  <?php endif; ?>
  <?php if (in_array($user_role, ['HR Manager', 'Training Department', 'Commander'])): ?>
    <a href="skill_assessment.php">Skill Assessment</a>
  <?php endif; ?>
  <?php if (in_array($user_role, ['HR Manager', 'Commander', 'Ground Staff'])): ?>
    <a href="deployment_management.php">Deployments</a>
  <?php endif; ?>
  <a href="profile.php">Profile</a>
  <a href="analytics.php">Analytics</a>
  <a href="reports.php">Reports</a>
  <a href="notifications.php">Notifications</a>
  <?php if (in_array($user_role, ['HR Manager', 'Commander'])): ?>
    <a href="admin.php">Administration</a>
  <?php endif; ?>
  <a href="logout.php">Logout</a>
</nav>

<main>
  <div class="admin-header">
    <h1>System Administration</h1>
    <p>Manage users, system settings, and monitor system activity</p>
  </div>
  
  <!-- Display Messages -->
  <?php foreach ($messages as $message): ?>
    <div class="message <?php echo $message['type']; ?>">
      <?php echo htmlspecialchars($message['text']); ?>
    </div>
  <?php endforeach; ?>
  
  <!-- System Statistics -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats['total_users']; ?></div>
      <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats['total_personnel']; ?></div>
      <div class="stat-label">Personnel Records</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats['active_deployments']; ?></div>
      <div class="stat-label">Active Deployments</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats['pending_notifications']; ?></div>
      <div class="stat-label">Pending Notifications</div>
    </div>
  </div>
  
  <!-- Administration Sections -->
  <div class="admin-sections">
    <!-- User Management -->
    <div class="admin-card">
      <h3>Create New User</h3>
      <form method="POST" action="">
        <input type="hidden" name="create_user" value="1">
        
        <div class="form-group">
          <label for="full_name">Full Name:</label>
          <input type="text" id="full_name" name="full_name" required>
        </div>
        
        <div class="form-group">
          <label for="email">Email:</label>
          <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
          <label for="role">Role:</label>
          <select name="role" id="role" required>
            <option value="">-- Select Role --</option>
            <option value="Commander">Commander</option>
            <option value="HR Manager">HR Manager</option>
            <option value="Medical Officer">Medical Officer</option>
            <option value="Training Department">Training Department</option>
            <option value="Ground Staff">Ground Staff</option>
          </select>
        </div>
        
        <button type="submit" class="btn btn-success">Create User</button>
      </form>
    </div>
    
    <!-- System Notifications -->
    <div class="admin-card">
      <h3>Send System Notification</h3>
      <form method="POST" action="">
        <input type="hidden" name="send_notification" value="1">
        
        <div class="form-group">
          <label for="target_users">Target Users:</label>
          <select name="target_users" id="target_users" required>
            <option value="all">All Users</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="notification_title">Title:</label>
          <input type="text" id="notification_title" name="notification_title" required placeholder="Notification title">
        </div>
        
        <div class="form-group">
          <label for="notification_message">Message:</label>
          <textarea id="notification_message" name="notification_message" required placeholder="Notification message"></textarea>
        </div>
        
        <div class="form-group">
          <label for="notify_type">Type:</label>
          <select name="notify_type" id="notify_type" required>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="success">Success</option>
            <option value="error">Error</option>
          </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Send Notification</button>
      </form>
    </div>
  </div>
  
  <!-- User Management Table -->
  <div class="admin-card">
    <h3>System Users</h3>
    <table class="users-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Joined</th>
          <th>Activity</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($users_result && $users_result->num_rows > 0): ?>
          <?php while ($user = $users_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($user['full_name']); ?></td>
              <td><?php echo htmlspecialchars($user['email']); ?></td>
              <td>
                <span class="role-badge role-<?php echo strtolower(str_replace(' ', '', $user['role'])); ?>">
                  <?php echo htmlspecialchars($user['role']); ?>
                </span>
              </td>
              <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
              <td>
                <?php echo $user['activity_count']; ?> actions
                <?php if ($user['unread_notifications'] > 0): ?>
                  <br><small style="color: #dc3545;"><?php echo $user['unread_notifications']; ?> unread</small>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password for this user?')">
                  <input type="hidden" name="reset_user_password" value="1">
                  <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                  <button type="submit" class="btn btn-warning" style="padding: 6px 12px; font-size: 0.8rem;">Reset Password</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center; color: #666;">No users found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Recent Activity -->
  <div class="admin-card">
    <h3>Recent System Activity</h3>
    <div class="audit-log">
      <?php if (!empty($audit_logs)): ?>
        <?php foreach ($audit_logs as $log): ?>
          <div class="audit-item">
            <span class="audit-user"><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></span>
            <?php echo htmlspecialchars($log['action']); ?>
            <?php if ($log['personnel_name']): ?>
              (<?php echo htmlspecialchars($log['personnel_name']); ?>)
            <?php endif; ?>
            <div class="audit-time"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align: center; color: #666; padding: 20px;">No recent activity</div>
      <?php endif; ?>
    </div>
  </div>
</main>

</body>
</html>