<?php
session_start();
include 'connection.php'; // Koneksi ke database

if (isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validasi input tidak boleh kosong
    if (!empty($email) && !empty($username) && !empty($password) && !empty($confirm_password)) {
        // Cek apakah password dan konfirmasi password cocok
        if ($password !== $confirm_password) {
            $error = "Password dan Konfirmasi Password tidak cocok!";
        } else {
            // Cek apakah email sudah terdaftar
            $check_query = "SELECT * FROM admin WHERE email = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email sudah terdaftar sebagai admin!";
            } else {
                // Hash password sebelum disimpan
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Query insert data ke database admin
                $query = "INSERT INTO admin (email, username, password, gambar) VALUES (?, ?, ?, '')";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('sss', $email, $username, $hashed_password);

                if ($stmt->execute()) {
                    $success = "Admin berhasil didaftarkan!";
                } else {
                    $error = "Terjadi kesalahan: " . $stmt->error;
                }
            }
        }
    } else {
        $error = "Harap isi semua bidang!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
</head>
<style>
  body {
    background: url('img\mesis.png') no-repeat center center/cover;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    font-family: Arial, sans-serif;
    margin: 0;
}

.regist-container {
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
}

.input-group i {
    margin-right: 10px;
    color: #ff69b4;
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
<body>
    <div class="regist-container">
        <h2>REGISTER ADMIN</h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <?php if (isset($success)) { echo "<p class='success'>$success</p>"; } ?>
        <form action="" method="POST">
            <div class="input-group">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" placeholder="Enter email" required>
            </div>
            <div class="input-group">
                <i class="bi bi-person"></i>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="input-group">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <div class="input-group">
                <i class="bi bi-lock-fill"></i>
                <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <p><a href="forgot.php">Forgot your password?</a></p>
            <p>Already have an account  <a href="login.php">Login</a></p>
            <button type="submit" name="register">Register</button>
        </form>
    </div>
</body>
</html>


    <!-- JavaScript untuk Show/Hide Password -->
    <script>
        document.getElementById("togglePassword").addEventListener("click", function() {
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
