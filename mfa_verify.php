<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path

if (!isset($_SESSION['mfa_user_id'])) {
    header("Location: login.php");
    exit();
}

use PHPGangsta_GoogleAuthenticator;

$ga = new PHPGangsta_GoogleAuthenticator();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['mfa_code']);
    $userId = $_SESSION['mfa_user_id'];

    $mysqli = new mysqli("localhost", "root", "", "iaf_human_management"); // change as needed
    if ($mysqli->connect_errno) {
        die("Database connection failed: " . $mysqli->connect_error);
    }

    // Get user's secret from DB
    $stmt = $mysqli->prepare("SELECT mfa_secret, full_name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($secret, $full_name, $role);
    $stmt->fetch();
    $stmt->close();

    if (!$secret) {
        $errors[] = "MFA is not enabled for this account.";
    } else {
        $checkResult = $ga->verifyCode($secret, $code, 2); // 2 = 2*30sec tolerance

        if ($checkResult) {
            // MFA verified, complete login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = $role;

            // Unset MFA session variables
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['mfa_full_name']);
            unset($_SESSION['mfa_role']);

            header("Location: dashboard.php");
            exit();
        } else {
            $errors[] = "Invalid authentication code.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>MFA Verification - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; background: #f4f4f9; padding: 20px;}
  .container {max-width: 400px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow:0 0 10px rgba(0,0,0,0.1);}
  input {width: 100%; padding: 10px; margin: 15px 0; border-radius: 4px; border: 1px solid #ccc;}
  button {width: 100%; padding: 10px; background: #007BFF; color: #fff; border: none; border-radius: 4px; cursor: pointer;}
  button:hover {background: #0056b3;}
  .errors {color: red; margin-bottom: 10px;}
</style>
</head>
<body>
<div class="container">
    <h2>Multi-Factor Authentication</h2>
    <p>Enter the 6-digit code from your Google Authenticator app.</p>
    <div class="errors">
        <?php foreach ($errors as $error) { echo "<p>" . htmlspecialchars($error) . "</p>"; } ?>
    </div>
    <form method="post" action="">
        <input type="text" name="mfa_code" placeholder="6-digit authentication code" required maxlength="6" pattern="\d{6}" />
        <button type="submit">Verify</button>
    </form>
    <p><a href="login.php">Cancel and return to login</a></p>
</div>
</body>
</html>
