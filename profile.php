<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$messages = [];

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $conn->real_escape_string(trim($_POST['full_name']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        
        // Check if email is already taken by another user
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_email->num_rows > 0) {
            $messages[] = ['type' => 'error', 'text' => 'Email address is already in use by another user.'];
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $full_name, $email, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $messages[] = ['type' => 'success', 'text' => 'Profile updated successfully.'];
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Failed to update profile.'];
            }
            $stmt->close();
        }
        $check_email->close();
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $messages[] = ['type' => 'error', 'text' => 'New passwords do not match.'];
        } elseif (strlen($new_password) < 8) {
            $messages[] = ['type' => 'error', 'text' => 'Password must be at least 8 characters long.'];
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($stored_password);
            $stmt->fetch();
            $stmt->close();
            
            if (password_verify($current_password, $stored_password)) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_password_hash, $user_id);
                
                if ($stmt->execute()) {
                    $messages[] = ['type' => 'success', 'text' => 'Password changed successfully.'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Failed to change password.'];
                }
                $stmt->close();
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Current password is incorrect.'];
            }
        }
    }
    
    if (isset($_POST['setup_mfa'])) {
        $messages[] = ['type' => 'info', 'text' => 'Redirecting to MFA setup...'];
        header("Location: mfa_setup.php");
        exit();
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT full_name, email, role, created_at, mfa_secret FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($full_name, $email, $role, $created_at, $mfa_secret);
$stmt->fetch();
$stmt->close();

// Get user activity stats
$login_count = $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE user_id = $user_id AND action LIKE '%login%'")->fetch_assoc()['count'] ?? 0;
$total_actions = $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE user_id = $user_id")->fetch_assoc()['count'] ?? 0;

// Get recent activity
$recent_activity = [];
$result = $conn->query("SELECT action, created_at FROM audit_logs WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Profile - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; margin:0; background:#f4f4f9;}
  header {background:#007BFF; color:#fff; padding:15px 20px; text-align:center; font-size:1.5rem;}
  nav {background:#003366; color:#fff; width:220px; height:100vh; position:fixed; top:0; left:0; padding-top:60px; box-sizing:border-box;}
  nav a {display:block; color:#fff; padding:15px 25px; text-decoration:none; font-weight:bold; border-left:5px solid transparent; transition: background 0.3s, border-color 0.3s;}
  nav a:hover {background:#0056b3; border-left:5px solid #FFD700;}
  main {margin-left:220px; padding:25px; min-height:100vh; box-sizing:border-box;}
  footer {text-align:center; padding:15px; background:#003366; color:#fff; position:fixed; bottom:0; left:220px; right:0;}
  
  .profile-container {display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;}
  .profile-card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .profile-header {text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0;}
  .profile-avatar {width: 80px; height: 80px; background: linear-gradient(135deg, #007BFF, #0056b3); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: white; font-size: 2rem; font-weight: bold;}
  .profile-name {font-size: 1.4rem; font-weight: bold; color: #333; margin-bottom: 5px;}
  .profile-role {color: #007BFF; font-weight: 600;}
  .profile-joined {color: #666; font-size: 0.9rem; margin-top: 10px;}
  
  .form-section {margin-bottom: 30px;}
  .form-section h3 {color: #007BFF; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px;}
  .form-group {margin-bottom: 20px;}
  .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333;}
  .form-group input {width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;}
  .form-group input:focus {border-color: #007BFF; outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,0.25);}
  
  .btn {padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: bold; transition: all 0.3s ease;}
  .btn-primary {background: #007BFF; color: white;}
  .btn-primary:hover {background: #0056b3; transform: translateY(-1px);}
  .btn-secondary {background: #6c757d; color: white;}
  .btn-secondary:hover {background: #5a6268;}
  .btn-success {background: #28a745; color: white;}
  .btn-success:hover {background: #218838;}
  
  .stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;}
  .stat-item {background: linear-gradient(135deg, #007BFF, #0056b3); color: white; padding: 20px; border-radius: 8px; text-align: center;}
  .stat-value {font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;}
  .stat-label {font-size: 0.9rem; opacity: 0.9;}
  
  .activity-list {max-height: 300px; overflow-y: auto;}
  .activity-item {padding: 12px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;}
  .activity-item:last-child {border-bottom: none;}
  .activity-action {color: #333; font-weight: 500;}
  .activity-time {color: #666; font-size: 0.9rem;}
  
  .message {padding: 12px 20px; border-radius: 4px; margin-bottom: 20px;}
  .message.success {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
  .message.error {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
  .message.info {background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;}
  
  .security-status {display: flex; align-items: center; gap: 10px; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-bottom: 20px;}
  .security-icon {width: 20px; height: 20px; border-radius: 50%;}
  .security-enabled {background: #28a745;}
  .security-disabled {background: #dc3545;}
  
  @media (max-width: 768px) {
    nav {position: relative; width: 100%; height: auto; padding-top: 10px;}
    main {margin-left: 0; padding: 15px;}
    footer {left: 0; position: relative;}
    .profile-container {grid-template-columns: 1fr;}
    .stats-grid {grid-template-columns: repeat(2, 1fr);}
  }
</style>
</head>
<body>

<header>
  Indian Air Force Human Management System - My Profile
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="personnel_list.php">Personnel List</a>
  <?php if (in_array($user_role, ['HR Manager', 'Training Department'])): ?>
    <a href="training_management.php">Training Management</a>
  <?php endif; ?>
  <a href="profile.php">Profile</a>
  <a href="analytics.php">Analytics</a>
  <a href="reports.php">Reports</a>
  <a href="logout.php">Logout</a>
</nav>

<main>
  <h1>My Profile</h1>
  
  <!-- Display Messages -->
  <?php foreach ($messages as $message): ?>
    <div class="message <?php echo $message['type']; ?>">
      <?php echo htmlspecialchars($message['text']); ?>
    </div>
  <?php endforeach; ?>
  
  <div class="profile-container">
    <!-- Profile Information Card -->
    <div class="profile-card">
      <div class="profile-header">
        <div class="profile-avatar">
          <?php echo strtoupper(substr($full_name, 0, 2)); ?>
        </div>
        <div class="profile-name"><?php echo htmlspecialchars($full_name); ?></div>
        <div class="profile-role"><?php echo htmlspecialchars($role); ?></div>
        <div class="profile-joined">Member since <?php echo date('M Y', strtotime($created_at)); ?></div>
      </div>
      
      <!-- Account Statistics -->
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value"><?php echo $total_actions; ?></div>
          <div class="stat-label">Total Actions</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?php echo $login_count; ?></div>
          <div class="stat-label">Logins</div>
        </div>
      </div>
      
      <!-- Security Status -->
      <div class="security-status">
        <div class="security-icon <?php echo !empty($mfa_secret) ? 'security-enabled' : 'security-disabled'; ?>"></div>
        <div>
          <strong>Multi-Factor Authentication:</strong>
          <?php echo !empty($mfa_secret) ? 'Enabled' : 'Disabled'; ?>
        </div>
      </div>
      
      <?php if (empty($mfa_secret)): ?>
        <form method="POST" action="">
          <input type="hidden" name="setup_mfa" value="1">
          <button type="submit" class="btn btn-success" style="width: 100%;">Enable MFA</button>
        </form>
      <?php endif; ?>
    </div>
    
    <!-- Profile Management Forms -->
    <div class="profile-card">
      <!-- Update Profile Form -->
      <div class="form-section">
        <h3>Update Profile Information</h3>
        <form method="POST" action="">
          <input type="hidden" name="update_profile" value="1">
          
          <div class="form-group">
            <label for="full_name">Full Name:</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
          </div>
          
          <div class="form-group">
            <label>Role:</label>
            <input type="text" value="<?php echo htmlspecialchars($role); ?>" readonly style="background: #f8f9fa;">
          </div>
          
          <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
      </div>
      
      <!-- Change Password Form -->
      <div class="form-section">
        <h3>Change Password</h3>
        <form method="POST" action="">
          <input type="hidden" name="change_password" value="1">
          
          <div class="form-group">
            <label for="current_password">Current Password:</label>
            <input type="password" id="current_password" name="current_password" required>
          </div>
          
          <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
          </div>
          
          <button type="submit" class="btn btn-secondary">Change Password</button>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Recent Activity -->
  <div class="profile-card">
    <h3>Recent Activity</h3>
    <?php if (!empty($recent_activity)): ?>
      <div class="activity-list">
        <?php foreach ($recent_activity as $activity): ?>
          <div class="activity-item">
            <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
            <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="no-data" style="text-align: center; color: #666; padding: 20px;">No recent activity found.</div>
    <?php endif; ?>
  </div>
</main>

<footer>
  &copy; 2025 Indian Air Force Human Management System
</footer>

<script>
// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthIndicator = document.getElementById('password-strength') || createStrengthIndicator();
    
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) strength++; else feedback.push('At least 8 characters');
    if (/[a-z]/.test(password)) strength++; else feedback.push('Lowercase letter');
    if (/[A-Z]/.test(password)) strength++; else feedback.push('Uppercase letter');
    if (/\d/.test(password)) strength++; else feedback.push('Number');
    if (/[^a-zA-Z\d]/.test(password)) strength++; else feedback.push('Special character');
    
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    
    strengthIndicator.style.background = colors[strength - 1] || '#dc3545';
    strengthIndicator.textContent = labels[strength - 1] || 'Very Weak';
    
    function createStrengthIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'password-strength';
        indicator.style.cssText = 'margin-top: 5px; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; color: white; text-align: center;';
        document.getElementById('new_password').parentNode.appendChild(indicator);
        return indicator;
    }
});

// Confirm password match
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.style.borderColor = '#dc3545';
        this.setCustomValidity('Passwords do not match');
    } else {
        this.style.borderColor = '#ddd';
        this.setCustomValidity('');
    }
});
</script>

</body>
</html>