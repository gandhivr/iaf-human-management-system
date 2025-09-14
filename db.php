<?php
$servername = "localhost";
$username = "root";  // Change as per your MySQL setup
$password = "";      // Change as per your MySQL setup
$dbname = " iaf_human_management";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
