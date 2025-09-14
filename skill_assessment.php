<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR Manager', 'Training Department', 'Commander'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$messages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_assessment'])) {
        $personnel_id = intval($_POST['personnel_id']);
        $skill_name = $conn->real_escape_string(trim($_POST['skill_name']));
        $proficiency_level = $conn->real_escape_string($_POST['proficiency_level']);
        $assessment_date = $_POST['assessment_date'];
        $notes = $conn->real_escape_string(trim($_POST['notes']));
        
        $stmt = $conn->prepare("INSERT INTO skill_assessments (personnel_id, skill_name, proficiency_level, assessment_date, assessor_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssds", $personnel_id, $skill_name, $proficiency_level, $assessment_date, $user_id, $notes);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => 'Skill assessment added successfully.'];
            
            // Log audit trail
            $action = "Added skill assessment for skill: $skill_name";
            $audit_stmt = $conn->prepare("INSERT INTO audit_logs (personnel_id, user_id, action) VALUES (?, ?, ?)");
            $audit_stmt->bind_param("iis", $personnel_id, $user_id, $action);
            $audit_stmt->execute();
            $audit_stmt->close();
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to add skill assessment.'];
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_assessment'])) {
        $assessment_id = intval($_POST['assessment_id']);
        $skill_name = $conn->real_escape_string(trim($_POST['skill_name']));
        $proficiency_level = $conn->real_escape_string($_POST['proficiency_level']);
        $assessment_date = $_POST['assessment_date'];
        $notes = $conn->real_escape_string(trim($_POST['notes']));
        
        $stmt = $conn->prepare("UPDATE skill_assessments SET skill_name = ?, proficiency_level = ?, assessment_date = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $skill_name, $proficiency_level, $assessment_date, $notes, $assessment_id);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => 'Skill assessment updated successfully.'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to update skill assessment.'];
        }
        $stmt->close();
    }
    
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $stmt = $conn->prepare("DELETE FROM skill_assessments WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $messages[] = ['type' => 'success', 'text' => 'Skill assessment deleted successfully.'];
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Failed to delete skill assessment.'];
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

// Fetch skill assessments with personnel details
$search_personnel = isset($_GET['search_personnel']) ? intval($_GET['search_personnel']) : 0;
$search_skill = isset($_GET['search_skill']) ? $conn->real_escape_string($_GET['search_skill']) : '';

$where_conditions = [];
if ($search_personnel > 0) {
    $where_conditions[] = "sa.personnel_id = $search_personnel";
}
if (!empty($search_skill)) {
    $where_conditions[] = "sa.skill_name LIKE '%$search_skill%'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$assessments_sql = "
    SELECT sa.*, p.full_name, p.rank_level, p.current_unit, u.full_name as assessor_name
    FROM skill_assessments sa
    JOIN personnel p ON sa.personnel_id = p.id
    LEFT JOIN users u ON sa.assessor_id = u.id
    $where_clause
    ORDER BY sa.assessment_date DESC, p.full_name ASC
";

$assessments_result = $conn->query($assessments_sql);

// Get skill categories for quick add
$common_skills = [
    'Aviation' => ['Fighter Pilot', 'Transport Pilot', 'Navigator', 'Flight Engineer'],
    'Technical' => ['Aircraft Maintenance', 'Avionics', 'Radar Systems', 'Communications'],
    'Combat' => ['Tactical Operations', 'Air Defense', 'Combat Training', 'Weapon Systems'],
    'Leadership' => ['Team Leadership', 'Strategic Planning', 'Decision Making', 'Personnel Management'],
    'IT & Cyber' => ['Cybersecurity', 'Network Administration', 'Software Development', 'Data Analysis']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Skill Assessment Management - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; margin:0; background:#f4f4f9;}
  header {background:#007BFF; color:#fff; padding:15px 20px; text-align:center; font-size:1.5rem;}
  nav {background:#003366; color:#fff; width:220px; height:100vh; position:fixed; top:0; left:0; padding-top:60px; box-sizing:border-box;}
  nav a {display:block; color:#fff; padding:15px 25px; text-decoration:none; font-weight:bold; border-left:5px solid transparent; transition: background 0.3s, border-color 0.3s;}
  nav a:hover {background:#0056b3; border-left:5px solid #FFD700;}
  main {margin-left:220px; padding:25px; min-height:100vh; box-sizing:border-box;}
  
  .page-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;}
  .page-title {font-size: 2rem; color: #007BFF; margin: 0;}
  
  .content-grid {display: grid; grid-template-columns: 1fr 2fr; gap: 25px; margin-bottom: 30px;}
  .card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  
  .form-section h3 {color: #007BFF; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px;}
  .form-group {margin-bottom: 20px;}
  .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333;}
  .form-group select, .form-group input, .form-group textarea {
    width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;
  }
  .form-group textarea {resize: vertical; min-height: 80px;}
  
  .btn {padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: bold; transition: all 0.3s ease; text-decoration: none; display: inline-block;}
  .btn-primary {background: #007BFF; color: white;}
  .btn-primary:hover {background: #0056b3;}
  .btn-success {background: #28a745; color: white;}
  .btn-success:hover {background: #218838;}
  .btn-warning {background: #ffc107; color: #212529;}
  .btn-warning:hover {background: #e0a800;}
  .btn-danger {background: #dc3545; color: white;}
  .btn-danger:hover {background: #c82333;}
  
  .search-filters {background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;}
  .filter-row {display: flex; gap: 15px; align-items: end;}
  .filter-group {flex: 1;}
  .filter-group label {display: block; margin-bottom: 5px; font-weight: 600; color: #333;}
  .filter-group select, .filter-group input {padding: 8px; border: 1px solid #ddd; border-radius: 4px;}
  
  .assessments-table {width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .assessments-table th, .assessments-table td {padding: 15px; text-align: left; border-bottom: 1px solid #eee;}
  .assessments-table th {background: #f8f9fa; font-weight: bold; color: #333;}
  .assessments-table tr:hover {background: #f1f3f4;}
  
  .proficiency-badge {padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase;}
  .proficiency-beginner {background: #ffc107; color: #212529;}
  .proficiency-intermediate {background: #17a2b8; color: white;}
  .proficiency-advanced {background: #28a745; color: white;}
  .proficiency-expert {background: #6f42c1; color: white;}
  
  .skill-categories {margin-bottom: 20px;}
  .category-group {margin-bottom: 15px;}
  .category-title {font-weight: bold; color: #007BFF; margin-bottom: 8px;}
  .skill-tags {display: flex; flex-wrap: wrap; gap: 8px;}
  .skill-tag {background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem; cursor: pointer; transition: background 0.3s;}
  .skill-tag:hover {background: #007BFF; color: white;}
  
  .message {padding: 12px 20px; border-radius: 4px; margin-bottom: 20px;}
  .message.success {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
  .message.error {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
  
  .no-data {text-align: center; color: #666; padding: 40px; font-style: italic;}
  
  @media (max-width: 768px) {
    nav {position: relative; width: 100%; height: auto; padding-top: 10px;}
    main {margin-left: 0; padding: 15px;}
    .content-grid {grid-template-columns: 1fr;}
    .filter-row {flex-direction: column; align-items: stretch;}
    .page-header {flex-direction: column; gap: 15px; align-items: stretch;}
  }
</style>
</head>
<body>

<header>
  Indian Air Force Human Management System - Skill Assessment
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
    <h1 class="page-title">Skill Assessment Management</h1>
  </div>
  
  <!-- Display Messages -->
  <?php foreach ($messages as $message): ?>
    <div class="message <?php echo $message['type']; ?>">
      <?php echo htmlspecialchars($message['text']); ?>
    </div>
  <?php endforeach; ?>
  
  <div class="content-grid">
    <!-- Assessment Form -->
    <div class="card">
      <div class="form-section">
        <h3>Add New Skill Assessment</h3>
        
        <!-- Common Skills Reference -->
        <div class="skill-categories">
          <?php foreach ($common_skills as $category => $skills): ?>
            <div class="category-group">
              <div class="category-title"><?php echo $category; ?></div>
              <div class="skill-tags">
                <?php foreach ($skills as $skill): ?>
                  <span class="skill-tag" onclick="selectSkill('<?php echo htmlspecialchars($skill); ?>')"><?php echo htmlspecialchars($skill); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <form method="POST" action="" id="assessmentForm">
          <input type="hidden" name="add_assessment" value="1">
          <input type="hidden" name="assessment_id" id="assessment_id" value="">
          
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
            <label for="skill_name">Skill Name:</label>
            <input type="text" name="skill_name" id="skill_name" required placeholder="e.g., Fighter Pilot, Aircraft Maintenance">
          </div>
          
          <div class="form-group">
            <label for="proficiency_level">Proficiency Level:</label>
            <select name="proficiency_level" id="proficiency_level" required>
              <option value="">-- Select Level --</option>
              <option value="Beginner">Beginner</option>
              <option value="Intermediate">Intermediate</option>
              <option value="Advanced">Advanced</option>
              <option value="Expert">Expert</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="assessment_date">Assessment Date:</label>
            <input type="date" name="assessment_date" id="assessment_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="notes">Notes/Comments:</label>
            <textarea name="notes" id="notes" placeholder="Additional observations, recommendations, or comments..."></textarea>
          </div>
          
          <button type="submit" class="btn btn-primary" id="submitBtn">Add Assessment</button>
          <button type="button" class="btn btn-warning" id="cancelBtn" onclick="resetForm()" style="display: none;">Cancel</button>
        </form>
      </div>
    </div>
    
    <!-- Search and Filter -->
    <div class="card">
      <h3>Search & Filter Assessments</h3>
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
            <label for="search_skill">Skill:</label>
            <input type="text" name="search_skill" id="search_skill" value="<?php echo htmlspecialchars($search_skill); ?>" placeholder="Search by skill name">
          </div>
          
          <div class="filter-group">
            <button type="submit" class="btn btn-primary">Search</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Assessments Table -->
  <div class="card">
    <h3>Skill Assessments</h3>
    <?php if ($assessments_result && $assessments_result->num_rows > 0): ?>
      <table class="assessments-table">
        <thead>
          <tr>
            <th>Personnel</th>
            <th>Rank/Unit</th>
            <th>Skill</th>
            <th>Proficiency</th>
            <th>Assessment Date</th>
            <th>Assessor</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($assessment = $assessments_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($assessment['full_name']); ?></td>
              <td><?php echo htmlspecialchars($assessment['rank_level'] . ' / ' . $assessment['current_unit']); ?></td>
              <td><?php echo htmlspecialchars($assessment['skill_name']); ?></td>
              <td>
                <span class="proficiency-badge proficiency-<?php echo strtolower($assessment['proficiency_level']); ?>">
                  <?php echo htmlspecialchars($assessment['proficiency_level']); ?>
                </span>
              </td>
              <td><?php echo date('M j, Y', strtotime($assessment['assessment_date'])); ?></td>
              <td><?php echo htmlspecialchars($assessment['assessor_name'] ?? 'System'); ?></td>
              <td>
                <button class="btn btn-warning" onclick="editAssessment(<?php echo htmlspecialchars(json_encode($assessment)); ?>)" style="padding: 6px 12px; margin-right: 5px;">Edit</button>
                <a href="?delete_id=<?php echo $assessment['id']; ?>" class="btn btn-danger" style="padding: 6px 12px;" onclick="return confirm('Delete this assessment?')">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="no-data">No skill assessments found matching your criteria.</div>
    <?php endif; ?>
  </div>
</main>

<script>
function selectSkill(skillName) {
    document.getElementById('skill_name').value = skillName;
}

function editAssessment(assessment) {
    // Fill form with assessment data
    document.getElementById('assessment_id').value = assessment.id;
    document.getElementById('personnel_id').value = assessment.personnel_id;
    document.getElementById('skill_name').value = assessment.skill_name;
    document.getElementById('proficiency_level').value = assessment.proficiency_level;
    document.getElementById('assessment_date').value = assessment.assessment_date;
    document.getElementById('notes').value = assessment.notes || '';
    
    // Update form for editing
    document.getElementById('submitBtn').textContent = 'Update Assessment';
    document.getElementById('cancelBtn').style.display = 'inline-block';
    document.querySelector('input[name="add_assessment"]').name = 'update_assessment';
    
    // Scroll to form
    document.getElementById('assessmentForm').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('assessmentForm').reset();
    document.getElementById('assessment_id').value = '';
    document.getElementById('submitBtn').textContent = 'Add Assessment';
    document.getElementById('cancelBtn').style.display = 'none';
    document.querySelector('input[name="update_assessment"]').name = 'add_assessment';
}

// Auto-complete for skill names
document.getElementById('skill_name').addEventListener('input', function() {
    // This could be enhanced with AJAX to fetch existing skill names from database
});
</script>

</body>
</html>