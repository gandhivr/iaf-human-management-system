<?php
session_start();
include 'db.php';

// Check session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR Manager') {
    header("Location: login.php");
    exit();
}

// Get personnel id
$personnel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($personnel_id <= 0) {
    die("<div style='color:#fff;background:#b71c1c;padding:18px;font-size:1.14rem;border-radius:7px;'>Invalid personnel ID.</div>");
}

// Initialize variables to avoid undefined warnings
$full_name = $email = $dob = $contact_number = $address = '';
$upload_success = $upload_error = '';

// Handle update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personnel'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $dob = $conn->real_escape_string($_POST['dob']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $address = $conn->real_escape_string($_POST['address']);

    $stmt = $conn->prepare("UPDATE personnel SET full_name=?, email=?, DOB=?, contact_number=?, address=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssssi", $full_name, $email, $dob, $contact_number, $address, $personnel_id);
    $stmt->execute();
    $stmt->close();

    // Log audit
    $user_id = $_SESSION['user_id'];
    $action = "Updated personnel info for ID $personnel_id";
    $log_stmt = $conn->prepare("INSERT INTO audit_logs (personnel_id, user_id, action) VALUES (?, ?, ?)");
    $log_stmt->bind_param("iis", $personnel_id, $user_id, $action);
    $log_stmt->execute();
    $log_stmt->close();

    $upload_success = "Personnel details updated successfully.";
}

// Handle medical upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_medical'])) {
    if (isset($_FILES['medical_doc']) && $_FILES['medical_doc']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $file_tmp = $_FILES['medical_doc']['tmp_name'];
        $file_type = mime_content_type($file_tmp);
        $file_size = $_FILES['medical_doc']['size'];

        if (!in_array($file_type, $allowed_types) || $file_size > 5 * 1024 * 1024) {
            $upload_error = "Invalid file type or size exceeds 5MB.";
        } else {
            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_name = uniqid() . "_" . basename($_FILES['medical_doc']['name']);
            $destination = $upload_dir . $file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $record_date = date('Y-m-d');
                $description = $conn->real_escape_string($_POST['medical_description']);
                $stmt = $conn->prepare("INSERT INTO medical_history (personnel_id, record_date, description, document_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $personnel_id, $record_date, $description, $destination);
                $stmt->execute();
                $stmt->close();

                // Log audit
                $user_id = $_SESSION['user_id'];
                $action = "Uploaded medical document for personnel ID $personnel_id";
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (personnel_id, user_id, action) VALUES (?, ?, ?)");
                $log_stmt->bind_param("iis", $personnel_id, $user_id, $action);
                $log_stmt->execute();
                $log_stmt->close();

                $upload_success = "Medical document uploaded successfully.";
            } else {
                $upload_error = "File upload failed.";
            }
        }
    } else {
        $upload_error = "No file selected or upload error.";
    }
}

// Re-fetch personnel info after any update
$stmt = $conn->prepare("SELECT full_name, email, DOB, contact_number, address FROM personnel WHERE id = ?");
$stmt->bind_param("i", $personnel_id);
$stmt->execute();
$stmt->bind_result($full_name_db, $email_db, $dob_db, $contact_number_db, $address_db);
if ($stmt->fetch()) {
    // Only override if not set via POST (i.e. on initial page load or after upload, not after update)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['upload_medical'])) {
        $full_name = $full_name_db;
        $email = $email_db;
        $dob = $dob_db;
        $contact_number = $contact_number_db;
        $address = $address_db;
    }
} else {
    $stmt->close();
    die("<div style='color:#fff;background:#b71c1c;padding:18px;font-size:1.14rem;border-radius:7px;'>Personnel not found.</div>");
}
$stmt->close();

