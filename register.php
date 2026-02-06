<?php
session_start();
include "db.php";

// Initialize variables
$message = "";
$topDuplicateMessage = "";
$errors  = [];

$full_name = $email = $mobile = $username = $dob = $gender = "";
$terms = false;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get and trim inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $mobile    = trim($_POST['mobile'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $dob       = $_POST['dob'] ?? '';
    $gender    = $_POST['gender'] ?? '';
    $terms     = isset($_POST['terms']);

    // Server-side validations
    if (empty($full_name)) $errors['full_name'] = "Full name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Enter a valid email";
    if (!preg_match('/^\d{10}$/', $mobile)) $errors['mobile'] = "Mobile number must be 10 digits";
    if (!preg_match('/^[a-zA-Z0-9_]{4,}$/', $username)) $errors['username'] = "Username must be alphanumeric (min 4 chars)";
    if (!preg_match('/^[a-zA-Z0-9]{1,8}$/', $password)) $errors['password'] = "Password max 8 chars, alphanumeric only";
    if ($password !== $confirm) $errors['confirm_password'] = "Passwords do not match";
    if (empty($dob)) $errors['dob'] = "Date of birth is required";
    if (empty($gender)) $errors['gender'] = "Please select gender";
    if (!$terms) $errors['terms'] = "You must accept Terms and Conditions";

    // Check for existing username/email only if username/email are valid
    if (!isset($errors['username'])) {
        $stmtCheck = $conn->prepare("SELECT username FROM users WHERE username=?");
        $stmtCheck->bind_param("s", $username);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $errors['username'] = "Username already exists";
            $username = ""; // clear only username field
            $topDuplicateMessage = "The username you entered already exists. Please choose a different username.";
        }
        $stmtCheck->close();
    }

    if (!isset($errors['email'])) {
        $stmtCheck = $conn->prepare("SELECT email FROM users WHERE email=?");
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $errors['email'] = "Email already exists";
            $email = ""; // clear only email field
            $topDuplicateMessage = "The email address you entered is already registered. Please use a different email.";
        }
        $stmtCheck->close();
    }

    // Handle profile image upload
    $profilePath = "uploads/profile/default.png";
    if (!empty($_FILES['profile_pic']['name'])) {
        if (!is_dir("uploads/profile")) {
            mkdir("uploads/profile", 0777, true);
        }
        $fileName = uniqid() . "_" . basename($_FILES['profile_pic']['name']);
        $target   = "uploads/profile/" . $fileName;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
            $profilePath = $target;
        }
    }

    // Insert user if no errors
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users 
            (full_name, email, mobile, username, password, dob, gender, profile_pic)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ssssssss",
            $full_name,
            $email,
            $mobile,
            $username,
            $hashedPassword,
            $dob,
            $gender,
            $profilePath
        );

        if ($stmt->execute()) {
            $message = "Registration Successful! Redirecting to login...";
            $_SESSION['success'] = $message;
            header("refresh:2;url=login.php");
        } else {
            $message = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register</title>
<link rel="stylesheet" href="assets/css/register.css">
<script src="assets/js/register.js" defer></script>
<style>
/* Clean UI for register form */
body {
    font-family: Arial, sans-serif;
    background: #f0f2f5;
    margin: 0;
    padding: 0;
}

.container {
    max-width: 900px;
    margin: 40px auto;
    background: #fff;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}

form h2 {
    text-align: center;
    margin-bottom: 24px;
    color: #1f2937;
}

.row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.col {
    flex: 1;
    min-width: 280px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #374151;
}

input[type=text],
input[type=email],
input[type=password],
input[type=date],
select {
    width: 100%;
    padding: 10px 12px;
    margin-bottom: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

input[type=file] {
    margin-bottom: 12px;
}

.error-msg {
    color: #dc2626;
    font-size: 13px;
    margin-bottom: 8px;
    display: block;
}

.success {
    color: #16a34a;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 600;
}

.error {
    color: #dc2626;
    text-align: center;
    margin-bottom: 15px;
    font-weight: 600;
}

.form-actions {
    text-align: center;
    margin-top: 20px;
}

button {
    padding: 12px 25px;
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    background: #2563eb;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.2s;
}

button:hover {
    background: #1d4ed8;
}

.password-wrapper {
    position: relative;
}

.password-wrapper .eye {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}

.terms {
    margin-top: 10px;
}

.terms input {
    width: auto;
}

p {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
}

p a {
    color: #2563eb;
    text-decoration: none;
}

p a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="container">
<form method="POST" enctype="multipart/form-data" class="form" id="registerForm">

<h2>Create Account</h2>

<!-- Success message only -->
<?php if($message && empty($errors)): ?>
    <p class="success"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- Top duplicate-data message -->
<?php if(!$message && !empty($topDuplicateMessage)): ?>
    <p class="error"><?= htmlspecialchars($topDuplicateMessage) ?></p>
<?php endif; ?>

<div class="row">
    <div class="col">
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($full_name) ?>">
        <span class="error-msg" id="fullNameError"><?= $errors['full_name'] ?? '' ?></span>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>">
        <span class="error-msg" id="emailError"><?= $errors['email'] ?? '' ?></span>

        <label>Mobile</label>
        <input type="text" name="mobile" maxlength="10" value="<?= htmlspecialchars($mobile) ?>">
        <span class="error-msg" id="mobileError"><?= $errors['mobile'] ?? '' ?></span>

        <label>Username</label>
        <input type="text" name="username" autocomplete="off" value="<?= htmlspecialchars($username) ?>">
        <span class="error-msg" id="usernameError"><?= $errors['username'] ?? '' ?></span>

        <label>Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" maxlength="8">
            <span class="eye" onclick="togglePassword('password')">👁</span>
        </div>
        <span class="error-msg" id="passwordError"><?= $errors['password'] ?? '' ?></span>

        <label>Confirm Password</label>
        <div class="password-wrapper">
            <input type="password" name="confirm_password" id="confirm_password" maxlength="8">
            <span class="eye" onclick="togglePassword('confirm_password')">👁</span>
        </div>
        <span class="error-msg" id="confirmPasswordError"><?= $errors['confirm_password'] ?? '' ?></span>
    </div>

    <div class="col">
        <label>Date of Birth</label>
        <input type="date" name="dob" value="<?= htmlspecialchars($dob) ?>">
        <span class="error-msg" id="dobError"><?= $errors['dob'] ?? '' ?></span>

        <label>Gender</label>
        <select name="gender">
            <option value="">Select Gender</option>
            <option value="Male" <?= $gender==='Male'?'selected':'' ?>>Male</option>
            <option value="Female" <?= $gender==='Female'?'selected':'' ?>>Female</option>
            <option value="Other" <?= $gender==='Other'?'selected':'' ?>>Other</option>
        </select>
        <span class="error-msg" id="genderError"><?= $errors['gender'] ?? '' ?></span>

        <label>Profile Image</label>
        <input type="file" name="profile_pic" accept="image/*">

        <div class="terms">
            <input type="checkbox" id="terms" name="terms" <?= $terms?'checked':'' ?>>
            <label for="terms">I accept the <a href="#">Terms and Conditions</a></label>
            <span class="error-msg" id="termsError"><?= $errors['terms'] ?? '' ?></span>
        </div>
    </div>
</div>

<div class="form-actions">
<button type="submit">Create Account</button>
</div>

<p>Already have an account? <a href="login.php">Login</a></p>

</form>
</div>
</body>
</html>
