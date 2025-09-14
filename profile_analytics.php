<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$messages = [];

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $conn->real_escape_string(trim($_POST['full_name']));
        $email = $conn->real_escape_string(trim($_POST['email']));

        // Check unique email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->bind_param("si", $email, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $messages[] = ['type' => 'error', 'text' => 'Email address is already in use by another user.'];
        } else {
            $stmt_update = $conn->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $stmt_update->bind_param("ssi", $full_name, $email, $user_id);
            if ($stmt_update->execute()) {
                $_SESSION['full_name'] = $full_name;
                $messages[] = ['type' => 'success', 'text' => 'Profile updated successfully.'];
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Failed to update profile.'];
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }

    // Handle Password Change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $messages[] = ['type' => 'error', 'text' => 'New passwords do not match.'];
        } elseif (strlen($new_password) < 8) {
            $messages[] = ['type' => 'error', 'text' => 'Password must be at least 8 characters long.'];
        } else {
            $stmt_pass = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_pass->bind_param("i", $user_id);
            $stmt_pass->execute();
            $stmt_pass->bind_result($stored_hash);
            $stmt_pass->fetch();
            $stmt_pass->close();

            if (password_verify($current_password, $stored_hash)) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_pass = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt_update_pass->bind_param("si", $new_hash, $user_id);
                if ($stmt_update_pass->execute()) {
                    $messages[] = ['type' => 'success', 'text' => 'Password changed successfully.'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Failed to change password.'];
                }
                $stmt_update_pass->close();
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Current password is incorrect.'];
            }
        }
    }

    // MFA setup redirect
    if (isset($_POST['setup_mfa'])) {
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

// Fetch recent activity, use created_at column
$recent_activity = [];
$res = $conn->query("SELECT action, created_at FROM audit_logs WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}

// Analytics overview (general)
$personnel_total = $conn->query("SELECT COUNT(*) as count FROM personnel")->fetch_assoc()['count'];
$active_deployments = $conn->query("SELECT COUNT(*) as count FROM deployments WHERE end_date IS NULL OR end_date > NOW()")->fetch_assoc()['count'];
$completed_trainings = $conn->query("SELECT COUNT(*) as count FROM training_records WHERE completion_date IS NOT NULL")->fetch_assoc()['count'];

$analytics = [];

// Role-based analytics
if (in_array($role, ['Commander', 'HR Manager'])) {
    // Personnel by rank_level
    $rank_distribution = [];
    $res = $conn->query("SELECT rank_level, COUNT(*) as count FROM personnel GROUP BY rank_level ORDER BY count DESC");
    while ($row = $res->fetch_assoc()) {
        $rank_distribution[] = $row;
    }
    $analytics['rank_distribution'] = $rank_distribution;

    // Medical fitness status
    $fitness_stats = [];
    $res = $conn->query("SELECT medical_fitness_status, COUNT(*) as count FROM personnel GROUP BY medical_fitness_status");
    while ($row = $res->fetch_assoc()) {
        $fitness_stats[] = $row;
    }
    $analytics['fitness_stats'] = $fitness_stats;

    // Training completion trends (last 6 months)
    $training_trends = [];
    $res = $conn->query("SELECT DATE_FORMAT(completion_date, '%Y-%m') as month, COUNT(*) as count FROM training_records WHERE completion_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
    while ($row = $res->fetch_assoc()) {
        $training_trends[] = $row;
    }
    $analytics['training_trends'] = $training_trends;

    // Skill gap analysis
    $skill_gaps = [];
    $res = $conn->query("
        SELECT skill_category, COUNT(DISTINCT tr.personnel_id) as trained_count, 
        (SELECT COUNT(*) FROM personnel) - COUNT(DISTINCT tr.personnel_id) as gap_count
        FROM training_records tr
        JOIN training_sessions ts ON tr.training_session_id = ts.id
        GROUP BY skill_category
    ");
    while ($row = $res->fetch_assoc()) {
        $skill_gaps[] = $row;
    }
    $analytics['skill_gaps'] = $skill_gaps;
}

if ($role === 'Medical Officer') {
    $medical_types = [];
    $res = $conn->query("SELECT medical_type, COUNT(*) as count FROM medical_history GROUP BY medical_type");
    while ($row = $res->fetch_assoc()) {
        $medical_types[] = $row;
    }
    $analytics['medical_types'] = $medical_types;

    $fitness_impact = [];
    $res = $conn->query("SELECT fitness_impact, COUNT(*) as count FROM medical_history GROUP BY fitness_impact");
    while ($row = $res->fetch_assoc()) {
        $fitness_impact[] = $row;
    }
    $analytics['fitness_impact'] = $fitness_impact;
}

if ($role === 'Training Department') {
    $training_effectiveness = [];
    $res = $conn->query("
        SELECT ts.training_name, AVG(CASE WHEN tr.result IN ('Passed','Excellent','Good') THEN 1 ELSE 0 END)*100 as success_rate, COUNT(*) as total_attempts
        FROM training_records tr
        JOIN training_sessions ts ON tr.training_session_id = ts.id
        GROUP BY ts.id, ts.training_name
    ");
    while ($row = $res->fetch_assoc()) {
        $training_effectiveness[] = $row;
    }
    $analytics['training_effectiveness'] = $training_effectiveness;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Profile & Analytics - IAF Human Management</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background: #1f292f;
    color: #d4d7d6;
    margin: 0; padding: 20px;
  }
  h1, h2, h3 {
    font-family: 'Oswald', sans-serif;
    color: #aed581;
  }
  h1 { font-size: 2.5rem; margin-bottom: 10px; }
  h2 { font-size: 2rem; margin-top: 30px; border-bottom: 2px solid #9ccc65; padding-bottom: 6px; }
  h3 { margin-top: 20px; }
  .messages div {
    margin-bottom: 15px;
    padding: 10px 15px;
    border-radius: 5px;
  }
  .messages .success { background: #4caf50; color: #fff; }
  .messages .error { background: #f44336; color: #fff; }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
  }
  th, td {
    border: 1px solid #37474f;
    padding: 10px;
    text-align: left;
  }
  th {
    background: #455a64;
    color: #cddc39;
  }
  a {
    color: #aed581;
    text-decoration: none;
  }
  a:hover {
    color: #cddc39;
    text-decoration: underline;
  }
  form {
    margin-top: 10px;
    max-width: 480px;
  }
  label {
    display: block;
    margin: 10px 0 5px;
  }
  input[type="text"], input[type="email"], input[type="password"] {
    width: 100%;
    padding: 8px 10px;
    border-radius: 3px;
    border: 1px solid #8eacbb;
    background: #263238;
    color: #d4d7d6;
  }
  button {
    margin-top: 15px;
    background-color: #9ccc65;
    color: #263238;
    border: none;
    padding: 10px 20px;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
  }
  button:hover {
    background-color: #aed581;
  }
</style>
<link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet" />
</head>
<body>

<h1>Welcome, <?php echo htmlspecialchars($full_name); ?></h1>

<div class="messages">
    <?php foreach ($messages as $msg): ?>
      <div class="<?php echo htmlspecialchars($msg['type']); ?>">
        <?php echo htmlspecialchars($msg['text']); ?>
      </div>
    <?php endforeach; ?>
</div>

<h2>Your Profile</h2>
<form method="POST">
    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($full_name); ?>" />
    
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" />
    
    <button type="submit" name="update_profile">Update Profile</button>
</form>

<h2>Change Password</h2>
<form method="POST">
    <label for="current_password">Current Password</label>
    <input type="password" id="current_password" name="current_password" required />
    
    <label for="new_password">New Password</label>
    <input type="password" id="new_password" name="new_password" required />
    
    <label for="confirm_password">Confirm New Password</label>
    <input type="password" id="confirm_password" name="confirm_password" required />
    
    <button type="submit" name="change_password">Change Password</button>
</form>

<form method="POST" style="margin-top:20px;">
    <button type="submit" name="setup_mfa">Setup Multi-Factor Authentication</button>
</form>

<h2>Analytics Overview</h2>
<p><strong>Total Personnel:</strong> <?php echo $personnel_total; ?></p>
<p><strong>Active Deployments:</strong> <?php echo $active_deployments; ?></p>
<p><strong>Completed Trainings:</strong> <?php echo $completed_trainings; ?></p>

<?php if (!empty($analytics['rank_distribution'])): ?>
  <h3>Personnel by Rank</h3>
  <table>
    <thead>
      <tr><th>Rank Level</th><th>Count</th></tr>
    </thead>
    <tbody>
      <?php foreach ($analytics['rank_distribution'] as $row): ?>
        <tr><td><?php echo htmlspecialchars($row['rank_level']); ?></td><td><?php echo $row['count']; ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($analytics['fitness_stats'])): ?>
  <h3>Medical Fitness Status</h3>
  <table>
    <thead><tr><th>Status</th><th>Count</th></tr></thead>
    <tbody>
      <?php foreach ($analytics['fitness_stats'] as $row): ?>
        <tr><td><?php echo htmlspecialchars($row['medical_fitness_status']); ?></td><td><?php echo $row['count']; ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($analytics['training_trends'])): ?>
  <h3>Training Completion Trends (Last 6 months)</h3>
  <table>
    <thead><tr><th>Month</th><th>Completions</th></tr></thead>
    <tbody>
      <?php foreach ($analytics['training_trends'] as $row): ?>
        <tr><td><?php echo htmlspecialchars($row['month']); ?></td><td><?php echo $row['count']; ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($analytics['skill_gaps'])): ?>
  <h3>Skill Gap Analysis</h3>
  <table>
    <thead><tr><th>Skill Category</th><th>Trained Count</th><th>Gap Count</th></tr></thead>
    <tbody>
      <?php foreach ($analytics['skill_gaps'] as $row): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['skill_category']); ?></td>
          <td><?php echo $row['trained_count']; ?></td>
          <td><?php echo $row['gap_count']; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($analytics['medical_types'])): ?>
  <h3>Medical History Types</h3>
  <table>
    <thead><tr><th>Type</th><th>Count</th></tr></thead>
    <tbody>
      <?php foreach ($analytics['medical_types'] as $row): ?>
        <tr><td><?php echo htmlspecialchars($row['medical_type']); ?></td><td><?php echo $row['count']; ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($analytics['fitness_impact'])): ?>
  <h3>Fitness Impact Analysis</h3>
  <table>
    <thead><tr><th>Impact</th><th>Count</th></tr></thead>
    <tbody>
      <?php foreach ($analytics['fitness_impact'] as $row): ?>
        <tr><td><?php echo htmlspecialchars($row['fitness_impact']); ?></td><td><?php echo $row['count']; ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($analytics['training_effectiveness'])): ?>
  <h3>Training Effectiveness by Program</h3>
  <table>
    <thead><tr><th>Training Name</th><th>Success Rate (%)</th><th>Total Attempts</th></tr></thead>
    <tbody>
      <?php foreach ($analytics['training_effectiveness'] as $row): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['training_name']); ?></td>
          <td><?php echo number_format($row['success_rate'], 2); ?></td>
          <td><?php echo $row['total_attempts']; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2>Recent Activity</h2>
<?php if ($recent_activity): ?>
  <table>
    <thead><tr><th>Action</th><th>Date/Time</th></tr></thead>
    <tbody>
      <?php foreach ($recent_activity as $activity): ?>
        <tr>
          <td><?php echo htmlspecialchars($activity['action']); ?></td>
          <td><?php echo $activity['created_at']; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No recent activity found.</p>
<?php endif; ?>

</body>
</html>
