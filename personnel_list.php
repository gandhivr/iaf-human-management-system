<?php
session_start();
include 'db.php';

// Only HR Managers allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR Manager') {
    header("Location: login.php");
    exit();
}

// Pagination setup
$limit = 20; // entries per page
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Safe search filter
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Ensure variable is always set for output
$search_html = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

// Get total count for pagination using prepared statement
$count_sql = "SELECT COUNT(*) FROM personnel WHERE full_name LIKE ?";
$count_stmt = $conn->prepare($count_sql);
$like_search = '%' . $search . '%';
$count_stmt->bind_param('s', $like_search);
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_records = $total_records ?: 0;
$total_pages = max(1, ceil($total_records / $limit));

// Fetch personnel records securely
$sql = "SELECT id, full_name, email, role FROM personnel WHERE full_name LIKE ? ORDER BY full_name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sii', $like_search, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Personnel List</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&display=swap" rel="stylesheet">
<style>
body {
  font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: #162720 url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1350&q=80') center/cover no-repeat fixed;
  margin: 0;
  padding: 0;
  color: #eaf2d7;
}
body::before {
  content:'';position:fixed;inset:0;z-index:-1;background:rgba(22,39,32,0.91);
}
.container {
  max-width: 1020px;
  margin: 2.5rem auto 0 auto;
  background: #22351fdd;
  padding: 35px 38px;
  border-radius: 17px;
  box-shadow: 0 10px 32px rgba(20,30,10,0.18);
  min-height: 90vh;
}
h1 {
  margin-bottom: 23px;
  font-family:'Oswald',sans-serif;
  font-weight: 700;
  color: #dbe824;
  font-size:2.15rem;
  letter-spacing:1.9px;
  border-bottom: 3px solid #dbe824;
  padding-bottom: 16px;
}
.search-box {
  margin-bottom: 25px;
  display: flex;
  gap: 10px;
}
input[type="text"] {
  flex-grow: 1;
  padding: 11px 17px;
  border-radius: 7px;
  border: none;
  outline: none;
  background: #2e4032;
  color: #e9f5d0;
  font-size: 1.09rem;
  box-shadow: inset 1.5px 1.5px 13px rgba(0,0,0,0.27);
  transition: background 0.3s;
}
input[type="text"]:focus {
  background: #406d43;
}
button {
  padding: 11px 23px;
  background: #b4d544;
  border: none;
  border-radius: 7px;
  font-family:'Oswald',sans-serif;
  font-weight:700;
  cursor: pointer;
  font-size: 1.03rem;
  color: #203011;
  box-shadow: 0 3px 15px rgba(180,213,68,0.23);
  transition: background 0.25s, color 0.25s;
}
button:hover, button:focus {
  background: #dbe824;
  color: #1e2d13;
}
table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 13px;
  margin-top:5px;
}
thead th {
  background: #3d5f23;
  color: #f2ffe1;
  font-weight: 700;
  padding: 14px 20px;
  text-transform: uppercase;
  letter-spacing: 1.1px;
  border-radius: 8px 8px 0 0;
  font-family:'Oswald',sans-serif;
  font-size:1.07rem;
}
tbody tr {
  background: #163609cf;
  box-shadow: 0 5px 17px rgba(0,0,0,0.08);
  transition: background 0.22s;
  border-radius: 8px;
}
tbody tr:hover {
  background: #a3db4d;
  color:#172818;
  cursor: pointer;
}
tbody td {
  padding: 15px 20px;
  color: #ebffea;
  font-size:1.05rem;
  border-bottom:1px solid #30541544;
  transition: color 0.13s;
}
tbody tr:hover td, tbody tr:hover a {
  color:#141c10;
  font-weight:700;
}
tbody td a {
  color: #d5ff46;
  font-weight:700;
  font-family:'Oswald',sans-serif;
  text-decoration: none;
  transition: color 0.2s;
  background: #3a4520;
  padding: 6px 13px;
  border-radius: 7px;
}
tbody td a:hover, tbody td a:focus {
  color: #203011;
  background: #dbe824;
  text-decoration: underline;
}
.pagination {
  margin-top: 27px;
  text-align: center;
}
.pagination a {
  display: inline-block;
  margin: 0 5px;
  padding: 10px 20px;
  background: #b4d544;
  color: #1e2d13;
  font-family:'Oswald',sans-serif;
  font-weight:700;
  border-radius: 7px;
  text-decoration: none;
  box-shadow: 0 3px 15px rgba(180,213,68,0.23);
  font-size:1rem;
  transition: background 0.3s, color 0.3s;
}
.pagination a:hover, .pagination a:focus {
  background: #dbe824;
  color: #1e2d13;
}
p {
  margin-top: 28px;
  font-weight: 700;
  font-size:1.11rem;
}
p a {
  color: #b4d544;
  font-weight: 700;
  text-decoration: none;
}
p a:hover, p a:focus {
  text-decoration: underline;
  color:#e4f529;
}
@media (max-width: 700px) {
  .container {padding: 16px 4vw;}
  h1{font-size:1.25rem;padding-bottom:8px;}
  table, thead th, tbody td {font-size:0.94rem;}
  tbody td {padding:10px 7px;}
  thead th {padding:9px 5px;}
  .pagination a {font-size:.95rem;padding:7px 12px;}
}
</style>
</head>
<body>
<div class="container">
  <h1>Personnel List</h1>
  <form method="get" class="search-box" action="">
    <input type="text" name="search" placeholder="Search by Full Name"
           value="<?php echo $search_html; ?>" />
    <button type="submit">Search</button>
  </form>

  <?php if ($result && $result->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Profile</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
      <tr onclick="window.location='personnel_profile.php?id=<?php echo $row['id']; ?>'">
        <td><?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
          <a href="personnel_profile.php?id=<?php echo $row['id']; ?>" onclick="event.stopPropagation();">
            View Profile
          </a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <div class="pagination" aria-label="pagination">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
      <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>">
        <?php echo $p; ?>
      </a>
    <?php endfor; ?>
  </div>

  <?php else: ?>
    <p>No personnel found.</p>
  <?php endif; ?>

  <p><a href="dashboard.php">&laquo; Back to Dashboard</a></p>
</div>
</body>
</html>
<?php
$stmt->close();
?>
