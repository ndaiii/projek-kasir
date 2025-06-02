<?php
session_start();
require 'connection.php'; // File koneksi ke database

if (isset($_SESSION['id_admin'])) {
    $id_admin = $_SESSION['id_admin'];

    // Update status admin jadi 'tidak aktif'
    $sql = "UPDATE admin SET status = 'tidak aktif' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_admin);
    $stmt->execute();
    $stmt->close();
}

// Hapus semua session dan destroy
session_unset();
session_destroy();

// Redirect ke login
header("Location: login.php");
exit();
?>
