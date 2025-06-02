<?php
session_start();
include 'connection.php';

if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];
} else {
    die("Token atau email tidak valid!");
}

if (isset($_POST['reset'])) {
    $new_password = $_POST['password'];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Periksa token dan update password
    $query = "SELECT * FROM admin WHERE email = ? AND reset_token = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update password dan hapus token
        $update_query = "UPDATE admin SET password = ?, reset_token = NULL WHERE email = ? AND reset_token = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('sss', $hashed_password, $email, $token);

        if ($update_stmt->execute()) {
            echo "<script>alert('Password berhasil diperbarui! Silakan login.'); window.location='login.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui password.');</script>";
        }
    } else {
        echo "<script>alert('Token tidak valid atau sudah digunakan!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password</title>

<!DOCTYPE html>
<html lang="en">

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body {
            background: url('mesis.png') no-repeat center center/cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .reset-container {
            background: rgba(255, 182, 193, 0.9);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 350px;
            backdrop-filter: blur(10px);
        }

        h2 {
            color: #000;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            align-items: center;
            background: #fff;
            border: 2px solid #ff69b4;
            border-radius: 8px;
            padding: 8px;
            margin: 10px 0;
            position: relative;
        }

        .input-group i {
            color: #ff69b4;
        }

        .input-group input {
            border: none;
            outline: none;
            width: 100%;
            padding: 8px;
            font-size: 16px;
        }

        #togglePassword {
            position: absolute;
            right: 12px;
            cursor: pointer;
            font-size: 18px;
            color: #ff69b4;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #ff69b4;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 18px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }

        button:hover {
            background: #ff1493;
        }

        .error {
            color: red;
            font-size: 14px;
        }

        a {
            color: #000;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        <form action="" method="POST">
            <div class="input-group">
                <i class="bi bi-lock-fill"></i>
                <input type="password" name="password" id="password" placeholder="Password Baru" required>
                <i class="bi bi-eye-slash" id="togglePassword"></i>
            </div>
            <button type="submit" name="reset">Reset Password</button>
        </form>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById("togglePassword").addEventListener("click", function() {
            let passwordField = document.getElementById("password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                this.classList.replace("bi-eye-slash", "bi-eye");
            } else {
                passwordField.type = "password";
                this.classList.replace("bi-eye", "bi-eye-slash");
            }
        });
    </script>
</body>
</html>
