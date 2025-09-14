<?php
session_start();
// Example stats: Replace with actual DB queries!
$total_personnel = 835;
$active_deployments = 28;
$total_trainings = 2431;
$notif_count = isset($_SESSION['user_id']) ? 3 : 0;
$user_name = isset($_SESSION['user_id']) ? $_SESSION['full_name'] : '';
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>IAF Human Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Roboto', Arial, sans-serif;
    min-height: 100vh;
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg, #1e2d26 0%, #2e4c39 100%), url('https://www.transparenttextures.com/patterns/green-fibers.png');
    box-sizing: border-box;
}
.hero-header {
    background: linear-gradient(120deg, #1c232e 0%, #3a614e 100%);
    color: #faffe2;
    min-height: 60vh;
    position: relative;
    box-shadow: 0 4px 24px rgba(41,62,44,0.22);
    overflow: hidden;
    border-bottom-left-radius:28px;border-bottom-right-radius:28px;
}
.main-nav {
    display: flex; align-items: center; justify-content: space-between;
    padding: 24px 8vw 0 8vw;
}
.brand-logo {
    display: flex;align-items: center;gap:17px;font-size:1.24rem;font-family:'Oswald',sans-serif;font-weight:700;color:#e7f282;
}
.brand-logo img {
    height:46px;
    width:auto;
    margin-right:14px;
}
.main-nav ul {
    list-style:none;
    display:flex;
    gap:18px;
    margin: 0;
    padding: 0;
}
.main-nav ul li {
    display:inline;
}
.main-nav a {
    color:#faffe2;
    text-decoration:none;
    font-weight:500;
    font-size:1.08rem;
    transition:.13s;
}
.main-nav a.btn-primary, .main-nav a.btn-secondary {
    padding:8px 21px;
    border-radius:7px;
    box-shadow:0 2px 12px #a3db4d66;
    font-family:'Oswald',sans-serif;
    font-weight:700;
    margin-left:7px;
}
.main-nav a.btn-primary {background:#e7f282;color:#223913;}
.main-nav a.btn-secondary {background:#2c342f;color:#e7f282;border:2px solid #e7f282;}
.main-nav a.btn-primary:hover {background:#dbee7b;color:#27401e;}
.main-nav a.btn-secondary:hover {background:#e7f282;color:#2c342f;}
#notification-bell {position:relative;}
#notification-bell #notif-count {
    background:#fd5252b0;color:#fff;font-size:.98em; font-weight:700;
    border-radius:15px; padding:2px 7px;position:absolute;top:-8px;left:35px;box-shadow:0 1px 8px #fd525277;
}
.hero-content {
    text-align: center;
    padding: 42px 5vw 32px 5vw;
}
.hero-content h1 {
    font-family:'Oswald',sans-serif;font-size:2.4rem;font-weight:700;
    letter-spacing:1.8px; margin-bottom:9px; position:relative; display:inline-block;
}
.hero-content .typed {
    background: linear-gradient(90deg, #dbee7b 14%, #e7f282 86%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip:text;
}
.hero-content .spark {
    display:inline-block;
    width:14px;
    height:14px;
    margin-left:9px;
    border-radius:50%;
    background:radial-gradient(circle,#e7f282 60%,#fff0 100%);
}
.hero-content .hero-slogan {
    font-size:1.22rem;
    color:#aad44f;
    margin-top:6px;
    margin-bottom:17px;
}
.hero-content #stats {
    display:inline-flex;
    gap:32px;
    padding:15px 0;
}
.stat-block {
    background:#26472d88;
    border-radius:7px;
    padding:7px 19px;
    box-shadow:0 2px 11px #bde76622;
}
.hero-cta {
    margin-top:24px;
}
.hero-cta .btn-accent, .hero-cta .btn-outline {
    font-size:1.13rem;
    font-family:'Oswald',sans-serif;
    font-weight:700;
    padding:15px 41px;
    margin:0 13px;
    border-radius:8px;
    text-decoration:none;
    box-shadow:0 6px 18px #b2e9492a;
}
.hero-cta .btn-accent {background:#dbee7b;color:#263213;}
.hero-cta .btn-accent:hover{background:#a7e93d;color:#1c232e;}
.hero-cta .btn-outline {background:transparent;color:#faffe2;border:2px solid #e7f282;}
.hero-cta .btn-outline:hover{background:#e7f282;color:#263213;}
.features-section {
    background:rgba(34,53,31,0.93);
    max-width:900px;
    margin:56px auto 0 auto;
    box-shadow:0 8px 28px #20321224;
    padding:44px 20px 34px 20px;
    border-radius:30px;
    text-align:center;
}
.features-section h2 {
    font-family:'Oswald',sans-serif;
    font-size:2.0rem;
    color:#a3db4d;
    font-weight:700;
    margin-bottom:21px;
    letter-spacing:1.2px;
    text-shadow:1px 2px 10px #223913;
}
.features-list {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap:46px;
    margin-top:11px;
}
.feature-block {
    background:#212d22fb;
    border-radius:18px;
    padding:28px 26px 21px 26px;
    box-shadow:0 5px 21px #34562911;
    min-width:240px;
    max-width:320px;
}
.feature-block h3 {
    font-family:'Oswald',sans-serif;
    font-size:1.21rem;
    color:#e7f282;
    margin-bottom:8px;
}
.feature-block p {
    color:#c4fa90;
    font-size:1.04rem;
    margin-top:4px;
}
@media(max-width:900px){
    .features-list{gap:13px;}
    .feature-block{padding:18px 8px;}
}
@media(max-width:700px){
    .main-nav{flex-direction:column;align-items:flex-start;padding:16px 3vw;}
    .main-nav ul{flex-direction:column; gap:9px;}
    .hero-content{padding:22px 2vw 11px 2vw;}
    .hero-content h1{font-size:1.27rem;}
    .features-section{padding:17px 2vw;}
}
</style>
</head>
<body>
<header class="hero-header">
  <nav class="main-nav">
    <div class="brand-logo">
      <span>IAF Human Management System</span>
    </div>
    <ul>
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="reports.php">Reports</a></li>
      <li><a href="training_management.php">Training</a></li>
      <li><a href="contact.php">Contact</a></li>
      <li id="notification-bell">
        <a href="notifications.php">🔔 <span id="notif-count"><?php echo $notif_count; ?></span></a>
      </li>
      <?php if($is_logged_in): ?>
        <li><a href="profile.php" class="btn-primary"><?php echo htmlspecialchars($user_name); ?></a></li>
        <li><a href="logout.php" class="btn-secondary">Logout</a></li>
      <?php else: ?>
        <li><a href="login.php" class="btn-primary">Login</a></li>
        <li><a href="register.php" class="btn-secondary">Register</a></li>
      <?php endif; ?>
    </ul>
  </nav>
  <section class="hero-content">
    <h1>
      <span class="typed">Empowering Personnel Excellence</span>
      <span class="spark"></span>
    </h1>
    <div class="hero-slogan">
      Seamlessly manage, train & deploy your workforce.
    </div>
    <div id="stats">
      <span class="stat-block">Personnel: <b><?php echo $total_personnel; ?></b></span>
      <span class="stat-block">Active Deployments: <b><?php echo $active_deployments; ?></b></span>
      <span class="stat-block">Trainings Completed: <b><?php echo $total_trainings; ?></b></span>
    </div>
    <div class="hero-cta">
      <?php if(!$is_logged_in): ?>
        <a href="register.php" class="btn-accent">Join the Mission</a>
        <a href="login.php" class="btn-outline">Access Portal</a>
      <?php else: ?>
        <a href="dashboard.php" class="btn-accent">Go to Dashboard</a>
      <?php endif; ?>
    </div>
  </section>
</header>
<section class="features-section">
  <h2>Extraordinary Features</h2>
  <div class="features-list">
    <div class="feature-block">
      <h3>Advanced Workforce Analytics</h3>
      <p>Gain real-time insights on readiness, attrition, skill gaps, and deployment trends for strategic decisions.</p>
    </div>
    <div class="feature-block">
      <h3>Training & Certification Tracking</h3>
      <p>Automated tracking of personnel training, certificates, and progression, with secure record/document upload.</p>
    </div>
    <div class="feature-block">
      <h3>Secure Multi-Level Authentication</h3>
      <p>Robust login security including password, OTP, and Google Authenticator integration for MFA.</p>
    </div>
    <div class="feature-block">
      <h3>Instant Notifications System</h3>
      <p>Role-based alerts for new tasks, mission updates, or medical and HR messages ensure no alert is missed.</p>
    </div>
    <div class="feature-block">
      <h3>Powerful Reporting Engine</h3>
      <p>Generate and export custom reports including personnel summaries, fitness, deployments, and skills matrix.</p>
    </div>
    <div class="feature-block">
      <h3>Responsive, Role-Driven Dashboard</h3>
      <p>Users see dashboards tailored to their position—commander, HR, medical, ground staff, or training manager.</p>
    </div>
  </div>
</section>
<script>
const typed = document.querySelector('.typed');
const text = "Empowering Personnel Excellence";
let i = 0;
function typeEffect() {
    if (typed && i <= text.length) {
        typed.textContent = text.substring(0, i);
        i++;
        setTimeout(typeEffect, 80);
    }
}
document.addEventListener('DOMContentLoaded', typeEffect);
</script>
</body>
</html>
