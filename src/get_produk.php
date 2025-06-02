<?php
// Koneksi ke database
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'kasir';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Koneksi gagal: " . $conn->connect_error]));
}

if (isset($_GET['kode'])) {
    $kode = $_GET['kode'];
    
    // Query untuk mencari produk berdasarkan kode barcode
    // Mengubah kode_barcode menjadi barcode sesuai kolom di database
    $query = "SELECT id_produk as id, nama_produk as nama, harga_jual as harga, stok, tanggal_exp 
              FROM produk 
              WHERE barcode = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $kode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $produk = $result->fetch_assoc();
        
        // Cek apakah produk kadaluwarsa
        $today = date('Y-m-d');
        if (strtotime($produk['tanggal_exp']) <= strtotime($today)) {
            echo json_encode([
                "status" => "error",
                "message" => "Produk sudah kadaluwarsa"
            ]);
            exit;
        }
        
        // Cek apakah stok tersedia
        if ($produk['stok'] <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Stok produk tidak tersedia"
            ]);
            exit;
        }
        
        echo json_encode([
            "status" => "success",
            "produk" => $produk
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Produk tidak ditemukan"
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Parameter kode tidak ditemukan"
    ]);
}

$conn->close();
?>