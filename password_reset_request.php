<?php
// password_reset_request.php - Complete implementation
session_start();
include 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $full_name);
            $stmt->fetch();
            
            // Generate 6-digit OTP and expiry (15 mins)
            $otp = random_int(100000, 999999);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Insert OTP in password_resets (replace any existing unused OTP)
            $stmt_insert = $conn->prepare("INSERT INTO password_resets (user_id, otp, expiry, used) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE otp = VALUES(otp), expiry = VALUES(expiry), used = 0");
            $stmt_insert->bind_param("iss", $user_id, $otp, $expiry);
            $stmt_insert->execute();
            $stmt_insert->close();
            
            // Send OTP Email (simplified - in production use PHPMailer)
            $subject = "Password Reset OTP - IAF Human Management";
            $message_body = "Dear " . htmlspecialchars($full_name) . ",\n\n";
            $message_body .= "Your OTP for password reset is: " . $otp . "\n";
            $message_body .= "This OTP is valid for 15 minutes only.\n\n";
            $message_body .= "If you didn't request this password reset, please ignore this email.\n\n";
            $message_body .= "Best regards,\nIAF Human Management System";
            
            $headers = "From: no-reply@iafmanagement.in\r\n";
            $headers .= "Reply-To: no-reply@iafmanagement.in\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // In production environment, replace with proper SMTP configuration
            mail($email, $subject, $message_body, $headers);
            
            $message = "OTP sent to your registered email address. Please check your inbox.";
            
            // Redirect to verification page
            $_SESSION['reset_email'] = $email;
            header("Location: password_reset_verify.php");
            exit();
        } else {
            $error = "No user found with this email address.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Password Reset Request - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center;}
  .container {max-width: 400px; width: 100%; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);}
  .logo {text-align: center; margin-bottom: 30px;}
  .logo h1 {color: #007BFF; font-size: 1.8rem; margin: 0; font-weight: bold;}
  .logo p {color: #666; margin: 5px 0 0; font-size: 0.9rem;}
  
  h2 {color: #333; text-align: center; margin-bottom: 25px; font-size: 1.5rem;}
  .form-group {margin-bottom: 20px;}
  .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333;}
  .form-group input {width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s;}
  .form-group input:focus {border-color: #007BFF; outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1);}
  
  .btn {width: 100%; padding: 14px; background: #007BFF; color: white; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: all 0.3s;}
  .btn:hover {background: #0056b3; transform: translateY(-1px); box-shadow: 0 5px 15px rgba(0,123,255,0.4);}
  
  .message {padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: 500;}
  .message.success {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
  .message.error {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
  
  .links {text-align: center; margin-top: 25px;}
  .links a {color: #007BFF; text-decoration: none; font-weight: 500;}
  .links a:hover {text-decoration: underline;}
  
  .info-box {background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #007BFF;}
  .info-box p {margin: 0; color: #495057; font-size: 0.9rem; line-height: 1.5;}
</style>
</head>
<body>
<div class="container">
    <div class="logo">
        <h1>IAF HMS</h1>
        <p>Human Management System</p>
    </div>
    
    <h2>Reset Password</h2>
    
    <div class="info-box">
        <p>Enter your registered email address and we'll send you a 6-digit OTP to reset your password.</p>
    </div>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required placeholder="Enter your registered email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        </div>
        
        <button type="submit" class="btn">Send Reset OTP</button>
    </form>
    
    <div class="links">
        <a href="login.php">Back to Login</a> | 
        <a href="index.php">Home</a>
    </div>
</div>
</body>
</html>

<?php
// password_reset_verify.php - Complete implementation
session_start();
include 'db.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: password_reset_request.php");
    exit();
}

$reset_email = $_SESSION['reset_email'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($otp) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (strlen($otp) !== 6 || !is_numeric($otp)) {
        $error = "OTP must be 6 digits.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Fetch user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            $stmt->close();
            
            // Verify OTP
            $stmt_otp = $conn->prepare("SELECT otp, expiry, used FROM password_resets WHERE user_id = ? AND otp = ? ORDER BY expiry DESC LIMIT 1");
            $stmt_otp->bind_param("is", $user_id, $otp);
            $stmt_otp->execute();
            $stmt_otp->store_result();
            
            if ($stmt_otp->num_rows === 1) {
                $stmt_otp->bind_result($db_otp, $expiry, $used);
                $stmt_otp->fetch();
                $stmt_otp->close();
                
                if ($used) {
                    $error = "This OTP has already been used.";
                } elseif (new DateTime() > new DateTime($expiry)) {
                    $error = "OTP has expired. Please request a new one.";
                } else {
                    // Update password
                    $new_pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt_update = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt_update->bind_param("si", $new_pass_hash, $user_id);
                    
                    if ($stmt_update->execute()) {
                        // Mark OTP as used
                        $stmt_use = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND otp = ?");
                        $stmt_use->bind_param("is", $user_id, $otp);
                        $stmt_use->execute();
                        $stmt_use->close();
                        
                        // Clear session
                        unset($_SESSION['reset_email']);
                        
                        $message = "Password updated successfully! You can now login with your new password.";
                        
                        // Auto redirect after 3 seconds
                        header("refresh:3;url=login.php");
                    } else {
                        $error = "Failed to update password. Please try again.";
                    }
                    $stmt_update->close();
                }
            } else {
                $error = "Invalid OTP.";
                $stmt_otp->close();
            }
        } else {
            $error = "Invalid email.";
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Verify OTP - IAF Human Management</title>
<style>
  body {font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center;}
  .container {max-width: 450px; width: 100%; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);}
  .logo {text-align: center; margin-bottom: 30px;}
  .logo h1 {color: #007BFF; font-size: 1.8rem; margin: 0; font-weight: bold;}
  .logo p {color: #666; margin: 5px 0 0; font-size: 0.9rem;}
  
  h2 {color: #333; text-align: center; margin-bottom: 25px; font-size: 1.5rem;}
  .form-group {margin-bottom: 20px;}
  .form-group label {display: block; margin-bottom: 8px; font-weight: bold; color: #333;}
  .form-group input {width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s;}
  .form-group input:focus {border-color: #007BFF; outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1);}
  
  .otp-input {text-align: center; font-size: 1.5rem; letter-spacing: 3px; font-weight: bold;}
  
  .btn {width: 100%; padding: 14px; background: #007BFF; color: white; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: all 0.3s;}
  .btn:hover {background: #0056b3; transform: translateY(-1px); box-shadow: 0 5px 15px rgba(0,123,255,0.4);}
  
  .message {padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: 500;}
  .message.success {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
  .message.error {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
  
  .links {text-align: center; margin-top: 25px;}
  .links a {color: #007BFF; text-decoration: none; font-weight: 500; margin: 0 10px;}
  .links a:hover {text-decoration: underline;}
  
  .email-info {background: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; color: #495057;}
  
  .password-strength {margin-top: 5px; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; text-align: center; transition: all 0.3s;}
</style>
</head>
<body>
<div class="container">
    <div class="logo">
        <h1>IAF HMS</h1>
        <p>Human Management System</p>
    </div>
    
    <h2>Verify OTP & Set New Password</h2>
    
    <div class="email-info">
        OTP sent to: <strong><?php echo htmlspecialchars($reset_email); ?></strong>
    </div>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <div class="form-group">
            <label for="otp">6-Digit OTP</label>
            <input type="text" id="otp" name="otp" class="otp-input" required placeholder="000000" maxlength="6" pattern="\d{6}">
        </div>
        
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" minlength="8">
            <div id="password-strength" class="password-strength" style="display: none;"></div>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password" minlength="8">
        </div>
        
        <button type="submit" class="btn">Reset Password</button>
    </form>
    
    <div class="links">
        <a href="password_reset_request.php">Resend OTP</a> |
        <a href="login.php">Back to Login</a>
    </div>
</div>

<script>
// OTP input formatting
document.getElementById('otp').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '');
});

// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password-strength');
    
    if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
    }
    
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) strength++; else feedback.push('8+ characters');
    if (/[a-z]/.test(password)) strength++; else feedback.push('lowercase');
    if (/[A-Z]/.test(password)) strength++; else feedback.push('uppercase');
    if (/\d/.test(password)) strength++; else feedback.push('number');
    if (/[^a-zA-Z\d]/.test(password)) strength++; else feedback.push('special char');
    
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    
    strengthDiv.style.background = colors[strength - 1] || '#dc3545';
    strengthDiv.style.color = 'white';
    strengthDiv.style.display = 'block';
    strengthDiv.textContent = labels[strength - 1] || 'Very Weak';
    
    if (feedback.length > 0) {
        strengthDiv.textContent += ' (need: ' + feedback.join(', ') + ')';
    }
});

// Password confirmation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.style.borderColor = '#dc3545';
        this.setCustomValidity('Passwords do not match');
    } else {
        this.style.borderColor = '#ddd';
        this.setCustomValidity('');
    }
});
</script>
</body>
</html>