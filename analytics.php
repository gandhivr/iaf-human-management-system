<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Fetch analytics data based on role
$analytics_data = [];

// Common analytics for all roles
$total_personnel = $conn->query("SELECT COUNT(*) as count FROM personnel")->fetch_assoc()['count'];
$active_deployments = $conn->query("SELECT COUNT(*) as count FROM deployments WHERE status = 'Active'")->fetch_assoc()['count'];
$total_trainings = $conn->query("SELECT COUNT(*) as count FROM training_records WHERE completion_date IS NOT NULL")->fetch_assoc()['count'];

// Role-specific analytics
if (in_array($user_role, ['Commander', 'HR Manager'])) {
    // Personnel by rank distribution
    $rank_distribution = [];
    $result = $conn->query("SELECT rank_level, COUNT(*) as count FROM personnel GROUP BY rank_level ORDER BY count DESC");
    while ($row = $result->fetch_assoc()) {
        $rank_distribution[] = $row;
    }
    
    // Medical fitness status
    $fitness_stats = [];
    $result = $conn->query("SELECT medical_fitness_status, COUNT(*) as count FROM personnel GROUP BY medical_fitness_status");
    while ($row = $result->fetch_assoc()) {
        $fitness_stats[] = $row;
    }
    
    // Training completion trends (last 6 months)
    $training_trends = [];
    $result = $conn->query("
        SELECT DATE_FORMAT(completion_date, '%Y-%m') as month, COUNT(*) as count 
        FROM training_records 
        WHERE completion_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        GROUP BY DATE_FORMAT(completion_date, '%Y-%m')
        ORDER BY month
    ");
    while ($row = $result->fetch_assoc()) {
        $training_trends[] = $row;
    }
    
    // Skill gap analysis
    $skill_gaps = [];
    $result = $conn->query("
        SELECT skill_category, COUNT(DISTINCT personnel_id) as trained_count,
        (SELECT COUNT(*) FROM personnel) - COUNT(DISTINCT personnel_id) as gap_count
        FROM training_records tr 
        JOIN training_sessions ts ON tr.training_session_id = ts.id 
        GROUP BY skill_category
    ");
    while ($row = $result->fetch_assoc()) {
        $skill_gaps[] = $row;
    }
}

if ($user_role === 'Medical Officer') {
    // Medical records by type
    $medical_types = [];
    $result = $conn->query("SELECT medical_type, COUNT(*) as count FROM medical_history GROUP BY medical_type");
    while ($row = $result->fetch_assoc()) {
        $medical_types[] = $row;
    }
    
    // Fitness impact analysis
    $fitness_impact = [];
    $result = $conn->query("SELECT fitness_impact, COUNT(*) as count FROM medical_history GROUP BY fitness_impact");
    while ($row = $result->fetch_assoc()) {
        $fitness_impact[] = $row;
    }
}

if ($user_role === 'Training Department') {
    // Training effectiveness by program
    $training_effectiveness = [];
    $result = $conn->query("
        SELECT ts.training_name, 
        AVG(CASE WHEN tr.result IN ('Passed', 'Excellent', 'Good') THEN 1 ELSE 0 END) * 100 as success_rate,
        COUNT(*) as total_attempts
        FROM training_records tr 
        JOIN training_sessions ts ON tr.training_session_id = ts.id 
        GROUP BY ts.id, ts.training_name
    ");
    while ($row = $result->fetch_assoc()) {
        $training_effectiveness[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Analytics Dashboard - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; margin:0; background:#f4f4f9;}
  header {background:#007BFF; color:#fff; padding:15px 20px; text-align:center; font-size:1.5rem;}
  nav {background:#003366; color:#fff; width:220px; height:100vh; position:fixed; top:0; left:0; padding-top:60px; box-sizing:border-box;}
  nav a {display:block; color:#fff; padding:15px 25px; text-decoration:none; font-weight:bold; border-left:5px solid transparent; transition: background 0.3s, border-color 0.3s;}
  nav a:hover {background:#0056b3; border-left:5px solid #FFD700;}
  main {margin-left:220px; padding:25px; min-height:100vh; box-sizing:border-box;}
  footer {text-align:center; padding:15px; background:#003366; color:#fff; position:fixed; bottom:0; left:220px; right:0;}
  
  .analytics-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;}
  .metric-card {background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
  .metric-value {font-size: 2rem; font-weight: bold; color: #007BFF;}
  .metric-label {color: #666; margin-top: 5px;}
  
  .chart-container {background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px;}
  .chart-title {font-size: 1.2rem; font-weight: bold; margin-bottom: 15px; color: #333;}
  
  .bar-chart {display: flex; flex-direction: column; gap: 10px;}
  .bar-item {display: flex; align-items: center; gap: 10px;}
  .bar-label {min-width: 120px; font-size: 0.9rem; color: #555;}
  .bar-visual {flex-grow: 1; background: #e9ecef; border-radius: 4px; height: 25px; position: relative; overflow: hidden;}
  .bar-fill {background: linear-gradient(90deg, #007BFF, #0056b3); height: 100%; border-radius: 4px; transition: width 0.3s ease;}
  .bar-value {position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: white; font-size: 0.8rem; font-weight: bold;}
  
  .pie-chart {display: flex; justify-content: center; align-items: center; min-height: 200px;}
  .pie-legend {display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; margin-top: 15px;}
  .legend-item {display: flex; align-items: center; gap: 5px; font-size: 0.9rem;}
  .legend-color {width: 12px; height: 12px; border-radius: 50%;}
  
  .trend-chart {height: 200px; display: flex; align-items: end; justify-content: space-around; border-bottom: 2px solid #ddd; border-left: 2px solid #ddd; padding: 20px;}
  .trend-bar {background: #007BFF; width: 40px; margin: 0 5px; border-radius: 4px 4px 0 0; position: relative;}
  .trend-label {position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 0.8rem; color: #666;}
  
  @media (max-width: 768px) {
    nav {position: relative; width: 100%; height: auto; padding-top: 10px;}
    main {margin-left: 0; padding: 15px;}
    footer {left: 0; position: relative;}
    .analytics-grid {grid-template-columns: 1fr;}
  }
</style>
</head>
<body>

<header>
  Indian Air Force Human Management System - Analytics Dashboard
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="personnel_list.php">Personnel List</a>
  <?php if (in_array($user_role, ['HR Manager', 'Training Department'])): ?>
    <a href="training_management.php">Training Management</a>
  <?php endif; ?>
  <a href="#">Profile</a>
  <a href="analytics.php">Analytics</a>
  <a href="reports.php">Reports</a>
  <a href="logout.php">Logout</a>
</nav>

<main>
  <h1>Analytics Dashboard - <?php echo htmlspecialchars($user_role); ?></h1>
  
  <!-- Key Metrics -->
  <div class="analytics-grid">
    <div class="metric-card">
      <div class="metric-value"><?php echo $total_personnel; ?></div>
      <div class="metric-label">Total Personnel</div>
    </div>
    <div class="metric-card">
      <div class="metric-value"><?php echo $active_deployments; ?></div>
      <div class="metric-label">Active Deployments</div>
    </div>
    <div class="metric-card">
      <div class="metric-value"><?php echo $total_trainings; ?></div>
      <div class="metric-label">Completed Trainings</div>
    </div>
  </div>

  <?php if (in_array($user_role, ['Commander', 'HR Manager'])): ?>
    <!-- Rank Distribution -->
    <div class="chart-container">
      <div class="chart-title">Personnel Distribution by Rank</div>
      <div class="bar-chart">
        <?php 
        $max_count = max(array_column($rank_distribution, 'count'));
        foreach ($rank_distribution as $rank): 
          $percentage = ($rank['count'] / $max_count) * 100;
        ?>
          <div class="bar-item">
            <div class="bar-label"><?php echo htmlspecialchars($rank['rank_level'] ?? 'Unassigned'); ?></div>
            <div class="bar-visual">
              <div class="bar-fill" style="width: <?php echo $percentage; ?>%;">
                <div class="bar-value"><?php echo $rank['count']; ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Medical Fitness Status -->
    <div class="chart-container">
      <div class="chart-title">Medical Fitness Status</div>
      <div class="bar-chart">
        <?php 
        $total_fitness = array_sum(array_column($fitness_stats, 'count'));
        foreach ($fitness_stats as $status): 
          $percentage = ($status['count'] / $total_fitness) * 100;
        ?>
          <div class="bar-item">
            <div class="bar-label"><?php echo htmlspecialchars($status['medical_fitness_status']); ?></div>
            <div class="bar-visual">
              <div class="bar-fill" style="width: <?php echo $percentage; %>%;">
                <div class="bar-value"><?php echo $status['count']; ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Training Trends -->
    <div class="chart-container">
      <div class="chart-title">Training Completion Trends (Last 6 Months)</div>
      <div class="trend-chart">
        <?php 
        if (!empty($training_trends)) {
          $max_trend = max(array_column($training_trends, 'count'));
          foreach ($training_trends as $trend): 
            $height = ($trend['count'] / $max_trend) * 150;
        ?>
          <div class="trend-bar" style="height: <?php echo $height; ?>px;">
            <div class="trend-label"><?php echo date('M Y', strtotime($trend['month'].'-01')); ?></div>
          </div>
        <?php 
          endforeach;
        } else {
          echo "<p>No training data available for the selected period.</p>";
        }
        ?>
      </div>
    </div>

    <!-- Skill Gap Analysis -->
    <div class="chart-container">
      <div class="chart-title">Skill Gap Analysis</div>
      <div class="bar-chart">
        <?php foreach ($skill_gaps as $gap): ?>
          <div class="bar-item">
            <div class="bar-label"><?php echo htmlspecialchars($gap['skill_category']); ?></div>
            <div style="font-size: 0.9rem; color: #666;">
              Trained: <?php echo $gap['trained_count']; ?> | Gap: <?php echo $gap['gap_count']; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($user_role === 'Medical Officer'): ?>
    <!-- Medical Records by Type -->
    <div class="chart-container">
      <div class="chart-title">Medical Records by Type</div>
      <div class="bar-chart">
        <?php 
        if (!empty($medical_types)) {
          $max_medical = max(array_column($medical_types, 'count'));
          foreach ($medical_types as $type): 
            $percentage = ($type['count'] / $max_medical) * 100;
        ?>
          <div class="bar-item">
            <div class="bar-label"><?php echo htmlspecialchars($type['medical_type']); ?></div>
            <div class="bar-visual">
              <div class="bar-fill" style="width: <?php echo $percentage; ?>%;">
                <div class="bar-value"><?php echo $type['count']; ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; } ?>
      </div>
    </div>

    <!-- Fitness Impact Analysis -->
    <div class="chart-container">
      <div class="chart-title">Fitness Impact Analysis</div>
      <div class="bar-chart">
        <?php 
        if (!empty($fitness_impact)) {
          foreach ($fitness_impact as $impact): ?>
          <div class="bar-item">
            <div class="bar-label"><?php echo htmlspecialchars($impact['fitness_impact']); ?></div>
            <div style="font-size: 1rem; color: #333; font-weight: bold;">
              <?php echo $impact['count']; ?> cases
            </div>
          </div>
        <?php endforeach; } ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($user_role === 'Training Department'): ?>
    <!-- Training Effectiveness -->
    <div class="chart-container">
      <div class="chart-title">Training Program Effectiveness</div>
      <div class="bar-chart">
        <?php foreach ($training_effectiveness as $effectiveness): ?>
          <div class="bar-item">
            <div class="bar-label"><?php echo htmlspecialchars($effectiveness['training_name']); ?></div>
            <div class="bar-visual">
              <div class="bar-fill" style="width: <?php echo $effectiveness['success_rate']; ?>%;">
                <div class="bar-value"><?php echo round($effectiveness['success_rate'], 1); ?>%</div>
              </div>
            </div>
            <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
              Total Attempts: <?php echo $effectiveness['total_attempts']; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<footer>
  &copy; 2025 Indian Air Force Human Management System
</footer>

</body>
</html>