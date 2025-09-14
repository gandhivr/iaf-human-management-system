<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path to composer autoload

$userId = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$mysqli = new mysqli("localhost", "root", "", "iaf_human_management"); // change credentials
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

use PHPGangsta_GoogleAuthenticator;

$ga = new PHPGangsta_GoogleAuthenticator();

// Get existing secret
$stmt = $mysqli->prepare("SELECT mfa_secret FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($existingSecret);
$stmt->fetch();
$stmt->close();

if (!$existingSecret) {
    // Generate new secret and save it
    $secret = $ga->createSecret();

    $stmt = $mysqli->prepare("UPDATE users SET mfa_secret = ? WHERE id = ?");
    $stmt->bind_param("si", $secret, $userId);
    $stmt->execute();
    $stmt->close();
} else {
    $secret = $existingSecret;
}

// Generate QR Code URL for Google Authenticator app scanning
$appName = "IAF Human Management";
$qrCodeUrl = $ga->getQRCodeGoogleUrl($appName . " (" . $full_name . ")", $secret);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>MFA Setup - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; padding: 20px; background: #f4f4f9;}
  .container {max-width: 500px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow:0 0 10px rgba(0,0,0,0.1);}
  img {display: block; margin: 20px auto;}
  p {text-align: center;}
  a {display: block; width: fit-content; margin: 20px auto; padding: 10px 20px; background: #007BFF; color: white; text-decoration: none; border-radius: 4px;}
  a:hover {background: #0056b3;}
</style>
</head>
<body>
<div class="container">
    <h2>MFA Setup for <?php echo htmlspecialchars($full_name); ?></h2>
    <p>Scan this QR code with Google Authenticator app.</p>
    <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code for Google Authenticator" />
    <p>Or enter this secret manually: <strong><?php echo htmlspecialchars($secret); ?></strong></p>
    <p><a href="dashboard.php">Back to Dashboard</a></p>
</div>
</body>
</html>
