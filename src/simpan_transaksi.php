<?php
session_start();
include 'koneksi.php';  // pastikan ini file koneksi DB kamu

if (!isset($_POST['konfirmasi_pembayaran'])) {
    // Kalau langsung akses halaman tanpa submit form, redirect ke halaman utama
    header("Location: index.php");
    exit;
}

$selectedItemsJson = $_POST['selectedItems'] ?? '';
$selectedItems = json_decode($selectedItemsJson, true);
$uangBayar = $_POST['uang_bayar'] ?? 0;
$fid_admin = $_SESSION['admin']['id'] ?? null;
$fid_member = $_POST['fid_member'] ?? null;  // kalau ada member
$diskon = $_POST['diskon'] ?? 0;

if (!$selectedItems || empty($selectedItems)) {
    echo "<script>alert('Keranjang kosong, silakan pilih produk terlebih dahulu.'); window.location.href='keranjang.php';</script>";
    exit;
}

if (!$fid_admin) {
    echo "<script>alert('Anda harus login sebagai admin terlebih dahulu.'); window.location.href='login.php';</script>";
    exit;
}

try {
    $conn->begin_transaction();

    // Buat invoice unik
    $invoiceNumber = "INV" . date("YmdHis") . rand(1000, 9999);

    // Hitung total harga dan total keuntungan
    $totalHarga = 0;
    $totalKeuntungan = 0;

    foreach ($selectedItems as $item) {
        $totalHarga += $item['harga_jual'] * $item['jumlah'];
        $totalKeuntungan += ($item['harga_jual'] - $item['harga_beli']) * $item['jumlah'];
    }

    // Hitung harga setelah diskon
    $hargaSetelahDiskon = $totalHarga - $diskon;
    if ($hargaSetelahDiskon < 0) $hargaSetelahDiskon = 0;

    // Hitung kembalian
    $kembalian = $uangBayar - $hargaSetelahDiskon;
    if ($kembalian < 0) {
        echo "<script>alert('Uang bayar kurang dari total harga setelah diskon.'); window.history.back();</script>";
        exit;
    }

    // Simpan transaksi ke DB
    $stmt = $conn->prepare("INSERT INTO transaksi (invoice, tanggal_pembelian, total_harga, fid_admin, fid_member, total_keuntungan, diskon) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
    $stmt->bind_param("siiiid", $invoiceNumber, $hargaSetelahDiskon, $fid_admin, $fid_member, $totalKeuntungan, $diskon);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {
        throw new Exception("Gagal menyimpan transaksi.");
    }

    // Ambil id transaksi terakhir
    $idTransaksi = $conn->insert_id;

    // Simpan detail transaksi (jika ada tabel detail)
    foreach ($selectedItems as $item) {
        $stmtDetail = $conn->prepare("INSERT INTO detail_transaksi (fid_transaksi, fid_produk, jumlah, harga_jual) VALUES (?, ?, ?, ?)");
        $stmtDetail->bind_param("iiii", $idTransaksi, $item['id_produk'], $item['jumlah'], $item['harga_jual']);
        $stmtDetail->execute();

        // Update stok produk
        $stmtStok = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?");
        $stmtStok->bind_param("ii", $item['jumlah'], $item['id_produk']);
        $stmtStok->execute();
    }

    // Jika ada member, tambah poin member 1 poin
    if ($fid_member) {
        $stmtPoin = $conn->prepare("UPDATE member SET point = point + 1 WHERE id_member = ?");
        $stmtPoin->bind_param("i", $fid_member);
        $stmtPoin->execute();
    }

    $conn->commit();

    // Setelah transaksi sukses, redirect ke struk.php dengan invoice sebagai parameter
    header("Location: struk.php?invoice=" . $invoiceNumber);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Terjadi kesalahan: " . $e->getMessage() . "'); window.history.back();</script>";
    exit;
}
