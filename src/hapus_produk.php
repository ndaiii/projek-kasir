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

if (isset($_GET['id'])) {
    $id_produk = (int)$_GET['id'];

    // Ambil stok dan tanggal kadaluarsa
    $query = "SELECT stok, tanggal_exp FROM produk WHERE id_produk = ?";
    $check = $conn->prepare($query);
    $check->bind_param("i", $id_produk);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $stok = $data['stok'];
        $tanggal_exp = $data['tanggal_exp'];
        $today = date('Y-m-d');

        if ($stok <= 0 || strtotime($tanggal_exp) <= strtotime($today)) {
            $stmt = $conn->prepare("DELETE FROM produk WHERE id_produk = ?");
            $stmt->bind_param("i", $id_produk);

            if ($stmt->execute()) {
                setcookie('product_deleted', 'true', time() + 300, '/');
                setcookie('deleted_product_id', $id_produk, time() + 300, '/');
                
                header("Location: produk.php?status=success&message=Produk berhasil dihapus");
                exit;
            } else {
                header("Location: produk.php?status=error&message=" . urlencode("Gagal menghapus produk: " . $conn->error));
                exit;
            }
            $stmt->close();
        } else {
            $message = "Produk tidak bisa dihapus karena masih memiliki stok dan belum kadaluarsa.";
            header("Location: produk.php?status=error&message=" . urlencode($message));
            exit;
        }
    } else {
        header("Location: produk.php?status=error&message=Produk tidak ditemukan");
        exit;
    }
    $check->close();
} else {
    header("Location: produk.php?status=error&message=ID produk tidak ditemukan");
    exit;
}

$conn->close();
?>