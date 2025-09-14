<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR Manager', 'Commander', 'Ground Staff'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$messages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_deployment'])) {
        $personnel_id = intval($_POST['personnel_id']);
        $deployment_location = $conn->real_escape_string(trim($_POST['deployment_location']));
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $mission_type = $conn->real_escape_string(trim($_POST['mission_type']));
        $status = $conn->real_escape_string($_POST['status']);
        $remarks = $conn->real_escape_string(trim($_POST['remarks']));
        
        $stmt = $conn->prepare("INSERT INTO deployments (personnel_id, deployment_location, start_date, end_date, mission_type, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $personnel_id, $deployment_location, $start_date, $end_date, $mission_type, $status, $remarks);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => 'Deployment record added successfully.'];
            
            // Log audit trail
            $action = "Added deployment to $deployment_location";
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (personnel_id, user_id, action) VALUES (?, ?, ?)");
            $audit_stmt->bind_param("iis", $personnel_id, $user_id, $action);
            $audit_stmt->execute();
            $audit_stmt->close();
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to add deployment record.'];
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_deployment'])) {
        $deployment_id = intval($_POST['deployment_id']);
        $deployment_location = $conn->real_escape_string(trim($_POST['deployment_location']));
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $mission_type = $conn->real_escape_string(trim($_POST['mission_type']));
        $status = $conn->real_escape_string($_POST['status']);
        $remarks = $conn->real_escape_string(trim($_POST['remarks']));
        
        $stmt = $conn->prepare("UPDATE deployments SET deployment_location = ?, start_date = ?, end_date = ?, mission_type = ?, status = ?, remarks = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $deployment_location, $start_date, $end_date, $mission_type, $status, $remarks, $deployment_id);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => 'Deployment record updated successfully.'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to update deployment record.'];
        }
        $stmt->close();
    }
    
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $stmt = $conn->prepare("DELETE FROM deployments WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => 'Deployment record deleted successfully.'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to delete deployment record.'];
        }
        $stmt->close();
    }
}

// Fetch personnel for dropdown
$personnel_list = [];
$result = $conn->query("SELECT id, full_name, rank_level, current_unit FROM personnel ORDER BY full_name ASC");
while ($row = $result->fetch_assoc()) {
    $personnel_list[] = $row;
}

// Fetch deployments with filters
$search_personnel = isset($_GET['search_personnel']) ? intval($_GET['search_personnel']) : 0;
$search_status = isset($_GET['search_status']) ? $conn->real_escape_string($_GET['search_status']) : '';
$search_location = isset($_GET['search_location']) ? $conn->real_escape_string($_GET['search_location']) : '';

