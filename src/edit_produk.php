<?php
// Koneksi ke database
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'kasir';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fungsi untuk mengambil semua kategori dari database
function getAllCategories($conn) {
    $categories = [];
    $stmt = $conn->prepare("SELECT id, kategori FROM kategori ORDER BY kategori");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
    return $categories;
}

// Fungsi untuk menghitung keuntungan (harga jual - modal)
function hitungKeuntungan($harga_jual, $modal) {
    return $harga_jual - $modal;
}

$categories = getAllCategories($conn);

// Validasi ID Produk
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID Produk tidak valid!');window.location.href='produk.php';</script>";
    exit;
}
$id_produk = (int)$_GET['id'];

// Ambil data produk dari DB
$stmt = $conn->prepare("SELECT * FROM produk WHERE id_produk = ?");
$stmt->bind_param("i", $id_produk);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<script>alert('Produk tidak ditemukan!');window.location.href='produk.php';</script>";
    exit;
}
$produk = $result->fetch_assoc();
$stmt->close();

// Proses update jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = $_POST['nama_produk'];
    $tanggal_exp = $_POST['tanggal_exp'];
    $stok = $_POST['stok'];
    $modal = $_POST['modal'];
    $harga_jual = $_POST['harga_jual'];
    $keuntungan = hitungKeuntungan($harga_jual, $modal);
    $fid_kategori = $_POST['fid_kategori'];
    $deskripsi = $_POST['deskripsi'];
    $barcode = $_POST['barcode'];

    $gambar = $produk['gambar']; // default gambar lama
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK && $_FILES['gambar']['size'] > 0) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $gambarName = time() . '_' . basename($_FILES['gambar']['name']);
        $gambarPath = $uploadDir . $gambarName;
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $gambarPath)) {
            if (!empty($produk['gambar']) && file_exists($produk['gambar'])) {
                unlink($produk['gambar']);
            }
            $gambar = $gambarPath;
        }
    }

    $stmt = $conn->prepare("UPDATE produk SET nama_produk = ?, tanggal_exp = ?, stok = ?, harga_jual = ?, modal = ?, keuntungan = ?, fid_kategori = ?, gambar = ?, deskripsi = ?, barcode = ? WHERE id_produk = ?");
   $stmt->bind_param("ssidddisssi", $nama_produk, $tanggal_exp, $stok, $harga_jual, $modal, $keuntungan, $fid_kategori, $gambar, $deskripsi, $barcode, $id_produk);

    
    if (!$stmt->execute()) {
        echo "<script>alert('Gagal memperbarui produk: " . $stmt->error . "');window.history.back();</script>";
        exit;
    }
    $stmt->close();
    echo "<script>alert('Produk berhasil diperbarui!');window.location.href='produk.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sweetpay - Edit Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background-color: #a05a5a; color: white; text-align: center; padding: 0px 0; position: relative; }
        .header i { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 30px; }
        .back-icon { left: -96%; color: #fff; font-size: 28px; }
        .content { background-color: #f8d5e0; padding: 20px; border-radius: 10px; margin: 20px; position: relative; }
        .content::before { content: ''; background-image: url('https://placehold.co/1000x1000'); background-size: cover; opacity: 0.2; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: -1; }
        .form-container { background-color: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #a05a5a; border-color: #a05a5a; }
        .btn-primary:hover { background-color: #8a4b4b; border-color: #8a4b4b; }
        .current-image { max-width: 200px; max-height: 200px; border-radius: 5px; margin-bottom: 10px; display: block; }
    </style>
    <script>
        function hitungKeuntungan() {
            const modal = parseFloat(document.getElementById('modal_edit').value) || 0;
            const hargaJual = parseFloat(document.getElementById('harga_jual_edit').value) || 0;
            document.getElementById('keuntungan_edit').value = hargaJual - modal;
        }

        document.addEventListener('DOMContentLoaded', function() {
            hitungKeuntungan();

            const inputGambar = document.getElementById('gambar');
            const preview = document.getElementById('preview');

            inputGambar.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</head>
<body>
    <div class="header">
        <h1>Sweetpay</h1>
        <i class="fas fa-user-circle"></i>
        <i class="fas fa-arrow-left back-icon" onclick="window.location.href='produk.php'"></i>
    </div>

    <div class="container content">
        <h2 class="text-center mb-4">Edit Produk</h2>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="form-container">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Nama Produk</label>
                            <input type="text" class="form-control" name="nama_produk" value="<?= htmlspecialchars($produk['nama_produk']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Expired</label>
                            <input type="date" class="form-control" name="tanggal_exp" value="<?= htmlspecialchars($produk['tanggal_exp']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stok</label>
                            <input type="number" class="form-control" name="stok" value="<?= htmlspecialchars($produk['stok']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Modal</label>
                            <input type="number" class="form-control" id="modal_edit" name="modal" value="<?= htmlspecialchars($produk['modal']) ?>" required oninput="hitungKeuntungan()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harga Jual</label>
                            <input type="number" class="form-control" id="harga_jual_edit" name="harga_jual" value="<?= htmlspecialchars($produk['harga_jual']) ?>" required oninput="hitungKeuntungan()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keuntungan</label>
                            <input type="number" class="form-control" id="keuntungan_edit" name="keuntungan" value="<?= htmlspecialchars($produk['keuntungan']) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gambar</label>
                            <?php if (!empty($produk['gambar'])): ?>
                                <p>Gambar saat ini:</p>
                                <img src="http://localhost/kasir/uploads/<?= basename($produk['gambar']) ?>" class="current-image" alt="Current Product Image">
                            <?php endif; ?>
                            <input type="file" class="form-control" name="gambar" id="gambar">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti gambar</small>
                            <img id="preview" style="display:none; max-width:200px; max-height:200px;" alt="Preview">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-control" name="fid_kategori" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($produk['fid_kategori'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" required><?= htmlspecialchars($produk['deskripsi']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" name="barcode" value="<?= htmlspecialchars($produk['barcode']) ?>" readonly>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="produk.php" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