// Fetch medical records
$medical_records = [];
$result = $conn->query("SELECT record_date, description, document_path FROM medical_history WHERE personnel_id = $personnel_id ORDER BY record_date DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) $medical_records[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Personnel Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&family=Roboto&display=swap');
body {
  font-family: 'Roboto', Arial, sans-serif;
  background: #15232D url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1350&q=80') center/cover no-repeat fixed;
  color: #dde7de;
  margin:0; padding:0;
}
body::before {
  content:''; position:fixed; inset:0; z-index:-1; background:rgba(21,35,45,0.86);
}
.profile-wrapper { max-width: 990px; margin: 2.2rem auto; padding: 0; }
.card {
  background:#253446e8; padding:2.5rem 2.3rem; border-radius:17px;
  box-shadow:0 7px 32px rgba(0,0,0,0.18); margin-bottom:2.4rem;
}
.card h1, .card h2, .card h3 {
  font-family:'Oswald',sans-serif; font-weight:700; letter-spacing:0.07em; margin:1rem 0 1.4rem 0; color:#cddc39; text-shadow:1px 1px 6px #212d18;
}
.card h3 {font-size:1.21rem;color:#f5f920;margin-bottom:10px;}
.form-section label, .medical-records label {
  display:block; margin: 12px 0 7px 0; font-weight:400; color:#b9e88e; font-size:1rem; letter-spacing:0.03em;
}
input, textarea {
  width:100%; padding:8px 13px; margin-bottom:9px; border-radius:6px; border:1px solid #567e51; font-size:1rem; background:#1f282f; color:#d6efcf; outline: none; transition:border 0.2s;
}
input:focus, textarea:focus { border:1.3px solid #8bc34a; background:#263339; }
button {
  margin-top:15px; padding:10px 28px; background:#738e06; color:#242b1a; border:none; border-radius:7px; font-size:1.09rem;
  font-family:'Oswald',sans-serif; font-weight:700; cursor:pointer; transition:.2s; box-shadow:0 2px 10px rgba(130,180,80,0.08);
}
button:hover { background:#b0d432; color:#2E2C3B; }
.message, .error {
  padding:13px 15px; border-radius:8px; margin-bottom:16px; text-align:center;
  font-weight:700; font-family:'Oswald',sans-serif; box-shadow:0 2px 8px rgba(50,70,30,0.20);
}
.message {background:#69f0ae;color:#2d3a1e;}
.error {background:#ff5252;color:#fff;}
.medical-records { margin-top:36px; background:#26334fda; border-radius:13px; padding:1.8rem 2.1rem; box-shadow:0 6px 22px rgba(60,80,80,0.09);}
.medical-records h2 {font-size:1.27rem;}
.medical-records table {
  width:100%; border-collapse:collapse; margin-top:12px; font-size:0.97rem; background:#253040; border-radius:7px; box-shadow:0 2px 10px rgba(70,90,30,0.07);
}
.medical-records th, .medical-records td {
  border: none; border-bottom:1.2px solid #324156; padding:12px 8px; text-align:left; color:#f0ffde; background:none;
}
.medical-records th {
  background:#2e391d !important; font-weight:700; color:#f9f92a; letter-spacing:0.07em; font-family:'Oswald',sans-serif;
}
.medical-records td a {
  color:#7ee315; font-weight:700; text-decoration:none; font-size:1.07rem;
}
.medical-records td a:hover {color:#fff400;}
.medical-records td {vertical-align:top;}
.medical-records table tr:last-child td {border-bottom:none;}
.medical-records .table-empty {color:#ffea00;font-style:italic;}
@media(max-width:760px){
  .profile-wrapper, .card, .medical-records {max-width:95vw;padding:1rem;}
  .card {padding:1.3rem 1.1rem;}
  .medical-records {padding:1rem 0.7rem;}
  .medical-records table, .medical-records th, .medical-records td {font-size:0.95rem;}
}
</style>
</head>
<body>
<div class="profile-wrapper">
  <div class="card">
    <h1><i class="fas fa-user-circle"></i> Personnel Profile</h1>
    <h2><?php echo htmlspecialchars($full_name); ?></h2>
    <?php if ($upload_success): ?><div class="message"><?php echo htmlspecialchars($upload_success); ?></div><?php endif; ?>
    <?php if ($upload_error): ?><div class="error"><?php echo htmlspecialchars($upload_error); ?></div><?php endif; ?>
    <form method="post" action="" autocomplete="off" class="form-section">
      <h3>Edit Personal Details</h3>
      <input type="hidden" name="update_personnel" value="1" />
      <label for="full_name">Full Name</label>
      <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required />

      <label for="email">Email</label>
      <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required />

      <label for="dob">Date of Birth</label>
      <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($dob); ?>" required />

      <label for="contact_number">Contact Number</label>
      <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>" />

      <label for="address">Address</label>
      <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>

      <button type="submit"><i class="fas fa-save"></i> Update Details</button>
    </form>
  </div>
  <div class="medical-records">
    <h2><i class="fas fa-notes-medical"></i> Upload Medical Document</h2>
    <form method="post" enctype="multipart/form-data" action="">
      <input type="hidden" name="upload_medical" value="1" />
      <label for="medical_description">Description of medical report</label>
      <textarea name="medical_description" id="medical_description" required placeholder="Description of medical report"></textarea>
      <label for="medical_doc">Choose file (PDF, JPG, PNG max 5MB)</label>
      <input type="file" name="medical_doc" id="medical_doc" accept=".pdf, .jpg, .jpeg, .png" required />
      <button type="submit"><i class="fas fa-upload"></i> Upload Document</button>
    </form>
    <h3>Medical History Records</h3>
    <table>
      <tr><th>Date</th><th>Description</th><th>Document</th></tr>
      <?php if (count($medical_records) > 0): ?>
        <?php foreach ($medical_records as $record): ?>
          <tr>
            <td><?php echo htmlspecialchars($record['record_date']); ?></td>
            <td><?php echo htmlspecialchars($record['description']); ?></td>
            <td>
              <?php if ($record['document_path'] && file_exists($record['document_path'])): ?>
                <a href="<?php echo htmlspecialchars($record['document_path']); ?>" target="_blank"><i class="fas fa-file-alt"></i> View</a>
              <?php else: ?>
                <span class="table-empty">No Document</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="3" class="table-empty">No medical records found.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
