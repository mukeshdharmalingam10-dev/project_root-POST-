<?php
session_start();
include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $message = "All fields are required!";
    } else {
        // Prepare statement to avoid SQL injection
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Check if password is hashed in DB
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $message = "Incorrect password!";
                }
            } else {
                $message = "Username not found!";
            }
            $stmt->close();
        } else {
            $message = "Database error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link rel="stylesheet" href="assets/css/login.css">
<script src="assets/js/login.js" defer></script>
</head>
<body>
<div class="login-container">
    <form method="POST" class="login-form" id="loginForm" novalidate>
        <h2>Login</h2>

        <!-- Server-side error message -->
        <?php if($message) echo "<p class='error'>$message</p>"; ?>

        <div class="form-group">
            <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <span class="error-msg" id="usernameError"></span>
        </div>

      <div class="form-group password-group">
    <input type="password" name="password" id="password" placeholder="Password" maxlength="8">
    <span class="toggle-eye" onclick="togglePassword()">👁</span>
    <span class="error-msg" id="passwordError"></span>
</div>


        <button type="submit">Login</button>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </form>
</div>
</body>
</html>
