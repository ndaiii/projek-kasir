<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['id_admin'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['id_admin'];

// Handle logout (jika mau pakai tombol logout di sini juga bisa, tapi lebih baik pakai logout.php)
if (isset($_GET['logout'])) {
    $updateStatus = "UPDATE admin SET status = 'tidak aktif' WHERE id = ?";
    $stmt = $conn->prepare($updateStatus);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();

    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle hapus admin, tapi hanya untuk admin yang statusnya bukan aktif
if (isset($_GET['hapus'])) {
    $hapus_id = intval($_GET['hapus']);

    // Cek status admin yang mau dihapus
    $cekStatus = "SELECT status FROM admin WHERE id = ?";
    $stmt = $conn->prepare($cekStatus);
    $stmt->bind_param("i", $hapus_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row && $row['status'] !== 'aktif') {
        $deleteSQL = "DELETE FROM admin WHERE id = ?";
        $stmt = $conn->prepare($deleteSQL);
        $stmt->bind_param("i", $hapus_id);
        if ($stmt->execute()) {
            header("Location: admin.php");
            exit();
        } else {
            $error = "Gagal menghapus admin!";
        }
        $stmt->close();
    } else {
        $error = "Admin yang aktif tidak bisa dihapus!";
    }
}

// Ambil semua data admin
$sql = "SELECT id, email, username, gambar, status FROM admin";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sweetpay Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <style>
        body {
            background-color: #f8d5e0;
            font-family: Arial, sans-serif;
        }
        .header {
            background-color: #a05a5a;
            color: white;
            padding: 15px;
            text-align: center;
            position: relative;
            font-size: 20px;
            font-weight: bold;
        }
        .header i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            cursor: pointer;
        }
        .header .back-icon {
            left: 15px;
        }
        .header .user-icon {
            right: 15px;
        }
        .content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        .admin-card {
            background-color: #f5e1da;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
        }
        .admin-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .admin-card p {
            margin: 5px 0;
            font-size: 14px;
        }
        .search-bar input {
            border-radius: 5px;
        }
        .add-admin-btn {
            float: right;
            background-color: #a05a5a;
            color: white;
            border-radius: 5px;
        }
        .add-admin-btn:hover {
            background-color: #8c4848;
        }
        .btn-action {
            margin-top: 10px;
        }
        .btn-disabled {
            pointer-events: none;
            opacity: 0.6;
        }
    </style>
</head>
<body>
<div class="header">
    <i class="fas fa-arrow-left back-icon" onclick="window.location.href='dashboard.php'"></i>
    Sweetpay
    <i class="fas fa-user user-icon" onclick="window.location.href='logout.php'"></i> <!-- Logout via logout.php -->
</div>

<div class="container">
    <div class="content">
        <h2 class="text-center mb-4">Daftar Admin</h2>
        <?php if (isset($error)) echo "<p class='text-danger text-center'>$error</p>"; ?>
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" id="search" placeholder="Cari admin...">
            </div>
            <div class="col-md-6 text-end">
                <a href="tambahadmin.php" class="btn add-admin-btn">Tambah Admin</a>
            </div>
        </div>

        <div class="row" id="admin-list">
            <?php while ($row = $result->fetch_assoc()) { ?>
                <div class="col-md-4 mb-3">
                    <div class="admin-card">
                        <img src="<?php echo htmlspecialchars($row['gambar']); ?>" alt="Admin profile picture" onerror="this.src='default-avatar.png'">
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($row['email']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                        <p><strong>Status:</strong>
                            <?php if ($row['status'] == 'aktif') { ?>
                                <span style="color: green; font-weight: bold;">Aktif</span>
                            <?php } else { ?>
                                <span style="color: red;">Tidak aktif</span>
                            <?php } ?>
                        </p>
                        <div class="btn-action">
                            <a href="editadmin.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <?php if ($row['status'] == 'aktif') { ?>
                                <button class="btn btn-danger btn-disabled" title="Admin aktif tidak bisa dihapus" disabled>Hapus</button>
                            <?php } else { ?>
                                <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus admin ini?')">Hapus</a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    // Simple search/filter admin cards by username or email
    document.getElementById('search').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const admins = document.querySelectorAll('#admin-list .admin-card');

        admins.forEach(card => {
            const email = card.querySelector('p:nth-child(2)').textContent.toLowerCase();
            const username = card.querySelector('p:nth-child(3)').textContent.toLowerCase();
            if (email.includes(filter) || username.includes(filter)) {
                card.parentElement.style.display = '';
            } else {
                card.parentElement.style.display = 'none';
            }
        });
    });
</script>
</body>
</html>
