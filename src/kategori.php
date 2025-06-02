<?php
$conn = new mysqli('localhost', 'root', '', 'kasir');
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Function to check if category name already exists
function isCategoryExists($conn, $kategori, $excludeId = 0) {
    $kategori = $conn->real_escape_string($kategori);
    $query = "SELECT COUNT(*) as total FROM kategori WHERE kategori = '$kategori'";
    
    // If we're updating, exclude the current ID from the check
    if ($excludeId > 0) {
        $query .= " AND id != $excludeId";
    }
    
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_kategori'])) {
    $kategori = $conn->real_escape_string($_POST['kategori']);
    
    // Check if kategori already exists
    if (isCategoryExists($conn, $kategori)) {
        echo "<script>
            alert('Kategori \"$kategori\" sudah ada! Silakan gunakan nama yang berbeda.');
            window.location.href = 'kategori.php';
        </script>";
    } else {
        $conn->query("INSERT INTO kategori (kategori) VALUES ('$kategori')");
        header("Location: kategori.php");
        exit;
    }
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);

    // Ambil nama kategori berdasarkan ID
    $result = $conn->query("SELECT kategori FROM kategori WHERE id = $id");
    $row = $result->fetch_assoc();

    if ($row) {
        $kategoriNama = $conn->real_escape_string($row['kategori']);

        // Ganti kolom 'fid_kategori' yang digunakan di tabel produk
        $cek = $conn->query("SELECT COUNT(*) AS total FROM produk WHERE fid_kategori = $id");
        $jumlah = $cek->fetch_assoc()['total'];

        if ($jumlah > 0) {
            echo "<script>
                alert('Kategori \"$kategoriNama\" tidak bisa dihapus karena masih digunakan oleh $jumlah produk.');
                window.location.href = 'kategori.php';
            </script>";
        } else {
            $conn->query("DELETE FROM kategori WHERE id = $id");
            header("Location: kategori.php");
            exit;
        }
    } else {
        // ID kategori tidak ditemukan
        echo "<script>
            alert('Kategori tidak ditemukan!');
            window.location.href = 'kategori.php';
        </script>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_kategori'])) {
    $id = intval($_POST['id']);
    $kategori = $conn->real_escape_string($_POST['kategori']);
    
    // Check if kategori already exists (excluding current ID)
    if (isCategoryExists($conn, $kategori, $id)) {
        echo "<script>
            alert('Kategori \"$kategori\" sudah ada! Silakan gunakan nama yang berbeda.');
            window.location.href = 'kategori.php';
        </script>";
    } else {
        $conn->query("UPDATE kategori SET kategori = '$kategori' WHERE id = $id");
        header("Location: kategori.php");
        exit;
    }
}

$data = $conn->query("SELECT * FROM kategori ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sweetpay - Kategori</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f8d5e0;
            background-image: url('sprinkle.png');
            background-size: cover;
        }
        header {
            background-color: #a05a5a;
            color: white;
            padding: 15px;
            text-align: center;
            position: relative;
            font-size: 20px;
            font-weight: bold;
        }

        header i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            cursor: pointer;
        }

        .back-icon {
            left: 15px;
            color: #fff; /* Memberikan warna putih pada ikon */
            font-size: 28px; /* Memperbesar ukuran ikon */
        }


        .user-icon {
            right: 15px;
        }

        .container {
            max-width: 850px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fde6d4;
            border-radius: 15px;
            position: relative;
        }
        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 1.5rem;
        }
        th, td {
            padding: 0.75rem;
            border: 1px solid #ccc;
            text-align: center;
        }
        .btn {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
        }
        .btn-lihat {
            background-color: #7d6b4f;
            color: white;
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 235, 235, 0.7);
            backdrop-filter: blur(6px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }
        .modal-box {
            background-color: #fbd6dc;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            width: 300px;
            max-width: 90%;
        }
        .modal-box input {
            padding: 0.5rem;
            border-radius: 10px;
            border: none;
            background-color: #ffd8ee;
            width: 100%;
            margin-bottom: 0.75rem;
        }
        .modal-box button {
            padding: 0.4rem 1rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            margin-right: 0.5rem;
        }
        .btn-simpan {
            background-color: #b0f0c0;
        }
        .btn-hapus {
            background-color: #f6b7b7;
            text-decoration: none;
            padding: 0.4rem 1rem;
            border-radius: 8px;
            display: inline-block;
        }
        .close-btn {
            float: right;
            cursor: pointer;
            font-weight: bold;
            color: #a55;
            background: none;
            border: none;
            font-size: 1.2rem;
        }
        .tambah-btn {
            background-color: #f8cdd8;
            color: #333;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 1rem;
        }
    </style>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>

<header>
    <i class="fas fa-arrow-left back-icon" onclick="window.location.href='dashboard.php'"></i>
    Sweetpay
    <i class="fas fa-user user-icon" onclick="window.location.href='?logout'"></i>
</header>

<div class="container">
    <h2>Data kategori</h2>

    <button class="tambah-btn" onclick="toggleFormTambah()">+ Tambah Kategori</button>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama kategori</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; while ($row = $data->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['kategori']) ?></td>
                <td>
                    <button class="btn btn-lihat" onclick="toggleEdit(<?= $row['id'] ?>)">Lihat</button>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="overlay" id="edit-form-<?= $row['id'] ?>">
                        <div class="modal-box">
                            <button class="close-btn" onclick="toggleEdit(<?= $row['id'] ?>)">×</button>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="text" name="kategori" value="<?= htmlspecialchars($row['kategori']) ?>" required>
                                <button type="submit" name="edit_kategori" class="btn-simpan">Simpan</button>
                                <a href="?hapus=<?= $row['id'] ?>" class="btn-hapus" onclick="return confirm('Yakin ingin hapus?')">Hapus</a>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- FORM TAMBAH -->
<div class="overlay" id="form-tambah">
    <div class="modal-box">
        <button class="close-btn" onclick="toggleFormTambah()">×</button>
        <form method="POST">
            <input type="text" name="kategori" required placeholder="Kategori baru">
            <button type="submit" name="tambah_kategori" class="btn-simpan">Tambah</button>
        </form>
    </div>
</div>

<script>
    function toggleFormTambah() {
        const form = document.getElementById('form-tambah');
        form.style.display = form.style.display === 'flex' ? 'none' : 'flex';
        document.querySelectorAll('[id^="edit-form-"]').forEach(e => e.style.display = 'none');
    }

    function toggleEdit(id) {
        const form = document.getElementById('edit-form-' + id);
        form.style.display = form.style.display === 'flex' ? 'none' : 'flex';
        document.getElementById('form-tambah').style.display = 'none';

        document.querySelectorAll('[id^="edit-form-"]').forEach(el => {
            if (el.id !== 'edit-form-' + id) el.style.display = 'none';
        });
    }
</script>

</body>
</html>

<?php $conn->close(); ?> 