$where_conditions = [];
if ($search_personnel > 0) {
    $where_conditions[] = "d.personnel_id = $search_personnel";
}
if (!empty($search_status)) {
    $where_conditions[] = "d.status = '$search_status'";
}
if (!empty($search_location)) {
    $where_conditions[] = "d.deployment_location LIKE '%$search_location%'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$deployments_sql = "
    SELECT d.*, p.full_name, p.rank_level, p.current_unit,
    DATEDIFF(COALESCE(d.end_date, CURDATE()), d.start_date) as deployment_days
    FROM deployments d
    JOIN personnel p ON d.personnel_id = p.id
    $where_clause
    ORDER BY d.status DESC, d.start_date DESC
";

$deployments_result = $conn->query($deployments_sql);

// Get deployment statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_deployments,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_deployments,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_deployments,
        AVG(DATEDIFF(COALESCE(end_date, CURDATE()), start_date)) as avg_deployment_days
    FROM deployments
";
$stats_result = $conn->query($stats_sql)->fetch_assoc();

// Common deployment locations
$locations = [
    'Domestic Bases' => ['Delhi', 'Mumbai', 'Bangalore', 'Pune', 'Gwalior', 'Jodhpur', 'Pathankot', 'Ambala'],
    'Border Areas' => ['Ladakh', 'Siachen', 'Rajasthan Border', 'Eastern Sector', 'Kashmir Valley'],
    'International' => ['UN Peacekeeping', 'Training Exchange', 'Joint Exercises', 'Diplomatic Mission']
];

$mission_types = ['Combat Operations', 'Training Mission', 'Peacekeeping', 'Humanitarian Aid', 'Border Security', 'Air Defense', 'Transport Operations', 'Search & Rescue'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Deployment Management - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; margin:0; background:#f4f4f9;}
  header {background:#007BFF; color:#fff; padding:15px 20px; text-align:center; font-size:1.5rem;}
  nav {background:#003366; color:#fff; width:220px; height:100vh; position:fixed; top:0; left:0; padding-top:60px; box-sizing:border-box;}
  nav a {display:block; color:#fff; padding:15px 25px; text-decoration:none; font-weight:bold; border-left:5px solid transparent; transition: background 0.3s, border-color 0.3s;}
  nav a:hover {background:#0056b3; border-left:5px solid #FFD700;}
  main {margin-left:220px; padding:25px; min-height:100vh; box-sizing:border-box;}
  
  .page-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;}
  .page-title {font-size: 2rem; color: #007BFF; margin: 0;}
  
  .stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;}
  .stat-card {background: linear-gradient(135deg, #007BFF, #0056b3); color: white; padding: 25px; border-radius: 8px; text-align: center; box-shadow: 0 4px 15px rgba(0,123,255,0.3);}
  .stat-value {font-size: 2.2rem; font-weight: bold; margin-bottom: 8px;}
  .stat-label {font-size: 0.9rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;}
  
  .content-grid {display: grid; grid-template-columns: 1fr 2fr; gap: 25px; margin-bottom: 30px;}
  .card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  
  .form-section h3 {color: #007BFF; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px;}
  .form-group {margin-bottom: 20px;}
  .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333;}
  .form-group select, .form-group input, .form-group textarea {
    width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; transition: border-color 0.3s;
  }
  .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
    border-color: #007BFF; outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
  }
  .form-group textarea {resize: vertical; min-height: 80px;}
  
  .btn {padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: bold; transition: all 0.3s ease; text-decoration: none; display: inline-block;}
  .btn-primary {background: #007BFF; color: white; box-shadow: 0 2px 5px rgba(0,123,255,0.3);}
  .btn-primary:hover {background: #0056b3; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,123,255,0.4);}
  .btn-success {background: #28a745; color: white;}
  .btn-success:hover {background: #218838;}
  .btn-warning {background: #ffc107; color: #212529;}
  .btn-warning:hover {background: #e0a800;}
  .btn-danger {background: #dc3545; color: white;}
  .btn-danger:hover {background: #c82333;}
  
  .search-filters {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;}
  .filter-row {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;}
  .filter-group label {display: block; margin-bottom: 5px; font-weight: 600; color: #333;}
  .filter-group select, .filter-group input {padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: border-color 0.3s;}
  .filter-group select:focus, .filter-group input:focus {border-color: #007BFF;}
  
  .deployments-table {width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .deployments-table th, .deployments-table td {padding: 15px; text-align: left; border-bottom: 1px solid #eee;}
  .deployments-table th {background: linear-gradient(135deg, #f8f9fa, #e9ecef); font-weight: bold; color: #333; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;}
  .deployments-table tr:hover {background: #f8f9fa; transform: scale(1.001); transition: all 0.2s;}
  
  .status-badge {padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;}
  .status-active {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
  .status-completed {background: #cce5ff; color: #004085; border: 1px solid #b8daff;}
  .status-cancelled {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
  
  .location-suggestions {margin-bottom: 15px;}
  .suggestion-group {margin-bottom: 12px;}
  .suggestion-title {font-weight: bold; color: #007BFF; margin-bottom: 6px; font-size: 0.9rem;}
  .suggestion-tags {display: flex; flex-wrap: wrap; gap: 6px;}
  .suggestion-tag {background: #e9ecef; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; cursor: pointer; transition: all 0.3s;}
  .suggestion-tag:hover {background: #007BFF; color: white; transform: translateY(-1px);}
  
  .mission-types {display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;}
  .mission-tag {background: #f1f3f4; padding: 6px 12px; border-radius: 15px; font-size: 0.85rem; cursor: pointer; transition: all 0.3s; border: 1px solid #ddd;}
  .mission-tag:hover {background: #007BFF; color: white; border-color: #007BFF;}
  
  .message {padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid;}
  .message.success {background: #d4edda; color: #155724; border-left-color: #28a745;}
  .message.error {background: #f8d7da; color: #721c24; border-left-color: #dc3545;}
  
  .no-data {text-align: center; color: #666; padding: 50px; font-style: italic; font-size: 1.1rem;}
  
  .deployment-duration {font-size: 0.9rem; color: #666; margin-top: 3px;}
  
  @media (max-width: 768px) {
    nav {position: relative; width: 100%; height: auto; padding-top: 10px;}
    main {margin-left: 0; padding: 15px;}
    .content-grid {grid-template-columns: 1fr;}
    .stats-grid {grid-template-columns: repeat(2, 1fr);}
    .filter-row {grid-template-columns: 1fr;}
    .page-header {flex-direction: column; gap: 15px; align-items: stretch;}
    .suggestion-tags, .mission-types {justify-content: center;}
  }
</style>
</head>
<body>

<header>
  Indian Air Force Human Management System - Deployment Management
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="personnel_list.php">Personnel List</a>
  <?php if (in_array($user_role, ['HR Manager', 'Training Department'])): ?>
    <a href="training_management.php">Training Management</a>
  <?php endif; ?>
  <a href="skill_assessment.php">Skill Assessment</a>
  <a href="deployment_management.php">Deployments</a>
  <a href="profile.php">Profile</a>
  <a href="analytics.php">Analytics</a>
  <a href="reports.php">Reports</a>
  <a href="logout.php">Logout</a>
</nav>

<main>
  <div class="page-header">
    <h1 class="page-title">Deployment Management</h1>
  </div>
  
  <!-- Display Messages -->
  <?php foreach ($messages as $message): ?>
    <div class="message <?php echo $message['type']; ?>">
      <?php echo htmlspecialchars($message['text']); ?>
    </div>
  <?php endforeach; ?>
  
  <!-- Deployment Statistics -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats_result['total_deployments'] ?? 0; ?></div>
      <div class="stat-label">Total Deployments</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats_result['active_deployments'] ?? 0; ?></div>
      <div class="stat-label">Active Deployments</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats_result['completed_deployments'] ?? 0; ?></div>
      <div class="stat-label">Completed Deployments</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?php echo round($stats_result['avg_deployment_days'] ?? 0); ?></div>
      <div class="stat-label">Avg Days</div>
    </div>
  </div>
  
  <div class="content-grid">
    <!-- Deployment Form -->
    <div class="card">
      <div class="form-section">
        <h3>Add New Deployment</h3>
        
        <!-- Location Suggestions -->
        <div class="location-suggestions">
          <?php foreach ($locations as $category => $places): ?>
            <div class="suggestion-group">
              <div class="suggestion-title"><?php echo $category; ?></div>
              <div class="suggestion-tags">
                <?php foreach ($places as $place): ?>
                  <span class="suggestion-tag" onclick="selectLocation('<?php echo htmlspecialchars($place); ?>')"><?php echo htmlspecialchars($place); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <form method="POST" action="" id="deploymentForm">
          <input type="hidden" name="add_deployment" value="1">
          <input type="hidden" name="deployment_id" id="deployment_id" value="">
          
          <div class="form-group">
            <label for="personnel_id">Select Personnel:</label>
            <select name="personnel_id" id="personnel_id" required>
              <option value="">-- Select Personnel --</option>
              <?php foreach ($personnel_list as $person): ?>
                <option value="<?php echo $person['id']; ?>">
                  <?php echo htmlspecialchars($person['full_name'] . ' (' . $person['rank_level'] . ' - ' . $person['current_unit'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="deployment_location">Deployment Location:</label>
            <input type="text" name="deployment_location" id="deployment_location" required placeholder="Enter deployment location">
          </div>
          
          <div class="form-group">
            <label for="mission_type">Mission Type:</label>
            <input type="text" name="mission_type" id="mission_type" required placeholder="Select or enter mission type">
            <div class="mission-types">
              <?php foreach ($mission_types as $type): ?>
                <span class="mission-tag" onclick="selectMissionType('<?php echo htmlspecialchars($type); ?>')"><?php echo htmlspecialchars($type); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          
          <div class="form-group">
            <label for="start_date">Start Date:</label>
            <input type="date" name="start_date" id="start_date" required>
          </div>
          
          <div class="form-group">
            <label for="end_date">End Date (Optional):</label>
            <input type="date" name="end_date" id="end_date">
          </div>
          
          <div class="form-group">
            <label for="status">Status:</label>
            <select name="status" id="status" required>
              <option value="">-- Select Status --</option>
              <option value="Active" selected>Active</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="remarks">Remarks/Notes:</label>
            <textarea name="remarks" id="remarks" placeholder="Additional information, special instructions, or notes..."></textarea>
          </div>
          
          <button type="submit" class="btn btn-primary" id="submitBtn">Add Deployment</button>
          <button type="button" class="btn btn-warning" id="cancelBtn" onclick="resetForm()" style="display: none;">Cancel Edit</button>
        </form>
      </div>
    </div>
    
    <!-- Search and Filter -->
    <div class="card">
      <h3>Search & Filter Deployments</h3>
      <div class="search-filters">
        <form method="GET" action="">
          <div class="filter-row">
            <div class="filter-group">
              <label for="search_personnel">Personnel:</label>
              <select name="search_personnel" id="search_personnel">
                <option value="">-- All Personnel --</option>
                <?php foreach ($personnel_list as $person): ?>
                  <option value="<?php echo $person['id']; ?>" <?php echo ($search_personnel == $person['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($person['full_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="search_status">Status:</label>
              <select name="search_status" id="search_status">
                <option value="">-- All Status --</option>
                <option value="Active" <?php echo ($search_status == 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Completed" <?php echo ($search_status == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                <option value="Cancelled" <?php echo ($search_status == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label for="search_location">Location:</label>
              <input type="text" name="search_location" id="search_location" value="<?php echo htmlspecialchars($search_location); ?>" placeholder="Search by location">
            </div>
            
            <div class="filter-group">
              <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Deployments Table -->
  <div class="card">
    <h3>Deployment Records</h3>
    <?php if ($deployments_result && $deployments_result->num_rows > 0): ?>
      <table class="deployments-table">
        <thead>
          <tr>
            <th>Personnel</th>
            <th>Location</th>
            <th>Mission Type</th>
            <th>Duration</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($deployment = $deployments_result->fetch_assoc()): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($deployment['full_name']); ?></strong><br>
                <small style="color: #666;"><?php echo htmlspecialchars($deployment['rank_level'] . ' / ' . $deployment['current_unit']); ?></small>
              </td>
              <td><?php echo htmlspecialchars($deployment['deployment_location']); ?></td>
              <td><?php echo htmlspecialchars($deployment['mission_type']); ?></td>
              <td>
                <?php echo date('M j, Y', strtotime($deployment['start_date'])); ?>
                <?php if ($deployment['end_date']): ?>
                  <br><small style="color: #666;">to <?php echo date('M j, Y', strtotime($deployment['end_date'])); ?></small>
                <?php else: ?>
                  <br><small style="color: #666;">Ongoing</small>
                <?php endif; ?>
                <div class="deployment-duration">
                  <?php echo $deployment['deployment_days']; ?> days
                </div>
              </td>
              <td>
                <span class="status-badge status-<?php echo strtolower($deployment['status']); ?>">
                  <?php echo htmlspecialchars($deployment['status']); ?>
                </span>
              </td>
              <td>
                <button class="btn btn-warning" onclick="editDeployment(<?php echo htmlspecialchars(json_encode($deployment)); ?>)" style="padding: 8px 12px; margin-right: 5px; font-size: 0.85rem;">Edit</button>
                <a href="?delete_id=<?php echo $deployment['id']; ?>" class="btn btn-danger" style="padding: 8px 12px; font-size: 0.85rem;" onclick="return confirm('Delete this deployment record?')">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="no-data">No deployment records found matching your criteria.</div>
    <?php endif; ?>
  </div>
</main>

<script>
function selectLocation(location) {
    document.getElementById('deployment_location').value = location;
}

function selectMissionType(missionType) {
    document.getElementById('mission_type').value = missionType;
}

function editDeployment(deployment) {
    // Fill form with deployment data
    document.getElementById('deployment_id').value = deployment.id;
    document.getElementById('personnel_id').value = deployment.personnel_id;
    document.getElementById('deployment_location').value = deployment.deployment_location;
    document.getElementById('mission_type').value = deployment.mission_type;
    document.getElementById('start_date').value = deployment.start_date;
    document.getElementById('end_date').value = deployment.end_date || '';
    document.getElementById('status').value = deployment.status;
    document.getElementById('remarks').value = deployment.remarks || '';
    
    // Update form for editing
    document.getElementById('submitBtn').textContent = 'Update Deployment';
    document.getElementById('cancelBtn').style.display = 'inline-block';
    document.querySelector('input[name="add_deployment"]').name = 'update_deployment';
    
    // Scroll to form
    document.getElementById('deploymentForm').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('deploymentForm').reset();
    document.getElementById('deployment_id').value = '';
    document.getElementById('submitBtn').textContent = 'Add Deployment';
    document.getElementById('cancelBtn').style.display = 'none';
    document.querySelector('input[name="update_deployment"]').name = 'add_deployment';
    
    // Reset status to Active by default
    document.getElementById('status').value = 'Active';
}

// Auto-set end date when status is completed
document.getElementById('status').addEventListener('change', function() {
    if (this.value === 'Completed' && !document.getElementById('end_date').value) {
        document.getElementById('end_date').value = new Date().toISOString().slice(0,10);
    }
});

// Validate dates
document.getElementById('end_date').addEventListener('change', function() {
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(this.value);
    
    if (endDate < startDate) {
        alert('End date cannot be earlier than start date');
        this.value = '';
    }
});
</script>

</body>
</html>