<?php
session_start();
ob_start();

// Koneksi ke database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'kasir';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fungsi untuk memeriksa duplikasi email dan username
function cekDuplikasi($conn, $email, $username) {
    $sql = "SELECT * FROM admin WHERE email = ? OR username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0; // Mengembalikan true jika ada duplikasi
}

// Proses tambah admin
if (isset($_POST['tambah_admin'])) {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
    $gambar = '';

    // Cek jika ada duplikasi email atau username
    if (cekDuplikasi($conn, $email, $username)) {
        echo "<script>alert('Email atau Username sudah terdaftar!');</script>";
    } else {
        // Cek jika ada file gambar yang diunggah
        if (!empty($_FILES['gambar']['name'])) {
            $targetDir = "uploads/"; // Folder penyimpanan gambar
            $fileName = basename($_FILES['gambar']['name']);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            // Validasi format file
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetFilePath)) {
                    $gambar = $targetFilePath;
                } else {
                    echo "<script>alert('Gagal mengunggah gambar.');</script>";
                }
            } else {
                echo "<script>alert('Format gambar tidak valid. Hanya JPG, JPEG, PNG, GIF.');</script>";
            }
        }

        // Simpan ke database
        $sql = "INSERT INTO admin (email, username, password, gambar, status) VALUES (?, ?, ?, ?, 'tidak aktif')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $email, $username, $password, $gambar);

        if ($stmt->execute()) {
            echo "<script>alert('Admin berhasil ditambahkan!'); window.location='admin.php';</script>";
        } else {
            echo "<script>alert('Gagal menambahkan admin.');</script>";
        }

        $stmt->close();
    }
}

$conn->close();
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Admin</title>
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

        .container {
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

        .form-group {
            display: flex;
            align-items: center;
            background: #fff;
            border: 2px solid #ff69b4;
            border-radius: 8px;
            padding: 8px;
            margin: 10px 0;
            position: relative;
        }

        .form-group i {
            color: #ff69b4;
            margin-right: 10px;
        }

        .form-group input {
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
            margin-top: 10px;
        }

        button:hover {
            background: #ff1493;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Tambah Admin</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <i class="bi bi-envelope"></i>
            <input type="email" name="email" placeholder="Email" required>
        </div>
        <div class="form-group">
            <i class="bi bi-person"></i>
            <input type="text" name="username" placeholder="Username" required>
        </div>
        <div class="form-group">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" placeholder="Password" required>
        </div>
        <div class="form-group">
            <i class="bi bi-lock"></i>
            <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required>
        </div>
        <div class="form-group">
            <i class="bi bi-image"></i>
            <input type="file" name="gambar" accept="image/*">
        </div>
        <button type="submit" name="tambah_admin">Simpan</button>
        <a href="admin.php" style="display: block; margin-top: 10px; color: #000; font-weight: bold; text-decoration: none;">Batal</a>
    </form>
</div>
</body>
</html>
