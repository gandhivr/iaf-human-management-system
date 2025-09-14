<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Handle report generation requests
$report_data = [];
$report_type = '';
$report_title = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    $filters = $_POST['filters'] ?? [];

    switch ($report_type) {
        case 'personnel_summary':
            $report_title = 'Personnel Summary Report';
            $sql = "SELECT p.*, 
                    (SELECT COUNT(*) FROM training_records tr WHERE tr.personnel_id = p.id) as training_count,
                    (SELECT COUNT(*) FROM deployments d WHERE d.personnel_id = p.id AND d.status = 'Active') as active_deployments,
                    (SELECT COUNT(*) FROM medical_history mh WHERE mh.personnel_id = p.id) as medical_records
                    FROM personnel p ORDER BY p.full_name";
            break;

        case 'training_effectiveness':
            $report_title = 'Training Effectiveness Report';
            $where_clause = '';
            if ($date_from && $date_to) {
                $where_clause = "WHERE tr.completion_date BETWEEN '$date_from' AND '$date_to'";
            }
            $sql = "SELECT ts.training_name, ts.skill_category, ts.difficulty_level,
                    COUNT(tr.id) as total_completions,
                    SUM(CASE WHEN tr.result IN ('Passed', 'Excellent', 'Good') THEN 1 ELSE 0 END) as successful_completions,
                    ROUND(AVG(CASE WHEN tr.result IN ('Passed', 'Excellent', 'Good') THEN 1 ELSE 0 END) * 100, 2) as success_rate,
                    ts.duration_days
                    FROM training_sessions ts
                    LEFT JOIN training_records tr ON ts.id = tr.training_session_id
                    $where_clause
                    GROUP BY ts.id, ts.training_name
                    ORDER BY success_rate DESC";
            break;

        case 'medical_fitness':
            $report_title = 'Medical Fitness Status Report';
            $sql = "SELECT p.full_name, p.service_number, p.rank_level, p.current_unit,
                    p.medical_fitness_status,
                    COUNT(mh.id) as total_medical_records,
                    MAX(mh.record_date) as last_medical_checkup,
                    SUM(CASE WHEN mh.fitness_impact = 'Permanent' THEN 1 ELSE 0 END) as permanent_impact_count,
                    SUM(CASE WHEN mh.fitness_impact = 'Temporary' THEN 1 ELSE 0 END) as temporary_impact_count
                    FROM personnel p
                    LEFT JOIN medical_history mh ON p.id = mh.personnel_id
                    GROUP BY p.id
                    ORDER BY p.medical_fitness_status, p.full_name";
            break;

        case 'deployment_status':
            $report_title = 'Deployment Status Report';
            $sql = "SELECT p.full_name, p.service_number, p.rank_level, p.current_unit,
                    d.deployment_location, d.start_date, d.end_date, d.mission_type, d.status,
                    DATEDIFF(COALESCE(d.end_date, CURDATE()), d.start_date) as deployment_days
                    FROM personnel p
                    LEFT JOIN deployments d ON p.id = d.personnel_id
                    ORDER BY d.status DESC, d.start_date DESC";
            break;

        case 'skill_matrix':
            $report_title = 'Skill Assessment Matrix';
            $sql = "SELECT p.full_name, p.service_number, p.rank_level, p.current_unit,
                    sa.skill_name, sa.proficiency_level, sa.assessment_date,
                    u.full_name as assessor_name
                    FROM personnel p
                    LEFT JOIN skill_assessments sa ON p.id = sa.personnel_id
                    LEFT JOIN users u ON sa.assessor_id = u.id
                    ORDER BY p.full_name, sa.skill_name";
            break;

        case 'attrition_analysis':
            $report_title = 'Personnel Attrition Analysis';
            $sql = "SELECT p.rank_level, p.current_unit,
                    COUNT(*) as total_personnel,
                    AVG(p.experience_years) as avg_experience,
                    AVG(DATEDIFF(CURDATE(), p.joining_date) / 365) as avg_service_years
                    FROM personnel p
                    GROUP BY p.rank_level, p.current_unit
                    ORDER BY p.rank_level, p.current_unit";
            break;
    }

    if (isset($sql)) {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
        }
    }
}

