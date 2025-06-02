<?php
// Koneksi ke database
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'kasir';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Koneksi gagal: " . $conn->connect_error]));
}

// Menerima data dari POST request (daftar ID produk)
$data = json_decode(file_get_contents('php://input'), true);
$result = [];

if (is_array($data) && count($data) > 0) {
    // Siapkan placeholder untuk query IN
    $placeholders = implode(',', array_fill(0, count($data), '?'));
    
    // Buat query untuk mendapatkan stok dan status kadaluwarsa produk
    $query = "SELECT id_produk, stok, tanggal_exp FROM produk WHERE id_produk IN ($placeholders)";
    $stmt = $conn->prepare($query);
    
    // Bind parameter (semua parameter adalah integer)
    $types = str_repeat('i', count($data));
    $stmt->bind_param($types, ...$data);
    
    $stmt->execute();
    $dbResult = $stmt->get_result();
    
    // Tanggal hari ini untuk perbandingan kadaluwarsa
    $today = date('Y-m-d');
    
    while ($row = $dbResult->fetch_assoc()) {
        // Jika produk kadaluwarsa atau stok kosong, jangan masukkan ke hasil
        if ($row['stok'] > 0 && strtotime($row['tanggal_exp']) > strtotime($today)) {
            $result[$row['id_produk']] = $row['stok'];
        }
    }
    
    $stmt->close();
}

// Kembalikan hasil dalam format JSON
header('Content-Type: application/json');
echo json_encode($result);

$conn->close();
?>