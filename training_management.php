<?php
session_start();
include 'db.php';

// Authorization check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR Manager', 'Training Department'])) {
    header("Location: login.php");
    exit();
}

// Initialize messages
$success_msg = '';
$error_msg = '';

// Handle form submission for adding/updating training records
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
    $personnel_id = intval($_POST['personnel_id']);
    $training_session_id = intval($_POST['training_session_id']);
    $completion_date = $_POST['completion_date'];
    $result = trim($_POST['result']);
    $feedback = trim($_POST['feedback']);
    $document_path = '';

    // File upload handling
    if (isset($_FILES['training_doc']) && $_FILES['training_doc']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $file_tmp = $_FILES['training_doc']['tmp_name'];
        $file_type = mime_content_type($file_tmp);
        $file_size = $_FILES['training_doc']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $error_msg = "Unsupported file type. Allowed: PDF, JPG, PNG.";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $error_msg = "File size exceeds 5MB limit.";
        } else {
            $upload_dir = 'uploads/training_docs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . "_" . basename($_FILES['training_doc']['name']);
            $destination = $upload_dir . $file_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $document_path = $destination;
            } else {
                $error_msg = "Failed to upload the document.";
            }
        }
    }

    if (!$error_msg) {
        if ($record_id > 0) {
            // Update existing record
            if ($document_path) {
                $stmt = $conn->prepare("UPDATE training_records SET personnel_id=?, training_session_id=?, completion_date=?, result=?, feedback=?, document_path=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("isssssi", $personnel_id, $training_session_id, $completion_date, $result, $feedback, $document_path, $record_id);
            } else {
                $stmt = $conn->prepare("UPDATE training_records SET personnel_id=?, training_session_id=?, completion_date=?, result=?, feedback=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("issssi", $personnel_id, $training_session_id, $completion_date, $result, $feedback, $record_id);
            }
            $stmt->execute();
            $stmt->close();
            $success_msg = "Training record updated successfully.";
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO training_records (personnel_id, training_session_id, completion_date, result, feedback, document_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $personnel_id, $training_session_id, $completion_date, $result, $feedback, $document_path);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Training record added successfully.";
        }
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM training_records WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: training_management.php");
    exit();
}

// Fetch personnel list for dropdown
$personnel_list = [];
$result = $conn->query("SELECT id, full_name FROM personnel ORDER BY full_name ASC");
while ($row = $result->fetch_assoc()) {
    $personnel_list[] = $row;
}

// Fetch training sessions for dropdown
$training_sessions = [];
$result2 = $conn->query("SELECT id, training_name FROM training_sessions ORDER BY training_name ASC");
while ($row = $result2->fetch_assoc()) {
    $training_sessions[] = $row;
}

// Fetch all training records with joins for display
$sql = "SELECT tr.id, tr.personnel_id, tr.training_session_id, p.full_name, ts.training_name, tr.completion_date, tr.result, tr.feedback, tr.document_path
        FROM training_records tr
        JOIN personnel p ON tr.personnel_id = p.id
        JOIN training_sessions ts ON tr.training_session_id = ts.id
        ORDER BY tr.completion_date DESC";
$records_result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Training Management</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@600&display=swap');

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #1e2d26;
    color: #cdd9cc;
    padding: 20px;
    margin: 0;
  }
  .container {
    max-width: 960px;
    margin: auto;
    background: #2f4640;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.7);
    color: #cdd9cc;
  }
  h1, h2 {
    font-family: 'Oswald', sans-serif;
    color: #a4c639;
    margin-bottom: 25px;
    letter-spacing: 2px;
    text-transform: uppercase;
  }
  form label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
  }
  form input[type="date"],
  form input[type="text"],
  form textarea,
  form select,
  form input[type="file"] {
    width: 100%;
    padding: 10px 15px;
    margin-bottom: 16px;
    border-radius: 6px;
    border: none;
    background: #45634d;
    color: #e0e8d6;
    font-size: 1rem;
    box-shadow: inset 2px 2px 8px rgba(0,0,0,0.5);
    transition: background-color 0.3s;
  }
  form input[type="date"]:focus,
  form input[type="text"]:focus,
  form textarea:focus,
  form select:focus,
  form input[type="file"]:focus {
    background: #548e38;
    outline: none;
    color: #f0f4c3;
  }
  form textarea {
    resize: vertical;
    min-height: 80px;
  }
  button {
    padding: 14px 25px;
    background: #a4c639;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1.1rem;
    color: #1e2d26;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(164,198,57,0.8);
    transition: background-color 0.3s, color 0.3s;
  }
  button:hover, button:focus {
    background: #879f2a;
    color: #fefeea;
  }
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 14px;
    margin-top: 40px;
  }
  th {
    background: #a4c639;
    color: #1e2d26;
    font-weight: 700;
    padding: 15px 20px;
    text-align: left;
    border-radius: 8px 8px 0 0;
  }
  td {
    background: #45634d;
    padding: 15px 20px;
    vertical-align: top;
    border-radius: 6px;
    color: #e0e8d6;
    box-shadow: inset 1px 1px 5px rgba(0,0,0,0.6);
  }
  td a {
    color: #d4e157;
    font-weight: 600;
    text-decoration: none;
  }
  td a:hover, td a:focus {
    text-decoration: underline;
  }
  .actions a {
    margin-right: 12px;
    cursor: pointer;
  }
  .message, .error {
    padding: 14px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 1rem;
  }
  .message {
    background-color: #879f2a;
    color: #fefeea;
  }
  .error {
    background-color: #902e2e;
    color: #f7d7d7;
  }
  @media (max-width: 720px) {
    .container {
      padding: 20px;
    }
    table, th, td {
      font-size: 0.9rem;
    }
    button {
      font-size: 1rem;
      padding: 12px 20px;
    }
  }