// Define available reports based on role
$available_reports = [];
if (in_array($user_role, ['Commander', 'HR Manager'])) {
    $available_reports = [
        'personnel_summary' => 'Personnel Summary Report',
        'training_effectiveness' => 'Training Effectiveness Report',
        'medical_fitness' => 'Medical Fitness Status Report',
        'deployment_status' => 'Deployment Status Report',
        'skill_matrix' => 'Skill Assessment Matrix',
        'attrition_analysis' => 'Personnel Attrition Analysis'
    ];
} elseif ($user_role === 'Medical Officer') {
    $available_reports = [
        'medical_fitness' => 'Medical Fitness Status Report',
        'personnel_summary' => 'Personnel Summary Report (Medical Focus)'
    ];
} elseif ($user_role === 'Training Department') {
    $available_reports = [
        'training_effectiveness' => 'Training Effectiveness Report',
        'skill_matrix' => 'Skill Assessment Matrix'
    ];
} elseif ($user_role === 'Ground Staff') {
    $available_reports = [
        'deployment_status' => 'Deployment Status Report'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reports - IAF Human Management</title>
<style>
  /* Reset & base */
  * {
    box-sizing: border-box;
  }
  body {
    font-family: 'Roboto', Arial, sans-serif;
    margin: 0;
    background: #f4f4f9;
    color: #223913;
  }
  header {
    background: linear-gradient(135deg, #3a614e, #1c232e);
    color: #e7f282;
    text-align: center;
    padding: 20px 15px;
    font-size: 1.8rem;
    font-family: 'Oswald', sans-serif;
    font-weight: 700;
    box-shadow: 0 4px 8px rgba(41,62,44,0.4);
    position: sticky;
    top: 0;
    z-index: 1000;
  }
  nav {
    background: #26472d;
    color: #e7f282;
    width: 220px;
    height: 100vh;
    position: fixed;
    top: 72px; /* height of header */
    left: 0;
    padding-top: 10px;
    font-weight: 600;
    font-family: 'Oswald', sans-serif;
    box-shadow: 2px 0 6px rgba(28,35,46,0.5);
  }
  nav a {
    display: block;
    color: #dbee7b;
    padding: 14px 20px;
    text-decoration: none;
    border-left: 5px solid transparent;
    transition: background 0.3s ease, border-color 0.3s ease;
  }
  nav a:hover {
    background: #92b946aa;
    border-left: 5px solid #dbee7b;
    color: #1c232e;
  }
  main {
    margin-left: 220px;
    padding: 30px 40px;
    min-height: calc(100vh - 72px);
    background: #f4f9f3;
  }
  footer {
    background: #26472d;
    color: #e7f282;
    padding: 15px;
    text-align: center;
    position: fixed;
    bottom: 0;
    left: 220px;
    right: 0;
    font-family: 'Oswald', sans-serif;
    font-weight: 600;
    font-size: 0.9rem;
  }

  /* Form styling */
  .report-form {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(107, 149, 62, 0.25);
    margin-bottom: 40px;
    font-family: 'Roboto', sans-serif;
  }
  .report-form h2 {
    font-family: 'Oswald', sans-serif;
    color: #2e4c39;
    margin-bottom: 24px;
    font-weight: 700;
  }
  .form-group {
    margin-bottom: 18px;
  }
  .form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #445a26;
  }
  .form-group select,
  .form-group input[type="date"] {
    width: 100%;
    padding: 10px 14px;
    border-radius: 6px;
    border: 1.8px solid #a3db4d;
    font-size: 1rem;
    color: #223913;
    transition: border-color 0.3s;
  }
  .form-group select:focus,
  .form-group input[type="date"]:focus {
    border-color: #dbee7b;
    outline: none;
  }
  .form-row {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
  }
  .form-row .form-group {
    flex: 1 1 200px;
    min-width: 150px;
  }
  .generate-btn {
    background-color: #a7e93d;
    border: none;
    padding: 15px 36px;
    font-size: 1.1rem;
    font-family: 'Oswald', sans-serif;
    font-weight: 700;
    color: #1c232e;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 6px 18px #92b946cc;
    transition: background-color 0.3s ease;
  }
  .generate-btn:hover {
    background-color: #7db72d;
  }

  /* Report container */
  .report-container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(107, 149, 62, 0.2);
    font-family: 'Roboto', sans-serif;
  }
  .report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #a3db4d;
    padding-bottom: 12px;
    margin-bottom: 20px;
  }
  .report-title {
    font-size: 1.6rem;
    font-family: 'Oswald', sans-serif;
    font-weight: 700;
    color: #26472d;
  }
  .export-buttons {
    display: flex;
    gap: 14px;
  }
  .export-btn {
    background: white;
    border: 2px solid #a3db4d;
    padding: 8px 18px;
    font-weight: 600;
    font-family: 'Oswald', sans-serif;
    border-radius: 7px;
    cursor: pointer;
    color: #26472d;
    transition: all 0.25s ease;
    text-decoration: none;
  }
  .export-btn:hover {
    background: #a3db4d;
    color: white;
  }

  /* Report stats */
  .report-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }
  .stat-card {
    background: linear-gradient(135deg, #4d7c21, #a3db4d);
    border-radius: 8px;
    padding: 20px;
    color: #e7f282;
    text-align: center;
    box-shadow: 0 4px 15px rgb(92 128 36 / 0.5);
  }
  .stat-value {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 6px;
  }
  .stat-label {
    font-size: 1rem;
    opacity: 0.9;
  }

  /* Report Table */
  .report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 1rem;
  }
  .report-table th,
  .report-table td {
    border: 1px solid #ddd;
    padding: 12px 16px;
    text-align: left;
    vertical-align: middle;
  }
  .report-table th {
    background: #a7e93d;
    color: #1c232e;
    font-weight: 700;
  }
  .report-table tr:nth-child(even) {
    background: #f9fbe9;
  }
  .report-table tr:hover {
    background: #e6f1c2;
  }

  /* No data message */
  .no-data {
    text-align: center;
    padding: 40px 15px;
    color: #7a8b3f;
    font-size: 1.2rem;
    font-family: 'Roboto', sans-serif;
  }

  /* Responsive */
  @media (max-width: 900px) {
    nav {
      height: auto;
      position: relative;
      width: 100%;
      padding: 15px 0;
      display: flex;
      justify-content: center;
      gap: 12px;
      top: 0;
      box-shadow: none;
    }
    nav a {
      border-left: none;
      border-bottom: 3px solid transparent;
      padding: 12px 15px;
      font-size: 0.9rem;
    }
    nav a:hover {
      border-left: none;
      border-bottom: 3px solid #dbee7b;
      background: transparent;
      color: #92b946cc;
    }
    main {
      margin-left: 0;
      padding: 20px 15px;
    }
    footer {
      left: 0;
      position: relative;
      margin-top: 40px;
    }
    .form-row {
      flex-direction: column;
    }
    .form-row .form-group {
      flex: 1 1 100%;
    }
    .export-buttons {
      justify-content: center;
    }
    .report-stats {
      grid-template-columns: 1fr;
    }
  }

  /* Print styles */
  @media print {
    nav, footer, .report-form, .export-buttons {
      display: none !important;
    }
    main {
      margin-left: 0 !important;
      background: white !important;
    }
    body {
      background: white !important;
      color: black !important;
    }
  }
