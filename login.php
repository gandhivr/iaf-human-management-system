<?php
session_start();
include 'db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $full_name, $hashed_password, $role);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role'] = $role;
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
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
<title>Login - Indian Air Force Management Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&display=swap" rel="stylesheet">
<style>
body {
  margin: 0;
  min-height: 100vh;
  font-family: 'Oswald', Arial, sans-serif;
  background: radial-gradient(ellipse 80% 80% at 50% 30%, #345f43 0%, #23302c 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.header-banner {
  background: linear-gradient(90deg, #bcee5c, #74d14a 80%);
  color: #162315;
  font-family: 'Oswald', sans-serif;
  font-size: 2.3rem;
  font-weight: bold;
  letter-spacing: 2px;
  box-shadow: 0 8px 38px #7ce44144, 0 4px 12px #23403333;
  padding: 30px 38px 30px 38px;
  border-radius: 18px;
  text-align: center;
  margin-bottom: -30px;
  position: relative;
  z-index: 2;
  text-transform: uppercase;
  transition: box-shadow 0.3s;
  cursor: pointer;
  text-decoration: none;
  filter: drop-shadow(0 0 8px #bcee5c80);
}
.header-banner:hover, .header-banner:focus {
  box-shadow: 0 10px 48px #bcee5c99;
  filter: drop-shadow(0 0 18px #c6fc8b);
  color: #1d2f15;
}
.card {
  background: #304838cc;
  border-radius: 20px;
  box-shadow: 0 14px 38px 0 rgba(44,84,61,0.18), 0 4px 16px #639d2922;
  padding: 54px 40px 40px;
  width: 100%;
  max-width: 440px;
  text-align: center;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  z-index: 1;
}
.login-title {
  font-size: 2rem;
  color: #bcee5c;
  letter-spacing: 2px;
  margin-bottom: 42px;
  font-weight: 700;
  text-transform: uppercase;
}
label {
  color: #cbeeb6;
  text-align: left;
  font-weight: 600;
  font-size: 1rem;
  margin-bottom: 7px;
  display: block;
}
input[type="email"], input[type="password"] {
  width: 100%;
  font-size: 1.16rem;
  padding: 14px 16px;
  border-radius: 8px;
  border: none;
  outline: none;
  background: #3b5b45;
  color: #eaffc9;
  margin-bottom: 23px;
  font-family: inherit;
  box-shadow: 0 2px 12px #23403312 inset;
  transition: background 0.22s, color 0.22s;
}
input[type="email"]:focus, input[type="password"]:focus {
  background: #74d14a;
  color: #2a3c1f;
}
button {
  margin-top: 15px;
  padding: 18px 0;
  width: 100%;
  background: linear-gradient(90deg,#c2ff66 60%,#70c540 100%);
  color: #15321d;
  font-weight: 800;
  font-size: 1.20rem;
  font-family: 'Oswald',sans-serif;
  border: none;
  border-radius: 10px;
  box-shadow: 0 8px 32px #c2ff6622;
  cursor: pointer;
  transition: background 0.22s, box-shadow 0.2s, color 0.2s;
}
button:hover, button:focus {
  background: linear-gradient(90deg,#8ae62e 30%, #74d14a 100%);
  color: #fff;
  box-shadow: 0 14px 48px #bcee5c33;
}
.errors {
  background: #a83c3c;
  color: #fbeaea;
  font-weight: 700;
  border-radius: 10px;
  margin-bottom: 20px;
  font-size: 1rem;
  padding: 14px 18px;
  box-shadow: 0 1px 6px #a83c3c44;
  text-align: left;
}
.errors p { margin: 0 0 5px; }
.register-link {
  margin-top: 32px;
  font-weight: 600;
  font-size: 1.08rem;
  color: #bcee5c;
}
.register-link a {
  color: #fff892;
  font-weight: 700;
  text-decoration: underline;
  transition: color .22s, text-decoration .2s;
}
.register-link a:hover, .register-link a:focus {
  color: #bcee5c;
  text-decoration: underline wavy;
}
@media screen and (max-width: 600px) {
  .card, .header-banner { max-width: 98vw; padding-left: 10px; padding-right: 10px; }
  body { padding: 2vw; }
}
</style>
</head>
<body>
  <a class="header-banner" href="index.php">
    INDIAN AIR FORCE<br>
    MANAGEMENT PORTAL
  </a>
  <div class="card">
    <div class="login-title">Login</div>
    <?php if (!empty($errors)): ?>
      <div class="errors">
        <?php foreach ($errors as $error) {
          echo "<p>" . htmlspecialchars($error) . "</p>";
        } ?>
      </div>
    <?php endif; ?>
    <form method="post" action="">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required />
      <label for="password">Password</label>
      <input id="password" type="password" name="password" required />
      <button type="submit">Login</button>
    </form>
    <div class="register-link">Don't have an account? <a href="register.php">Register here.</a></div>
  </div>
</body>
</html>