</style>
</head>
<body>

<div class="container">
  <h1>Training Records Management</h1>

  <?php if ($success_msg): ?>
    <div class="message"><?php echo htmlspecialchars($success_msg); ?></div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
    <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="record_id" id="record_id" value="" />

    <label for="personnel_id">Select Personnel:</label>
    <select name="personnel_id" id="personnel_id" required>
      <option value="">-- Select Personnel --</option>
      <?php foreach ($personnel_list as $person): ?>
        <option value="<?php echo $person['id']; ?>">
          <?php echo htmlspecialchars($person['full_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="training_session_id">Select Training Session:</label>
    <select name="training_session_id" id="training_session_id" required>
      <option value="">-- Select Training --</option>
      <?php foreach ($training_sessions as $session): ?>
        <option value="<?php echo $session['id']; ?>">
          <?php echo htmlspecialchars($session['training_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="completion_date">Completion Date:</label>
    <input type="date" name="completion_date" id="completion_date" required />

    <label for="result">Result:</label>
    <input type="text" name="result" id="result" placeholder="e.g., Passed, Failed, Excellent" />

    <label for="feedback">Feedback/Remarks:</label>
    <textarea name="feedback" id="feedback" rows="4"></textarea>

    <label for="training_doc">Upload Document (optional):</label>
    <input type="file" name="training_doc" id="training_doc" accept=".pdf,.jpg,.jpeg,.png" />

    <button type="submit">Save Record</button>
  </form>

  <h2>Existing Training Records</h2>
  <table>
    <thead>
      <tr>
        <th>Personnel</th>
        <th>Training</th>
        <th>Completion Date</th>
        <th>Result</th>
        <th>Feedback</th>
        <th>Document</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($records_result && $records_result->num_rows > 0): ?>
        <?php while ($record = $records_result->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
            <td><?php echo htmlspecialchars($record['training_name']); ?></td>
            <td><?php echo htmlspecialchars($record['completion_date']); ?></td>
            <td><?php echo htmlspecialchars($record['result']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($record['feedback'])); ?></td>
            <td>
              <?php if ($record['document_path'] && file_exists($record['document_path'])): ?>
                <a href="<?php echo htmlspecialchars($record['document_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                N/A
              <?php endif; ?>
            </td>
            <td class="actions">
              <a href="#" onclick='fillForm(<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)'>Edit</a> |
              <a href="training_management.php?delete_id=<?php echo $record['id']; ?>" onclick="return confirm('Delete this record?');">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7" style="text-align:center; padding: 20px;">No records found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function fillForm(record) {
  document.getElementById('record_id').value = record.id || '';
  document.getElementById('personnel_id').value = record.personnel_id || '';
  document.getElementById('training_session_id').value = record.training_session_id || '';
  document.getElementById('completion_date').value = record.completion_date || '';
  document.getElementById('result').value = record.result || '';
  document.getElementById('feedback').value = record.feedback || '';
}
</script>
</body>
</html>
