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

$categories = getAllCategories($conn);
$sql = "SELECT p.id_produk, p.nama_produk, p.tanggal_exp, p.stok, p.modal, p.harga_jual, 
        p.keuntungan, p.fid_kategori, p.gambar, p.deskripsi, p.barcode, k.kategori 
        FROM produk p 
        LEFT JOIN kategori k ON p.fid_kategori = k.id";
$result = $conn->query($sql);
?>
<?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
    <script>
        alert("<?= htmlspecialchars($_GET['message']) ?>");
    </script>
<?php endif; ?>


<!DOCTYPE html>
<html>
<head>
    <title>Sweetpay</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { background-color: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background-color: #a05a5a; color: white; text-align: center; padding: 0px 0; position: relative; }
        .header i { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 30px; }
        .back-icon { left: -96%; color: #fff;  font-size: 28px; }
        .content { background-color: #f8d5e0; padding: 20px; border-radius: 10px; margin: 20px; position: relative; }
        .content::before { content: ''; background-image: url('https://placehold.co/1000x1000'); background-size: cover; opacity: 0.2; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: -1; }
        .search-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .product-card { background-color: #f8d5e0; border: none; text-align: center; }
        .product-card img { width: 100%; border-radius: 10px; }
        .product-card .card-body { padding: 10px; }
        .product-card .btn { background-color: #a68b8b; color: white; border: none; }
        .add-product { font-size: 24px; color: #a68b8b; cursor: pointer; }
        .barcode-container { text-align: center; margin: 15px 0; padding: 10px; background-color: #f8f9fa; border-radius: 5px; }
        .barcode-container svg { max-width: 100%; height: auto; }
        .barcode-text { margin-top: 5px; font-size: 12px; color: #666; }
        .modal-body hr { margin: 15px 0; }
        .detail-section { margin-bottom: 15px; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    document.querySelectorAll('.product-card').forEach(function(card) {
                        const productName = card.querySelector('.card-body p').textContent.toLowerCase();
                        const productContainer = card.closest('.col-md-4');
                        if (productName.includes(searchTerm)) {
                            productContainer.style.display = '';
                        } else {
                            productContainer.style.display = 'none';
                        }
                    });
                });
            }
            generateAllBarcodes();
        });
        function generateAllBarcodes() {
            const barcodeElements = document.querySelectorAll('.barcode-svg');
            
            barcodeElements.forEach(function(element) {
                const barcodeValue = element.getAttribute('data-barcode');
                if (barcodeValue) {
                    try {
                        JsBarcode(element, barcodeValue, {
                            format: "CODE128",
                            width: 2,
                            height: 70,
                            displayValue: true,
                            fontSize: 14,
                            margin: 10
                        });
                    } catch (e) {
                        console.error("Error generating barcode:", e);
                        const container = element.closest('.barcode-container');
                        if (container) {
                            container.innerHTML = '<div class="alert alert-danger">Barcode tidak valid</div>';
                        }
                    }
                }
            });
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>Sweetpay</h1>
        <i class="fas fa-user-circle"></i>
        <i class="fas fa-arrow-left back-icon" onclick="window.location.href='dashboard.php'"></i>
    </div>

    <div class="container content">
        <h2 class="text-center">Produk</h2>
        <div class="search-bar">
            <div>
                <label for="search">Cari:</label>
                <input type="text" id="search" class="form-control">
            </div>
            <i class="fas fa-plus-circle add-product" onclick="window.location.href='tambah_produk.php'"></i>
        </div>

        <div class="row">
            <?php while ($row = $result->fetch_assoc()) { ?>
                <div class="col-md-4">
                    <div class="card product-card">
                        <img src="<?php echo $row['gambar']; ?>" alt="<?php echo $row['nama_produk']; ?>" height="200">
                        <div class="card-body">
                            <p>
                                Nama produk: <?php echo $row['nama_produk']; ?><br>
                                Stok: <?php echo $row['stok']; ?><br>
                                Harga: Rp. <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?>
                            </p>
                            <button class="btn" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $row['id_produk']; ?>">Detail</button>
                        </div>
                    </div>
                </div>

                <!-- Modal Detail -->
                <div class="modal fade" id="detailModal<?php echo $row['id_produk']; ?>" tabindex="-1" aria-labelledby="detailModalLabel<?php echo $row['id_produk']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="detailModalLabel<?php echo $row['id_produk']; ?>">Detail Produk</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                <img src="<?php echo $row['gambar']; ?>" alt="Gambar Produk" class="img-fluid rounded mb-3">
                                
                                <!-- Tambahkan barcode di sini -->
                                <div class="barcode-container">
                                    <h6><i class="fas fa-barcode"></i> Barcode Produk</h6>
                                    <svg class="barcode-svg" data-barcode="<?php echo htmlspecialchars($row['barcode'] ?? 'NOBRC'); ?>"></svg>
                                    <div class="barcode-text">
                                        Barcode dapat dipindai untuk menambahkan produk ke keranjang
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="detail-section">
                                    <p><strong>Nama produk:</strong> <?php echo $row['nama_produk']; ?></p>
                                    <p><strong>Exp:</strong> <?php echo $row['tanggal_exp']; ?></p>
                                    <p><strong>Stok:</strong> <?php echo $row['stok']; ?></p>
                                    <p><strong>Modal:</strong> Rp. <?php echo number_format($row['modal'], 0, ',', '.'); ?></p>
                                    <p><strong>Harga Jual:</strong> Rp. <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></p>
                                    <p><strong>Keuntungan:</strong> Rp. <?php echo number_format($row['keuntungan'], 0, ',', '.'); ?></p>
                                    <p><strong>Kategori:</strong> <?php echo $row['kategori']; ?></p>
                                    <p><strong>Deskripsi:</strong> <?php echo $row['deskripsi']; ?></p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="edit_produk.php?id=<?php echo $row['id_produk']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="hapus_produk.php?id=<?php echo $row['id_produk']; ?>" class="btn btn-danger" onclick="return confirm('Hapus produk ini?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?> 