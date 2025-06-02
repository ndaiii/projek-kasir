<?php
session_start();
ob_start();
require 'connection.php';

// Cek apakah admin sudah login
if (!isset($_SESSION['id_admin'])) {
    header("Location: login.php");
    exit();
}

// Ambil ID admin yang sedang login
$login_admin_id = $_SESSION['id_admin'];

// Ambil data admin yang akan diedit berdasarkan ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: admin.php");
    exit();
}

// Cek apakah admin yang login mengedit profilnya sendiri atau admin lain
$is_self_edit = ($login_admin_id == $id);

// Query untuk mendapatkan data admin yang akan diedit
$sql = "SELECT * FROM admin WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Admin tidak ditemukan
    header("Location: admin.php");
    exit();
}

$admin = $result->fetch_assoc();
$stmt->close();

// Update admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_admin'])) {
    $username = $_POST['username'];
    $email = $is_self_edit ? $_POST['email'] : $admin['email']; // Email hanya bisa diubah jika mengedit diri sendiri
    $gambar = $admin['gambar']; // Gambar lama
    
    // Proses password jika admin mengedit dirinya sendiri dan mengisi password baru
    $update_password = false;
    $password_hash = $admin['password']; // Password lama
    
    if ($is_self_edit && isset($_POST['password']) && !empty($_POST['password'])) {
        $password = $_POST['password'];
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_password = true;
    }

    // Cek jika email atau username sudah ada, kecuali admin yang sedang diubah
    $checkSql = "SELECT * FROM admin WHERE (email = ? OR username = ?) AND id != ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("ssi", $email, $username, $id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck->num_rows > 0) {
        echo "<script>alert('Email atau Username sudah digunakan oleh admin lain!');</script>";
    } else {
        // Cek jika ada gambar baru yang diunggah (hanya jika mengedit diri sendiri)
        if ($is_self_edit && !empty($_FILES['gambar']['name'])) {
            $targetDir = "uploads/";
            
            // Buat direktori jika belum ada
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $fileName = basename($_FILES['gambar']['name']);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($fileType, $allowedTypes) && move_uploaded_file($_FILES['gambar']['tmp_name'], $targetFilePath)) {
                $gambar = $targetFilePath;
            }
        }

        // Update data admin
        if ($is_self_edit) {
            // Jika mengedit diri sendiri - update semua field
            if ($update_password) {
                $updateSql = "UPDATE admin SET email=?, username=?, password=?, gambar=? WHERE id=?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ssssi", $email, $username, $password_hash, $gambar, $id);
            } else {
                $updateSql = "UPDATE admin SET email=?, username=?, gambar=? WHERE id=?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("sssi", $email, $username, $gambar, $id);
            }
        } else {
            // Jika mengedit admin lain - hanya update username
            $updateSql = "UPDATE admin SET username=? WHERE id=?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $username, $id);
        }

        if ($updateStmt->execute()) {
            echo "<script>alert('Admin berhasil diperbarui!'); window.location.href='admin.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal memperbarui admin: " . $conn->error . "');</script>";
        }
        $updateStmt->close();
    }
    $stmtCheck->close();
}

$conn->close();
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
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

        .cancel {
            display: block;
            margin-top: 10px;
            color: #000;
            font-weight: bold;
            text-decoration: none;
        }
        
        .disabled-field {
            background-color: #f0f0f0;
            color: #888;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Admin</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <i class="bi bi-envelope"></i>
            <input type="email" name="email" value="<?= htmlspecialchars($admin['email']); ?>" <?= (!$is_self_edit) ? 'readonly class="disabled-field"' : 'required'; ?>>
        </div>
        <div class="form-group">
            <i class="bi bi-person"></i>
            <input type="text" name="username" value="<?= htmlspecialchars($admin['username']); ?>" required>
        </div>
        
        <?php if ($is_self_edit): ?>
        <div class="form-group">
            <i class="bi bi-key"></i>
            <input type="password" name="password" placeholder="Password Baru (kosongkan jika tidak diubah)">
        </div>
        <div class="form-group">
            <i class="bi bi-image"></i>
            <input type="file" name="gambar" accept="image/*">
        </div>
        <?php endif; ?>
        
        <button type="submit" name="edit_admin">Simpan</button>
        <a href="admin.php" class="cancel">Batal</a>
    </form>
</div>
</body>
</html>