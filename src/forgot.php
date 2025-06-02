<?php
session_start();
include 'connection.php';

// Tambahkan library PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if (isset($_POST['submit'])) {
    $email = $_POST['email'];

    // Cek apakah email ada di database
    $query = "SELECT * FROM admin WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Buat token unik dan simpan ke database
        $token = bin2hex(random_bytes(50));
        $update_query = "UPDATE admin SET reset_token = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ss', $token, $email);
        $update_stmt->execute();

        // Buat link reset password yang benar
        $reset_link = "http://kasir.test:8080/src/reset_password.php?email=" . urlencode($email) . "&token=" . $token;

        // Kirim email menggunakan PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'adinda.28122020@gmail.com'; // Ganti dengan emailmu
            $mail->Password   = 'qedl navz oecj dbsa';  // Ganti dengan password atau app password Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('no-reply@kasir.com', 'Kasir');
            $mail->addAddress($email);

            $mail->Subject = "Password Reset Request";
            $mail->Body    = "Klik link berikut untuk reset password: <a href='$reset_link'>$reset_link</a>";
            $mail->isHTML(true);

            $mail->send();
            echo "<script>alert('Email reset password telah dikirim!'); window.location='login.php';</script>";
        } catch (Exception $e) {
            echo "<script>alert('Gagal mengirim email: {$mail->ErrorInfo}');</script>";
        }
    } else {
        echo "<script>alert('Email tidak ditemukan!');</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Lupa Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
</head>

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

.forget-container {
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
<body>
<div class="forget-container">
    <form action="" method="POST">
    <div class="input-group">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" placeholder="Masukkan email" required>
</div>            
        <button type="submit" name="submit">Kirim Link Reset</button>
    </form>
</body>
</html>
