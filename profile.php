<?php
session_start();
include "db.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch full user details using prepared statement
$stmt = $conn->prepare("SELECT full_name, email, mobile, username, dob, gender, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    // Fallback: if for some reason user not found, redirect to login
    header("Location: login.php");
    exit;
}

// Determine profile image path
$profileSrc = !empty($user['profile_pic']) ? $user['profile_pic'] : "uploads/profile/default.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .profile-wrapper {
            max-width: 720px;
            margin: 40px auto;
            background: #ffffff;
            padding: 32px 36px;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
        }

        .profile-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2563eb;
        }

        .profile-header h2 {
            margin: 0;
            font-size: 22px;
            color: #111827;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
            gap: 18px 32px;
        }

        .profile-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .profile-field span {
            display: block;
            font-size: 15px;
            color: #111827;
        }

        .profile-actions {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .profile-actions a {
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-logout {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: #ffffff;
        }

        @media (max-width: 640px) {
            .profile-wrapper {
                margin: 20px;
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-wrapper">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($profileSrc) ?>" alt="Profile">
            <div>
                <h2><?= htmlspecialchars($user['full_name']) ?></h2>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>

        <div class="profile-grid">
            <div class="profile-field">
                <label>Username</label>
                <span><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="profile-field">
                <label>Mobile</label>
                <span><?= htmlspecialchars($user['mobile']) ?></span>
            </div>
            <div class="profile-field">
                <label>Date of Birth</label>
                <span><?= htmlspecialchars($user['dob']) ?></span>
            </div>
            <div class="profile-field">
                <label>Gender</label>
                <span><?= htmlspecialchars($user['gender']) ?></span>
            </div>
        </div>

        <div class="profile-actions">
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
</body>
</html>