</style>
</head>
<body>

<header>
  Indian Air Force Human Management System - Reports
</header>

<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="personnel_list.php">Personnel List</a>
  <?php if (in_array($user_role, ['HR Manager', 'Training Department'])): ?>
    <a href="training_management.php">Training Management</a>
  <?php endif; ?>
  <a href="#">Profile</a>
  <a href="analytics.php">Analytics</a>
  <a href="reports.php" style="border-left: 5px solid #dbee7b; background:#92b946aa; color:#1c232e;">Reports</a>
  <a href="logout.php">Logout</a>
</nav>

<main>
  <h1>Reports Dashboard</h1>
  
  <!-- Report Generation Form -->
  <div class="report-form" role="region" aria-label="Generate a report form">
    <h2>Generate Report</h2>
    <form method="POST" action="">
      <input type="hidden" name="generate_report" value="1">

      <div class="form-row">
        <div class="form-group">
          <label for="report_type">Select Report Type:</label>
          <select name="report_type" id="report_type" required>
            <option value="">-- Choose Report --</option>
            <?php foreach ($available_reports as $type => $name): ?>
              <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($report_type === $type) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="date_from">From Date:</label>
          <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($_POST['date_from'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="date_to">To Date:</label>
          <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($_POST['date_to'] ?? ''); ?>">
        </div>

        <div class="form-group" style="flex: 0 1 auto;">
          <button type="submit" class="generate-btn">Generate Report</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Report Display -->
  <?php if (!empty($report_data) && !empty($report_title)): ?>
    <div class="report-container" role="region" aria-label="<?php echo htmlspecialchars($report_title); ?>">
      <div class="report-header">
        <div class="report-title"><?php echo htmlspecialchars($report_title); ?></div>
        <div class="export-buttons" role="group" aria-label="Export report options">
          <button class="export-btn" onclick="exportToCSV()">Export CSV</button>
          <button class="export-btn" onclick="window.print()">Print Report</button>
        </div>
      </div>

      <div class="report-stats">
        <div class="stat-card">
          <div class="stat-value"><?php echo count($report_data); ?></div>
          <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo date('Y-m-d'); ?></div>
          <div class="stat-label">Generated Date</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo htmlspecialchars($full_name); ?></div>
          <div class="stat-label">Generated By</div>
        </div>
      </div>

      <table class="report-table" id="reportTable" role="table" aria-label="Report data table">
        <thead>
          <tr>
            <?php if (!empty($report_data)): ?>
              <?php foreach (array_keys($report_data[0]) as $column): ?>
                <th scope="col"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $column))); ?></th>
              <?php endforeach; ?>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($report_data as $row): ?>
            <tr>
              <?php foreach ($row as $cell): ?>
                <td><?php echo htmlspecialchars($cell ?? 'N/A'); ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])): ?>
    <div class="report-container no-data" role="alert">
      No data found for the selected report criteria.
    </div>
  <?php else: ?>
    <div class="report-container no-data" role="alert">
      Please select a report type and click "Generate Report" to view data.
    </div>
  <?php endif; ?>
</main>

<footer>
  &copy; 2025 Indian Air Force Human Management System
</footer>

<script>
function exportToCSV() {
    const table = document.getElementById('reportTable');
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');

        for (let j = 0; j < cols.length; j++) {
            let cellData = cols[j].innerText;
            cellData = cellData.replace(/"/g, '""'); // Escape quotes
            row.push('"' + cellData + '"');
        }

        csv.push(row.join(','));
    }

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'iaf_report_' + new Date().toISOString().slice(0,10) + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Auto-set date range for some reports
document.getElementById('report_type').addEventListener('change', function() {
    const reportType = this.value;
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');

    if (reportType === 'training_effectiveness') {
        // Set to last 6 months by default
        const today = new Date();
        const sixMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 6, today.getDate());

        dateFrom.value = sixMonthsAgo.toISOString().slice(0,10);
        dateTo.value = today.toISOString().slice(0,10);
    }
});
</script>

</body>
</html>
