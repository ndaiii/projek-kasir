<?php
session_start();
include 'connection.php';

// Cek invoice
$invoice = $_GET['invoice'] ?? $_SESSION['invoice'] ?? null;
if (!$invoice) {
    die("<script>alert('Invoice tidak ditemukan!'); window.location.href = 'transaksi.php';</script>");
}

// Ambil data transaksi
$sql = "SELECT t.*, m.nama_member, m.no_tlp FROM transaksi t JOIN member m ON t.fid_member = m.id_member WHERE t.invoice = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $invoice);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();

if (!$transaksi) {
    die("<script>alert('Transaksi tidak ditemukan!'); window.location.href = 'transaksi.php';</script>");
}

// Ambil produk dari session
$produk = json_decode($_SESSION['cart_data'] ?? '[]', true);

// Format data
$tanggal = date('d-m-Y H:i:s', strtotime($transaksi['tanggal_pembelian']));
$formatRp = fn($n) => 'Rp. ' . number_format($n, 0, ',', '.');

// Fungsi kirim WhatsApp - DIPERBAIKI
function kirimWA($no_tlp, $pesan) {
    // Format nomor
    $no = preg_replace('/[^0-9]/', '', $no_tlp);
    if (substr($no, 0, 1) == '0') {
        $no = '62' . substr($no, 1);
    } elseif (substr($no, 0, 2) != '62') {
        $no = '62' . $no;
    }
    
    // Validasi
    if (strlen($no) < 10 || strlen($no) > 15) {
        $_SESSION['wa_error'] = "Nomor tidak valid: $no_tlp → $no";
        return false;
    }
    
    // Data untuk Fonnte
    $token = 'gPFzVshwRq2LUkcHKpZd';
    $data = array(
        'target' => $no,
        'message' => $pesan,
        'countryCode' => '62'  
    );
    
    // CURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.fonnte.com/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: ' . $token,
        'Content-Type: application/x-www-form-urlencoded'
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Simpan debug info
    $_SESSION['wa_debug'] = array(
        'nomor' => $no,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'timestamp' => date('H:i:s')
    );
    
    // Cek hasil
    if ($error) {
        $_SESSION['wa_error'] = "CURL Error: $error";
        return false;
    }
    
    if ($httpCode != 200) {
        $_SESSION['wa_error'] = "HTTP Error: $httpCode";
        return false;
    }
    
    // Parse response
    $result = json_decode($response, true);
    if (!$result) {
        $_SESSION['wa_error'] = "Invalid JSON response";
        return false;
    }
    
    // Cek berbagai format response Fonnte
    if (isset($result['status']) && $result['status'] == true) return true;
    if (isset($result['detail']) && stripos($result['detail'], 'sent') !== false) return true;
    if (isset($result['message']) && stripos($result['message'], 'success') !== false) return true;
    
    // Jika sampai sini, gagal
    $_SESSION['wa_error'] = "API Response: " . ($result['reason'] ?? 'Unknown error');
    return false;
}

// Buat pesan WhatsApp
function buatPesanWA($transaksi, $produk, $formatRp, $tanggal) {
    $pesan = "🧾 *STRUK PEMBELIAN*\n";
    $pesan .= "━━━━━━━━━━━━━━━━━━━━\n";
    $pesan .= "🏪 *SweetPay Store*\n";
    $pesan .= "📅 {$tanggal}\n";
    $pesan .= "🧾 Invoice: {$transaksi['invoice']}\n";
    $pesan .= "👤 Member: {$transaksi['nama_member']}\n";
    $pesan .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $pesan .= "📋 *DETAIL PEMBELIAN:*\n";

    foreach ($produk as $item) {
        $total = $item['qty'] * $item['harga'];
        $pesan .= "• {$item['nama']}\n";
        $pesan .= "  {$item['qty']} x {$formatRp($item['harga'])} = {$formatRp($total)}\n\n";
    }

    $pesan .= "━━━━━━━━━━━━━━━━━━━━\n";
    $pesan .= "💰 Subtotal: {$formatRp($transaksi['total_harga'])}\n";
    $pesan .= "🎁 Diskon: {$formatRp($transaksi['diskon'])}\n";
    $pesan .= "💳 *Total: {$formatRp($transaksi['total_bayar'])}*\n";
    $pesan .= "💵 Bayar: {$formatRp($transaksi['uang_bayar'])}\n";
    $pesan .= "💰 Kembali: {$formatRp($transaksi['kembalian'])}\n";
    $pesan .= "━━━━━━━━━━━━━━━━━━━━\n";
    $pesan .= "🎉 Terima kasih!\n";
    $pesan .= "📱 *SweetPay Store*";
    
    return $pesan;
}

$pesan_wa = buatPesanWA($transaksi, $produk, $formatRp, $tanggal);

// Proses kirim WhatsApp
$wa_status = '';
if (isset($_POST['kirim_wa'])) {
    if (kirimWA($transaksi['no_tlp'], $pesan_wa)) {
        $wa_status = 'success';
    } else {
        $wa_status = 'error';
    }
}

