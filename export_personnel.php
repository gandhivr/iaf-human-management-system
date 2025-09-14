<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR Manager') {
    header("Location: login.php");
    exit();
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=personnel_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Full Name', 'Email', 'DOB', 'Contact Number', 'Role', 'Address']);

$result = $conn->query("SELECT id, full_name, email, DOB, contact_number, role, address FROM personnel ORDER BY full_name ASC");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['full_name'],
        $row['email'],
        $row['DOB'],
        $row['contact_number'],
        $row['role'],
        $row['address']
    ]);
}

fclose($output);
exit();
?>
