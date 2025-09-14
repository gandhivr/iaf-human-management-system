<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

$dashboard_content = [
    'Commander' => 'Mission readiness overview and workforce allocation insights.',
    'HR Manager' => 'Workforce analytics, attrition predictions, training needs, and personnel profiles management.',
    'Medical Officer' => 'Medical fitness reports and personnel health summaries.',
    'Training Department' => 'Training records, feedback, and skill progression tracking.',
    'Ground Staff' => 'Task updates and skill logs.',
];

$content = $dashboard_content[$role] ?? 'Dashboard content unavailable for your role.';

// Summary cards - real data
$total_personnel = $conn->query("SELECT COUNT(*) as count FROM personnel")->fetch_assoc()['count'] ?? 0;
$active_deployments = $conn->query("SELECT COUNT(*) as count FROM deployments WHERE end_date IS NULL OR end_date > NOW()")->fetch_assoc()['count'] ?? 0;
$total_trainings = $conn->query("SELECT COUNT(*) as count FROM training_records WHERE completion_date IS NOT NULL")->fetch_assoc()['count'] ?? 0;
$total_alerts = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = {$_SESSION['user_id']} AND is_read = 0")->fetch_assoc()['count'] ?? 0;

// Recent training records
$recent_training_records = [];
if (in_array($role, ['HR Manager', 'Training Department'])) {
    $stmt = $conn->prepare("
        SELECT tr.id, p.full_name, ts.training_name, tr.completion_date 
        FROM training_records tr
        JOIN personnel p ON tr.personnel_id = p.id
        JOIN training_sessions ts ON tr.training_session_id = ts.id
        ORDER BY tr.completion_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stmt->bind_result($tr_id, $personnel_name, $training_name, $comp_date);
    while ($stmt->fetch()) {
        $recent_training_records[] = [
            'id' => $tr_id,
            'personnel_name' => $personnel_name,
            'training_name' => $training_name,
            'completion_date' => $comp_date
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>IAF Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.0/css/all.min.css">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&family=Roboto&display=swap');
    body { margin: 0; padding: 0; font-family: 'Roboto', sans-serif; background: #102027 url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1350&q=80') center/cover no-repeat fixed; color: #dde7de;}
    body::before {content:'';position:fixed;inset:0;z-index:-1;background:rgba(16,32,39,0.88);}
    header {background:#263238dd;color:#aed581;font-family:'Oswald',sans-serif;font-weight:700;font-size:2rem;padding:1.5rem 3rem;letter-spacing:0.10em;text-align:center;text-transform:uppercase;box-shadow:0 3px 10px rgba(0,0,0,0.7);}
    nav {position: fixed; top: 72px; left: 0; width: 240px; height: calc(100vh - 72px); background: #263238f2; display:flex; flex-direction:column; padding-top:2rem; box-shadow:2px 0 12px rgba(0,0,0,0.8); border-top-right-radius: 20px; border-bottom-right-radius: 20px; overflow-y:auto;}
    nav a {color:#a5d6a7;text-decoration:none;font-weight:600;font-size:1.1rem;padding:1rem 2rem;margin:0 1rem 0.7rem 1rem;border-radius: 10px;border-left:5px solid transparent;transition:0.3s;}
    nav a:hover, nav a:focus {background: #81c784;color:#263238;border-left:5px solid #4caf50;outline:none;}

    main { margin-left:240px; padding:3rem 4rem 3rem 4rem; min-height:calc(100vh - 72px); overflow-y:auto;}
    main h2 {font-family: 'Oswald', sans-serif; font-weight:700; font-size:2.8rem; margin-bottom:0.4rem; color:#cddc39; text-shadow:1px 1px 4px #27472f;}
    main p.role { font-size:1.2rem; margin-bottom:2rem; color:#9ccc65; font-weight:600;}
    .summary-row { display:flex; gap:1.7rem; margin-bottom:2.1rem; flex-wrap:wrap;}
    .summary-card {background: #222d3290; color:#cddc39; border-radius: 14px; padding: 2rem 2.5rem; display:flex; flex-direction:column; align-items: flex-start; justify-content:center; min-width:180px; min-height:120px; flex:1 1 170px; box-shadow: 0 4px 16px 0 rgba(44,62,80,0.16); position:relative; overflow:hidden; margin-bottom:12px;}
    .summary-card i { font-size:2.2rem; margin-bottom:0.4rem; color:#8bc34a;}
    .summary-card .count-up { font-size:2.5rem; font-family:'Oswald',sans-serif;font-weight:700;line-height:1;margin-bottom:0.2rem;}
    .summary-card span.label {font-size:1.13rem; color:#b7e063; opacity:0.9; font-weight:700; letter-spacing:0.05em;}
    .summary-card.card-alerts .label {color: #ff7043;}
    .summary-card.card-alerts i {color: #ff7043;}
    @media (max-width: 900px) {
        nav { position:relative; width:100%; height:56px; padding-top:0; border-radius:0; box-shadow:0 2px 8px rgba(0,0,0,0.9); z-index:20; flex-direction:row; justify-content: space-around; align-items: center;}
        nav a { margin:0; padding:0 10px; border-left:none; border-radius:0; font-size:1rem; }
        nav a:hover, nav a:focus { border-left:none; }
        main { margin-left:0; padding:4rem 1.5rem 2rem 1.5rem;}
        .summary-row { flex-direction:column; gap:1.2rem;}
    }
    .dashboard-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(320px,1fr)); gap:2.5rem;}
    section.card {background: #37474fdd; border-radius: 16px; padding: 2rem; box-shadow: 0 8px 15px rgba(0,0,0,0.7); color: #e6ee9c;}
    section.card h3 {font-family: 'Oswald', sans-serif; font-weight:700; font-size:1.7rem; border-bottom:2px solid #9ccc65; padding-bottom:0.7rem; margin-bottom:1rem; color:#e9f76c; letter-spacing:0.03em;}
    .overview-text { font-size:1.08rem; line-height:1.4;}
    ul.record-list {list-style:none; padding: 0; max-height:220px; overflow-y:auto; margin:0;}
    ul.record-list li {background: #455a64cc; margin-bottom:12px; border-radius:12px; padding:14px 22px; display:flex; flex-direction:column; gap:3px; font-weight:600; transition:background 0.3s;}
    ul.record-list li:hover { background-color:#81c784cc; color:#263238;}
    a.record-link { color:inherit; font-weight:700; text-decoration:none; }
    a.record-link:hover { text-decoration: underline; }
    .record-date { font-size:0.95rem; color:#dce775cc;}
    a.view-all {display:inline-block;margin-top:0.7rem;font-weight:700;color:#cddc39;text-decoration:none;font-size:1.05rem;}
    a.view-all:hover { color: #fff; text-decoration: underline;}
    /* Notifications Centre */
    #notification-center { margin-bottom:22px;}
    #notification-center .fa-bell { color:#ffd600; font-size:2rem;}
    #notif-count {background:#ff7043;color:#fff;padding:3px 10px;border-radius:12px;min-width:22px;display:inline-block;text-align:center;font-weight:700;font-size:1.07rem;}
    #markAllRead, #clearAllNotif {margin-left:8px;padding:4px 15px;border-radius:18px;font-size:1rem;cursor:pointer;}
    #markAllRead {background:#8bc34a;color:#212121;border:none;}
    #clearAllNotif {background:#d32f2f;color:#fff;border:none;}
    #notif-list {margin-top:10px;list-style:none;padding-left:0;}
    #notif-list li {margin-bottom:6px;background:#222;padding:7px 13px;border-radius:9px;color:#ffd600;}
    #notif-list li .when {float:right;color:#bdb76b;font-size:0.9em;}
    #notif-list .no-unread {color:#888;font-style:italic;}
</style>
</head>
<body>
<header>Indian Air Force Human Management System</header>
<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="personnel_list.php">Personnel List</a>
    <?php if (in_array($role, ['HR Manager', 'Training Department'])): ?>
    <a href="training_management.php">Training Management</a>
    <?php endif; ?>
    <a href="profile_analytics.php">Profile &amp; Analytics</a>
    <a href="reports.php">Reports</a>
    <a href="logout.php">Logout</a>
</nav>
<main>
    <h2>Welcome, <?php echo htmlspecialchars($full_name); ?>!</h2>
    <p class="role">Role: <?php echo htmlspecialchars($role); ?></p>

    <!-- Smart Notification Centre -->
    <div id="notification-center">
      <div style="display:flex;align-items:center;gap:16px;">
        <span><i class="fas fa-bell"></i></span>
        <span id="notif-count"><?=$total_alerts ?: ''?></span>
        <button id="markAllRead">Mark all as read</button>
        <button id="clearAllNotif">Clear all</button>
      </div>
      <ul id="notif-list"></ul>
    </div>

    <!-- Summary row -->
    <div class="summary-row">
        <div class="summary-card">
            <i class="fas fa-users"></i>
            <div class="count-up" id="personnelCount" data-count="<?=$total_personnel?>">0</div>
            <span class="label">Total Personnel</span>
        </div>
        <div class="summary-card">
            <i class="fas fa-plane"></i>
            <div class="count-up" id="deploymentCount" data-count="<?=$active_deployments?>">0</div>
            <span class="label">Active Deployments</span>
        </div>
        <div class="summary-card">
            <i class="fas fa-graduation-cap"></i>
            <div class="count-up" id="trainingCount" data-count="<?=$total_trainings?>">0</div>
            <span class="label">Training Sessions</span>
        </div>
        <div class="summary-card card-alerts">
            <i class="fas fa-bell"></i>
            <div class="count-up" id="alertsCount" data-count="<?=$total_alerts?>">0</div>
            <span class="label">Alerts/Notifications</span>
        </div>
    </div>

    <div class="dashboard-grid">
        <section class="card">
            <h3>Your Dashboard Overview</h3>
            <p class="overview-text"><?php echo htmlspecialchars($content); ?></p>
        </section>
        <?php if (in_array($role, ['HR Manager', 'Training Department'])): ?>
        <section class="card" aria-label="Recent training records">
            <h3>Recent Training Records</h3>
            <?php if (count($recent_training_records) > 0): ?>
            <ul class="record-list" tabindex="0" aria-live="polite">
                <?php foreach ($recent_training_records as $record): ?>
                <li>
                    <a href="training_management.php#record-<?php echo $record['id']; ?>" class="record-link" tabindex="0">
                        <?php echo htmlspecialchars($record['personnel_name']); ?>
                    </a>
                    <span class="record-date">completed <strong><?php echo htmlspecialchars($record['training_name']); ?></strong> on <?php echo htmlspecialchars($record['completion_date']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <a href="training_management.php" class="view-all" tabindex="0" aria-label="View all training records">View All Training Records</a>
            <?php else: ?>
                <p>No recent training records found.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>
<!-- Animated Counter Script -->
<script>
function animateCounter(id, value, duration=1000) {
    let el = document.getElementById(id);
    let start = 0, end = parseInt(value, 10);
    if (isNaN(end)) return;
    let stepTime = Math.max(Math.floor(duration / end), 20);
    let startTime = null;
    function runStep(timestamp) {
        if (!startTime) startTime = timestamp;
        let progress = Math.min((timestamp - startTime) / duration, 1);
        el.textContent = Math.floor(progress * end);
        if (progress < 1) {
            requestAnimationFrame(runStep);
        } else {
            el.textContent = end; // ensure it ends cleanly
        }
    }
    requestAnimationFrame(runStep);
}
window.onload = function() {
    ['personnelCount','deploymentCount','trainingCount','alertsCount'].forEach(function(id){
        let value = document.getElementById(id).getAttribute('data-count');
        animateCounter(id, value, 1200+Math.random()*800);
    });
};
</script>
<!-- Notifications Centre Ajax/JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadNotifications() {
    $.get("fetch_notifications.php", function(data) {
        let res = JSON.parse(data), html = "";
        let notifs = res.notifications || [];
        $("#notif-count").text(notifs.length > 0 ? notifs.length : "");
        notifs.forEach(function(n) {
            html += `<li>
                <span style="font-weight:500;">${n.message}</span>
                <span class="when">${n.created_at}</span>
            </li>`;
        });
        if(notifs.length === 0) html = '<li class="no-unread">No unread notifications.</li>';
        $("#notif-list").html(html);
        $("#alertsCount").text(notifs.length > 0 ? notifs.length : "0");
    });
}
loadNotifications();
setInterval(loadNotifications, 5000);

$("#markAllRead").on("click", function(){
    $.post("mark_all_read.php", function(resp){
        loadNotifications();
    });
});

$("#clearAllNotif").on("click", function(){
    if(confirm("Clear all notifications?")) {
        $.post("clear_all_notifications.php", function(resp){
            loadNotifications();
        });
    }
});
</script>
</body>
</html>
