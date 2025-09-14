<?php
session_start();
include 'db.php'; // Your DB connection

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (count($errors) === 0) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email is already registered.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);

            if ($insert_stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $errors[] = "Registration failed, please try again.";
            }
            $insert_stmt->close();
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
<title>Register - Indian Air Force Management Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Roboto&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Roboto', Arial, sans-serif;
    margin: 0;
    background: linear-gradient(135deg, #23302c 20%, #445f43 95%);
    color: #cdd9cc;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    padding: 20px;
}
.container {
    background: #2f4640;
    padding: 42px 40px 50px;
    border-radius: 12px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.7);
    text-align: center;
}
h2 {
    font-family: 'Oswald', sans-serif;
    font-weight: 700;
    font-size: 2.4rem;
    color: #a4c639;
    margin-bottom: 28px;
    text-transform: uppercase;
    letter-spacing: 2px;
}
label {
    display: block;
    margin-top: 15px;
    font-weight: 600;
    font-size: 1rem;
    color: #d0ddb3;
    margin-bottom: 6px;
    text-align: left;
}
input[type="text"],
input[type="email"],
input[type="password"],
select {
    width: 100%;
    padding: 13px 16px;
    border-radius: 6px;
    border: none;
    background: #45634d;
    color: #e0e8d6;
    font-size: 1rem;
    box-shadow: inset 2px 2px 10px rgba(0,0,0,0.5);
    transition: background 0.3s;
    outline: none;
    font-family: inherit;
}
input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus,
select:focus {
    background: #74d14a;
    color: #132012;
}
button {
    width: 100%;
    margin-top: 30px;
    padding: 14px 0;
    background: #a4c639;
    color: #1e2d26;
    font-weight: 700;
    font-size: 1.25rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(164,198,57,0.8);
    transition: background 0.3s, color 0.3s;
    font-family: 'Oswald', sans-serif;
}
button:hover, button:focus {
    background: #879f2a;
    color: #fefeea;
}
.errors {
    margin-bottom: 18px;
    padding: 15px 20px;
    background: #902e2e;
    color: #f7d7d7;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.3;
    text-align: left;
}
.errors p {
    margin: 0 0 8px;
}
p.login-link {
    margin-top: 28px;
    text-align: center;
    font-weight: 600;
    font-size: 1rem;
    color: #a4c639;
}
p.login-link a {
    color: #d4e157;
    font-weight: 700;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color 0.3s;
}
p.login-link a:hover, p.login-link a:focus {
    border-color: #d4e157;
}
</style>
</head>
<body>

<div class="container">
    <h2>Register</h2>
    <?php if (!empty($errors)): ?>
    <div class="errors">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post" action="">
        <label for="full_name">Full Name</label>
        <input id="full_name" type="text" name="full_name" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required />

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required />

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required />

        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" type="password" name="confirm_password" required />

        <label for="role">Role</label>
        <select id="role" name="role" required>
            <option value="">Select Role</option>
            <option value="Commander" <?php if(isset($role) && $role=="Commander") echo "selected"; ?>>Commander</option>
            <option value="HR Manager" <?php if(isset($role) && $role=="HR Manager") echo "selected"; ?>>HR Manager</option>
            <option value="Medical Officer" <?php if(isset($role) && $role=="Medical Officer") echo "selected"; ?>>Medical Officer</option>
            <option value="Training Department" <?php if(isset($role) && $role=="Training Department") echo "selected"; ?>>Training Department</option>
            <option value="Ground Staff" <?php if(isset($role) && $role=="Ground Staff") echo "selected"; ?>>Ground Staff</option>
        </select>

        <button type="submit">Register</button>
    </form>
    <p class="login-link">Already registered? <a href="login.php">Login here</a>.</p>
</div>

</body>
</html>
