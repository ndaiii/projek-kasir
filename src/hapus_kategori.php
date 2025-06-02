<?php
// Koneksi ke database
$host = 'localhost';
$user = 'root'; // Sesuaikan dengan username database Anda
$pass = ''; // Sesuaikan dengan password database Anda
$dbname = 'kasir'; // Sesuaikan dengan nama database Anda

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Cek apakah ada ID kategori yang diberikan
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Hapus kategori dari database
    $sql_delete = "DELETE FROM kategori WHERE id = '$id'";

    if ($conn->query($sql_delete) === TRUE) {
        echo "Kategori berhasil dihapus";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
