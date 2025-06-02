<?php
session_start();
include 'connection.php';

// Pastikan data dikirim via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: transaksi.php");
    exit;
}

$cart_data = isset($_POST['cart_data']) ? json_decode($_POST['cart_data'], true) : null;
$nama_member = trim($_POST['nama_member'] ?? '');
$tanggal_pembelian = date('Y-m-d H:i:s');
$fid_admin = $_SESSION['id_admin'] ?? null;

if (!$cart_data || empty($cart_data)) {
    $_SESSION['message'] = "Keranjang kosong, tidak bisa diproses.";
    $_SESSION['message_type'] = "danger";
    header("Location: transaksi.php");
    exit;
}

// Cek apakah member ada
$fid_member = null;
if ($nama_member !== '') {
    $stmt = $conn->prepare("SELECT id_member FROM member WHERE nama_member = ?");
    $stmt->bind_param("s", $nama_member);
    $stmt->execute();
    $stmt->bind_result($fid_member);
    if (!$stmt->fetch()) {
        $fid_member = null;
    }
    $stmt->close();
}

// Hitung total harga dan diskon (jika ada)
$total = 0;
$diskon = 0;
$total_keuntungan = 0;

foreach ($cart_data as $item) {
    $qty = (int) $item['qty'];
    $harga = (float) $item['harga'];
    $total += $qty * $harga;

    // Cek harga_beli untuk menghitung keuntungan (jika diperlukan)
    $stmt = $conn->prepare("SELECT harga_beli FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $stmt->bind_result($harga_beli);
    if ($stmt->fetch()) {
        $total_keuntungan += ($harga - $harga_beli) * $qty;
    }
    $stmt->close();
}

// Insert ke tabel transaksi
$stmt = $conn->prepare("INSERT INTO transaksi (fid_member, tanggal_pembelian, total_harga, diskon, total_keuntungan, fid_admin) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isdddi", $fid_member, $tanggal_pembelian, $total, $diskon, $total_keuntungan, $fid_admin);

if ($stmt->execute()) {
    $id_transaksi = $stmt->insert_id;
    $stmt->close();

    // Insert ke detail_transaksi dan update stok
    foreach ($cart_data as $item) {
        $id_produk = (int) $item['id'];
        $qty = (int) $item['qty'];
        $harga = (float) $item['harga'];

        // Insert detail
        $stmt_detail = $conn->prepare("INSERT INTO detail_transaksi (fid_transaksi, fid_produk, jumlah, harga_saat_transaksi) VALUES (?, ?, ?, ?)");
        $stmt_detail->bind_param("iiid", $id_transaksi, $id_produk, $qty, $harga);
        $stmt_detail->execute();
        $stmt_detail->close();

        // Update stok
        $stmt_stok = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?");
        $stmt_stok->bind_param("ii", $qty, $id_produk);
        $stmt_stok->execute();
        $stmt_stok->close();
    }

    $_SESSION['message'] = "Transaksi berhasil disimpan.";
    $_SESSION['message_type'] = "success";
    header("Location: invoice.php?id=" . $id_transaksi);
    exit;
} else {
    $_SESSION['message'] = "Gagal menyimpan transaksi.";
    $_SESSION['message_type'] = "danger";
    header("Location: transaksi.php");
    exit;
}

$conn->close();
?>
