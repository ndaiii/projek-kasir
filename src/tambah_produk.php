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

// Fungsi untuk menghasilkan barcode otomatis
function generateBarcode() {
    // Format: tahun(2) + bulan(2) + tanggal(2) + jam(2) + menit(2) + detik(2) + random(4)
    $date = new DateTime();
    $prefix = $date->format('ymdHis');
    $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $random;
}

// Fungsi untuk memeriksa apakah barcode sudah ada
function barcode_exists($conn, $barcode) {
    $sql = "SELECT id_produk FROM produk WHERE barcode = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Fungsi untuk memeriksa apakah nama produk sudah ada
function product_name_exists($conn, $nama_produk) {
    $sql = "SELECT id_produk FROM produk WHERE nama_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nama_produk);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Fungsi untuk menghitung keuntungan (harga jual - modal)
function hitungKeuntungan($harga_jual, $modal) {
    return $harga_jual - $modal;
}

// Ambil semua kategori untuk digunakan di form
$categories = getAllCategories($conn);

// Generate barcode saat halaman dimuat
$generatedBarcode = generateBarcode();

// Pesan error
$error_message = '';

// Proses tambah produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nama_produk'])) {
    $nama_produk = $_POST['nama_produk'];
    $tanggal_exp = $_POST['tanggal_exp'];
    $stok = $_POST['stok'];
    $modal = $_POST['modal']; // Ambil nilai modal dari form
    $harga_jual = $_POST['harga_jual'];
    $keuntungan = hitungKeuntungan($harga_jual, $modal); // Hitung keuntungan otomatis
    $fid_kategori = (int)$_POST['fid_kategori']; // Pastikan ini adalah integer
    $deskripsi = $_POST['deskripsi'];
    
    // Gunakan barcode dari form
    $barcode = !empty($_POST['barcode']) ? $_POST['barcode'] : $generatedBarcode;

    // Validasi barcode
    if (barcode_exists($conn, $barcode)) {
        $error_message = "Barcode sudah terdaftar!";
    }
    // Validasi nama produk
    else if (product_name_exists($conn, $nama_produk)) {
        $error_message = "Produk dengan nama yang sama sudah ada!";
    }
    else {
        $gambar = '';
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $gambarName = time() . '_' . basename($_FILES['gambar']['name']);
            $gambarPath = $uploadDir . $gambarName;
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $gambarPath)) {
                $gambar = $gambarPath;
            }
        }

        $stmt = $conn->prepare("INSERT INTO produk (nama_produk, tanggal_exp, stok, harga_jual, modal, keuntungan, fid_kategori, gambar, deskripsi, barcode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssiiddisss", $nama_produk, $tanggal_exp, $stok, $harga_jual, $modal, $keuntungan, $fid_kategori, $gambar, $deskripsi, $barcode);
        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }
        $stmt->close();

        header("Location: produk.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sweetpay - Tambah Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- JsBarcode untuk generate barcode -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { background-color: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background-color: #a05a5a; color: white; text-align: center; padding: 0px 0; position: relative; }
        .header i { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 30px; }
        .back-icon { left: -96%; color: #fff;  font-size: 28px; }
        .content { background-color: #f8d5e0; padding: 20px; border-radius: 10px; margin: 20px; position: relative; }
        .content::before { content: ''; background-image: url('https://placehold.co/1000x1000'); background-size: cover; opacity: 0.2; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: -1; }
        .form-container { background-color: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #a05a5a; border-color: #a05a5a; }
        .btn-primary:hover { background-color: #8a4b4b; border-color: #8a4b4b; }
        .back-btn { margin-bottom: 20px; }
        #barcode-container { margin: 15px 0; text-align: center; }
        #barcode-container svg { max-width: 100%; height: auto; }
        .barcode-label { margin-top: 10px; font-weight: bold; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
    <script>
        // Script untuk menghitung keuntungan otomatis
        function hitungKeuntungan(modalId, hargaJualId, keuntunganId) {
            const modal = parseFloat(document.getElementById(modalId).value) || 0;
            const hargaJual = parseFloat(document.getElementById(hargaJualId).value) || 0;
            const keuntungan = hargaJual - modal;
            document.getElementById(keuntunganId).value = keuntungan;
        }
        
        // Fungsi untuk menghasilkan barcode otomatis ketika form dibuka
        function generateBarcode() {
            const now = new Date();
            const year = now.getFullYear().toString().substr(-2);
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hour = String(now.getHours()).padStart(2, '0');
            const minute = String(now.getMinutes()).padStart(2, '0');
            const second = String(now.getSeconds()).padStart(2, '0');
            const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            
            return year + month + day + hour + minute + second + random;
        }
        
        // Fungsi untuk merenderkan barcode
        function renderBarcode(barcodeValue) {
            if (!barcodeValue) return;
            
            // Hapus barcode lama jika ada
            const container = document.getElementById('barcode-container');
            container.innerHTML = '';
            
            // Buat elemen SVG baru untuk barcode
            const svgElement = document.createElement('svg');
            svgElement.id = 'barcode-svg';
            container.appendChild(svgElement);
            
            // Generate barcode menggunakan JsBarcode
            try {
                JsBarcode("#barcode-svg", barcodeValue, {
                    format: "CODE128",
                    width: 2,
                    height: 100,
                    displayValue: true,
                    fontSize: 16,
                    margin: 10
                });
                
                // Tambahkan label di bawah barcode
                const label = document.createElement('div');
                label.className = 'barcode-label';
                label.textContent = 'Barcode produk ini siap untuk di-scan';
                container.appendChild(label);
            } catch (e) {
                console.error("Error generating barcode:", e);
                container.innerHTML = '<div class="alert alert-danger">Gagal membuat barcode. Pastikan kode barcode valid.</div>';
            }
        }
        
        // Inisialisasi dan update barcode ketika dokumen siap atau nilai berubah
        document.addEventListener('DOMContentLoaded', function() {
            const barcodeInput = document.getElementById('barcode');
            
            // Jika input kosong, isi dengan barcode yang digenerate
            if (barcodeInput && !barcodeInput.value) {
                const generatedBarcode = '<?php echo $generatedBarcode; ?>';
                barcodeInput.value = generatedBarcode;
                renderBarcode(generatedBarcode);
            } else if (barcodeInput && barcodeInput.value) {
                renderBarcode(barcodeInput.value);
            }
            
            // Tambahkan event listener untuk perubahan nilai barcode
            barcodeInput.addEventListener('input', function() {
                renderBarcode(this.value);
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
        <h2 class="text-center mb-4">Tambah Produk</h2>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="form-container">
                    <?php if(!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="nama_produk" class="form-label">Nama Produk</label>
                            <input type="text" class="form-control" id="nama_produk" name="nama_produk" required value="<?php echo isset($_POST['nama_produk']) ? htmlspecialchars($_POST['nama_produk']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_exp" class="form-label">Tanggal Expired</label>
                            <input type="date" class="form-control" id="tanggal_exp" name="tanggal_exp" required value="<?php echo isset($_POST['tanggal_exp']) ? $_POST['tanggal_exp'] : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="stok" class="form-label">Stok</label>
                            <input type="number" class="form-control" id="stok" name="stok" required value="<?php echo isset($_POST['stok']) ? $_POST['stok'] : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="modal" class="form-label">Modal</label>
                            <input type="number" class="form-control" id="modal_add" name="modal" required oninput="hitungKeuntungan('modal_add', 'harga_jual_add', 'keuntungan_add')" value="<?php echo isset($_POST['modal']) ? $_POST['modal'] : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="harga_jual" class="form-label">Harga Jual</label>
                            <input type="number" class="form-control" id="harga_jual_add" name="harga_jual" required oninput="hitungKeuntungan('modal_add', 'harga_jual_add', 'keuntungan_add')" value="<?php echo isset($_POST['harga_jual']) ? $_POST['harga_jual'] : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="keuntungan" class="form-label">Keuntungan</label>
                            <input type="number" class="form-control" id="keuntungan_add" name="keuntungan" readonly value="<?php echo isset($_POST['harga_jual'], $_POST['modal']) ? ($_POST['harga_jual'] - $_POST['modal']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="gambar" class="form-label">Gambar</label>
                            <input type="file" class="form-control" id="gambar" name="gambar" required>
                        </div>
                        <div class="mb-3">
                            <label for="fid_kategori" class="form-label">Kategori</label>
                            <select class="form-control" id="fid_kategori" name="fid_kategori" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo isset($_POST['fid_kategori']) && $_POST['fid_kategori'] == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['kategori']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required><?php echo isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : ''; ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="barcode" class="form-label">Barcode Produk</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo $generatedBarcode; ?>" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="regenerateBarcode()">
                                    <i class="fas fa-sync-alt"></i> Generate Baru
                                </button>
                            </div>
                            <small class="form-text text-muted">Barcode otomatis terisi dan siap digunakan</small>
                        </div>
                        
                        <!-- Container untuk menampilkan barcode batang -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Barcode Produk</h5>
                            </div>
                            <div class="card-body">
                                <div id="barcode-container"></div>
                                <p class="text-center text-muted mt-2">
                                    <i class="fas fa-info-circle"></i> 
                                    Barcode ini dapat digunakan untuk scan produk saat di kasir
                                </p>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="produk.php" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fungsi untuk regenerasi barcode jika tombol refresh diklik
        function regenerateBarcode() {
            const newBarcode = generateBarcode();
            document.getElementById('barcode').value = newBarcode;
            renderBarcode(newBarcode);
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>