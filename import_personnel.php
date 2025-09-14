<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR Manager') {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $row++;
            if ($row == 1) continue; // Skip header row

            list($id, $full_name, $email, $dob, $contact_number, $role, $address) = $data;

            if (empty($full_name) || empty($email)) continue;

            if (!empty($id) && is_numeric($id)) {
                $stmt = $conn->prepare("UPDATE personnel SET full_name=?, email=?, DOB=?, contact_number=?, role=?, address=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("ssssssi", $full_name, $email, $dob, $contact_number, $role, $address, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO personnel (full_name, email, DOB, contact_number, role, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $full_name, $email, $dob, $contact_number, $role, $address);
                $stmt->execute();
                $stmt->close();
            }
        }
        fclose($handle);
        $message = "CSV data imported successfully.";
    } else {
        $message = "Unable to read the uploaded file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Import Personnel CSV</title>
<style>
  /* Add your CSS styling here */
</style>
</head>
<body>
<div>
    <h2>Import Personnel from CSV</h2>
    <?php if ($message): ?>
      <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label>Select CSV file (with header row)</label><br/>
        <input type="file" name="csv_file" accept=".csv" required /><br />
        <button type="submit">Upload and Import</button>
    </form>
    <p><a href="dashboard.php">Back to Dashboard</a></p>
</div>
</body>
</html>