// Kirim otomatis saat pertama load
if (!isset($_SESSION['wa_sent_' . $invoice])) {
    if (kirimWA($transaksi['no_tlp'], $pesan_wa)) {
        $wa_status = 'auto_success';
    }
    $_SESSION['wa_sent_' . $invoice] = true;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - SweetPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background: #a05a5a; color: white; padding: 15px; text-align: center; }
        .struk { background: white; margin: 20px auto; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; }
        .store { text-align: center; border-bottom: 2px dashed #333; padding-bottom: 15px; margin-bottom: 20px; }
        .store-name { font-size: 24px; font-weight: bold; color: #a05a5a; }
        .info { font-size: 12px; color: #666; margin-bottom: 20px; }
        .item { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .detail { font-size: 12px; color: #666; margin-left: 10px; margin-bottom: 8px; }
        .divider { border-top: 1px dashed #333; margin: 15px 0; }
        .total { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .grand { font-weight: bold; font-size: 16px; border-top: 1px solid #333; padding-top: 8px; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; border-top: 2px dashed #333; padding-top: 15px; }
        .btn-custom { background: #a05a5a; color: white; border: none; padding: 12px 25px; border-radius: 8px; margin: 10px 5px; }
        .btn-custom:hover { background: #8b4a4a; color: white; }
        .btn-whatsapp { background: #25D366; color: white; border: none; padding: 12px 25px; border-radius: 8px; margin: 10px 5px; }
        .btn-whatsapp:hover { background: #128C7E; color: white; }
        .actions { text-align: center; margin-top: 25px; }
        @media print { body { background: white; } .header, .actions { display: none; } .struk { box-shadow: none; margin: 0; } }
    </style>
</head>
<body>

<div class="header">
    <span onclick="window.location.href='index.php'" style="cursor:pointer;">← Struk Pembelian</span>
</div>

<div class="struk">
    <div class="store">
        <div class="store-name">🍭 SweetPay </div>
        <div style="font-size: 12px; color: #666;">Jakarta, SMKN 71</div>
    </div>

    <div class="info">
        <div><strong>Invoice:</strong> <?= $transaksi['invoice'] ?></div>
        <div><strong>Tanggal:</strong> <?= $tanggal ?></div>
        <div><strong>Member:</strong> <?= $transaksi['nama_member'] ?></div>
        <div><strong>No. HP:</strong> <?= $transaksi['no_tlp'] ?></div>
    </div>

    <div class="divider"></div>

    <?php foreach ($produk as $item): ?>
    <div class="item"><span><?= $item['nama'] ?></span></div>
    <div class="detail"><?= $item['qty'] ?> x <?= $formatRp($item['harga']) ?> = <?= $formatRp($item['qty'] * $item['harga']) ?></div>
    <?php endforeach; ?>

    <div class="divider"></div>

    <div class="total"><span>Subtotal:</span><span><?= $formatRp($transaksi['total_harga']) ?></span></div>
    <?php if ($transaksi['diskon'] > 0): ?>
    <div class="total"><span>Diskon:</span><span>-<?= $formatRp($transaksi['diskon']) ?></span></div>
    <?php endif; ?>
    <div class="total grand"><span><strong>Total:</strong></span><span><strong><?= $formatRp($transaksi['total_bayar']) ?></strong></span></div>
    <div class="total"><span>Bayar:</span><span><?= $formatRp($transaksi['uang_bayar']) ?></span></div>
    <div class="total"><span>Kembali:</span><span><?= $formatRp($transaksi['kembalian']) ?></span></div>

    <div class="footer">
        <div>🎉 Terima kasih atas kunjungan Anda! 🎉</div>
        <div>Barang yang sudah dibeli tidak dapat dikembalikan</div>
        <div style="margin-top: 10px;"><strong>SweetPay Store</strong><br>📱 WhatsApp: 088888888</div>
    </div>

    <div class="actions">
        <button class="btn btn-custom" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
        
        <form method="POST" style="display: inline;">
            <button type="submit" name="kirim_wa" class="btn btn-whatsapp">
                <i class="fab fa-whatsapp"></i> Kirim WhatsApp
            </button>
        </form>
        
        <button class="btn btn-custom" onclick="window.location.href='transaksi.php'">
            <i class="fas fa-home"></i> Beranda
        </button>

        <!-- Status WhatsApp -->
        <?php if ($wa_status == 'success'): ?>
        <div class="alert alert-success mt-3">
            <i class="fas fa-check-circle"></i> Struk berhasil dikirim ke WhatsApp!
        </div>
        <?php elseif ($wa_status == 'auto_success'): ?>
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> Struk otomatis dikirim ke WhatsApp.
        </div>
        <?php elseif ($wa_status == 'error'): ?>
        <div class="alert alert-danger mt-3">
            <i class="fas fa-exclamation-triangle"></i> Gagal kirim WhatsApp: <?= $_SESSION['wa_error'] ?? 'Unknown error' ?>
            <?php if (isset($_SESSION['wa_debug'])): ?>
            <br><small>Debug: <?= $_SESSION['wa_debug']['nomor'] ?> | HTTP: <?= $_SESSION['wa_debug']['http_code'] ?> | <?= $_SESSION['wa_debug']['timestamp'] ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert:not(.alert-info)').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Clear session
<?php 
unset($_SESSION['cart_data'], $_SESSION['produk_terakhir'], $_SESSION['wa_error']); 
?>
</script>

</body>
</html>