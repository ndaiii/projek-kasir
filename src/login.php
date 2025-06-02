<?php
session_start();
require 'connection.php'; // Koneksi database

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM admin WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['id_admin'] = $user['id'];
        $_SESSION['admin_name'] = $user['username'];

        // Update status admin jadi aktif
        $update = "UPDATE admin SET status = 'aktif' WHERE id = ?";
        $stmt_update = $conn->prepare($update);
        $stmt_update->bind_param("i", $user['id']);
        $stmt_update->execute();
        $stmt_update->close();

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Sweetpay</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
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
        .login-container {
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
        #togglePassword {
            position: absolute;
            right: 12px;
            cursor: pointer;
            font-size: 18px;
        }
        .input-group input {
            border: none;
            outline: none;
            width: 100%;
            padding: 8px;
            font-size: 16px;
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
    <div class="login-container">
        <h2>LOGIN</h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form action="" method="POST">
            <div class="input-group">
                <i class="bi bi-person"></i>
                <input type="text" name="username" placeholder="Username or email" required />
            </div>

            <div class="input-group">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" placeholder="Password" required />
                <i class="bi bi-eye" id="togglePassword"></i>
            </div>

            <button type="submit" name="login">Login</button>
        </form>
        <p><a href="forgot.php">Forgot your password?</a></p>
    </div>

    <script>
        document.getElementById("togglePassword").addEventListener("click", function () {
            let passwordInput = document.getElementById("password");
            let icon = this;

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove("bi-eye");
                icon.classList.add("bi-eye-slash");
            } else {
                passwordInput.type = "password";
                icon.classList.remove("bi-eye-slash");
                icon.classList.add("bi-eye");
            }
        });
    </script>
</body>
</html>
