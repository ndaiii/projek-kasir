<?php
session_start();
include 'connection.php';

// Jika tidak ada data keranjang, kembali ke halaman transaksi
if (!isset($_POST['cart_data']) || empty($_POST['cart_data'])) {
    echo "<script>alert('Tidak ada item di keranjang!'); window.location.href='transaksi.php';</script>";
    exit;
}

$cartData = json_decode($_POST['cart_data'], true);
$selectedItems = [];

// Convert cart data to selected items format
foreach ($cartData as $id => $item) {
    $selectedItems[] = $id;
}

// Simpan selected items ke localStorage melalui JavaScript
?>
<!DOCTYPE html>
<html>
<head>
    <title>Processing...</title>
</head>
<body>
    <script>
    // Simpan item yang dipilih untuk halaman invoice
    localStorage.setItem('selectedItems', JSON.stringify(<?php echo json_encode($selectedItems); ?>));
    
    // Redirect ke halaman invoice
    window.location.href = 'invoice.php';
    </script>
</body>
</html>