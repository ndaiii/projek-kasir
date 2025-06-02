<?php
session_start();
include 'connection.php';

$invoiceNumber = 'INV-' . date('YmdHis');
error_log("Payment processing started. Invoice: $invoiceNumber");

function reduceStock($conn, $id_produk, $quantity) {
    $sql = "UPDATE produk SET stok = stok - ? WHERE id_produk = ? AND stok >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $quantity, $id_produk, $quantity);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

if (isset($_POST['konfirmasi_pembayaran'])) {
    error_log("Payment confirmation received");
    error_log("POST data: " . print_r($_POST, true));
    
    if (!isset($_POST['cart_data']) || empty($_POST['cart_data'])) {
        echo "<script>alert('Data keranjang tidak ditemukan!'); window.location.href = 'keranjang.php';</script>";
        exit;
    }
    
    $cartData = json_decode($_POST['cart_data'], true);
    $no_tlp = isset($_POST['no_tlp']) ? $_POST['no_tlp'] : null;
    $total_harga = isset($_POST['total_harga']) ? intval($_POST['total_harga']) : 0;
    $jumlahUang = isset($_POST['uang_dibayar']) ? intval($_POST['uang_dibayar']) : 0;
    $totalBayarAkhir = isset($_POST['total_bayar_akhir']) ? intval($_POST['total_bayar_akhir']) : 0;
    $pointsUsed = isset($_POST['points_used']) ? intval($_POST['points_used']) : 0;
    $total_keuntungan = isset($_POST['total_keuntungan']) ? intval($_POST['total_keuntungan']) : 0;

    error_log("No Telepon: " . $no_tlp . ", Total Harga: " . $total_harga . ", Jumlah Uang: " . $jumlahUang . ", Total Bayar Akhir: " . $totalBayarAkhir . ", Points Used: " . $pointsUsed . ", Total Keuntungan: " . $total_keuntungan);

    if (!$no_tlp) {
        echo "<script>alert('No Telepon Member tidak ditemukan!'); window.location.href = 'keranjang.php';</script>";
        exit;
    }

    if ($jumlahUang < $totalBayarAkhir) {
        echo "<script>alert('Nominal uang tidak mencukupi!'); window.location.href = 'keranjang.php';</script>";
        exit;
    }

    $cek = $conn->prepare("SELECT id_member, nama_member, point FROM member WHERE no_tlp = ?");
    $cek->bind_param('s', $no_tlp);
    $cek->execute();
    $result = $cek->get_result();

    if ($result->num_rows == 0) {
        echo "<script>alert('Member tidak ditemukan!'); window.location.href = 'keranjang.php';</script>";
        exit;
    }

    $member_data = $result->fetch_assoc();
    $id_member = $member_data['id_member'];
    $nama_member = $member_data['nama_member'];
    $point_member = $member_data['point'];
    $cek->close();
    
    if ($pointsUsed > $point_member) {
        echo "<script>alert('Point yang digunakan melebihi point yang dimiliki!'); window.location.href = 'keranjang.php';</script>";
        exit;
    }

    $diskon = $pointsUsed * 100;
    $totalBayar = $total_harga - $diskon;
    $kembalian = $jumlahUang - $totalBayar;

    // Hitung point baru berdasarkan transaksi
    $pointFromTransaction = 1; // 1 point per transaksi
    
    error_log("Processing transaction: Member ID: $id_member, Points Used: $pointsUsed, Discount: $diskon, Total Payment: $totalBayar, Points from transaction: $pointFromTransaction");

    $conn->begin_transaction();
    try {
        $success = true;

        foreach ($cartData as $item) {
            $itemId = isset($item['id_produk']) ? $item['id_produk'] : (isset($item['id']) ? $item['id'] : null);
            if (!$itemId) {
                error_log("Missing product ID in cart item: " . print_r($item, true));
                $success = false;
                break;
            }
            
            error_log("Checking stock for item ID: " . $itemId . ", Qty: " . $item['qty']);
            if (!reduceStock($conn, $itemId, $item['qty'])) {
                error_log("Insufficient stock for product ID: " . $itemId);
                $success = false;
                break;
            }
        }

        if ($success) {
            // PERBAIKAN LOGIKA POINT:
            // Point baru = Point lama - Point yang digunakan + Point dari transaksi baru
            $newPoints = $point_member - $pointsUsed + $pointFromTransaction;
            
            // Pastikan point tidak negatif
            if ($newPoints < 0) {
                $newPoints = 0;
            }
            
            error_log("Point calculation: Old points: $point_member, Used: $pointsUsed, From transaction: $pointFromTransaction, New points: $newPoints");
            
            $sql_update_point = "UPDATE member SET point = ? WHERE id_member = ?";
            $stmt_update_point = $conn->prepare($sql_update_point);
            $stmt_update_point->bind_param('ii', $newPoints, $id_member);
            
            if (!$stmt_update_point->execute()) {
                error_log("Error updating member points: " . $stmt_update_point->error);
                throw new Exception("Gagal mengupdate point member: " . $stmt_update_point->error);
            }
            $stmt_update_point->close();
            
            $sql_transaksi = "INSERT INTO transaksi (invoice, fid_member, total_harga, diskon, total_bayar, uang_bayar, kembalian, total_keuntungan, tanggal_pembelian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_transaksi = $conn->prepare($sql_transaksi);
            $stmt_transaksi->bind_param('siiiiiii', $invoiceNumber, $id_member, $total_harga, $diskon, $totalBayar, $jumlahUang, $kembalian, $total_keuntungan);
            
            if (!$stmt_transaksi->execute()) {
                error_log("Error saving transaction: " . $stmt_transaksi->error);
                throw new Exception("Gagal menyimpan transaksi: " . $stmt_transaksi->error);
            }
            $stmt_transaksi->close();
            
            $id_transaksi = $conn->insert_id;
            error_log("Transaction saved with ID: $id_transaksi");
            
            foreach ($cartData as $item) {
                $itemId = isset($item['id_produk']) ? $item['id_produk'] : (isset($item['id']) ? $item['id'] : null);
                $itemName = isset($item['nama_produk']) ? $item['nama_produk'] : (isset($item['nama']) ? $item['nama'] : 'Unknown Product');
                
                if ($itemId) {
                    $sql_detail = "INSERT INTO detail_transaksi (id_transaksi, id_produk, nama_produk, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_detail = $conn->prepare($sql_detail);
                    $subtotal = $item['qty'] * $item['harga'];
                    $stmt_detail->bind_param('iisiii', $id_transaksi, $itemId, $itemName, $item['qty'], $item['harga'], $subtotal);
                    
                    if (!$stmt_detail->execute()) {
                        error_log("Warning: Could not save transaction detail: " . $stmt_detail->error);
                    }
                    $stmt_detail->close();
                }
            }
            
            $_SESSION['invoice'] = $invoiceNumber;
            $_SESSION['uang_bayar'] = $jumlahUang;
            $_SESSION['kembalian'] = $kembalian;
            $_SESSION['no_tlp'] = $no_tlp;
            $_SESSION['nama_member'] = $nama_member;
            $_SESSION['diskon'] = $diskon;
            $_SESSION['point_used'] = $pointsUsed;
            $_SESSION['new_point'] = $newPoints;
            $_SESSION['cart_data'] = $_POST['cart_data'];
            $_SESSION['total_harga'] = $total_harga;
            $_SESSION['total_bayar'] = $totalBayar;
            
            $conn->commit();
            error_log("Transaction committed successfully. Redirecting to struk.php?invoice=$invoiceNumber");
            
            echo "<script>
                console.log('Transaction successful, clearing cart...');
                localStorage.removeItem('selectedItems');
                
                try {
                    const cart = JSON.parse(localStorage.getItem('cart') || '{}');
                    const processedItems = " . $_POST['cart_data'] . ";
                    
                    for (const item of processedItems) {
                        const itemId = item.id_produk || item.id;
                        if (itemId && cart[itemId]) {
                            delete cart[itemId];
                        }
                    }
                    
                    localStorage.setItem('cart', JSON.stringify(cart));
                    console.log('Cart cleared successfully');
                } catch (e) {
                    console.error('Error clearing cart:', e);
                    localStorage.removeItem('cart');
                }
                
                window.location.href = 'struk.php?invoice=" . $invoiceNumber . "';
            </script>";
            exit;
        } else {
            $conn->rollback();
            error_log("Failed to process transaction - stock issue");
            echo "<script>alert('Gagal memproses transaksi - stok tidak mencukupi'); window.location.href = 'keranjang.php';</script>";
            exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction error: " . $e->getMessage());
        echo "<script>alert('Terjadi kesalahan transaksi: " . addslashes($e->getMessage()) . "'); window.location.href = 'keranjang.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - SweetPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body { background: linear-gradient(135deg, #f8d5e0 0%, #e8c5d0 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; }
        .header { background: linear-gradient(135deg, #a05a5a 0%, #8b4a4a 100%); color: white; padding: 15px; text-align: center; position: relative; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header .fa-user-circle, .header .fa-arrow-left { position: absolute; top: 15px; font-size: 20px; cursor: pointer; transition: transform 0.2s ease; }
        .header .fa-user-circle { right: 15px; }
        .header .fa-arrow-left { left: 15px; }
        .header .fa-arrow-left:hover, .header .fa-user-circle:hover { transform: scale(1.1); }
        .container { background-color: white; margin: 20px auto; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 600px; }
        .invoice-title { text-align: center; color: #a05a5a; margin-bottom: 30px; font-weight: bold; }
        .form-control { border-radius: 8px; border: 2px solid #e0e0e0; transition: all 0.3s ease; }
        .form-control:focus { border-color: #a05a5a; box-shadow: 0 0 0 0.2rem rgba(160, 90, 90, 0.25); }
        .btn-pay { background: linear-gradient(135deg, #a58b6f 0%, #8b7355 100%); color: white; border: none; padding: 15px 30px; border-radius: 10px; display: block; margin: 30px auto 0; font-size: 16px; font-weight: bold; transition: all 0.3s ease; min-width: 200px; }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(165, 139, 111, 0.4); color: white; }
        .btn-pay:disabled { background: #ccc; transform: none; box-shadow: none; }
        .change-info, .member-info { margin-top: 15px; padding: 20px; border-radius: 10px; border-left: 4px solid #a05a5a; }
        .change-info { background: linear-gradient(135deg, #e9f5db 0%, #d4f0c2 100%); }
        .member-info { background: linear-gradient(135deg, #f0e6d2 0%, #e8d5b7 100%); display: none; }
        .table { border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table thead { background: linear-gradient(135deg, #a05a5a 0%, #8b4a4a 100%); color: white; }
        .table tbody tr:hover { background-color: #f8f9fa; }
        .alert-custom { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 1px solid #ffeaa7; border-radius: 10px; padding: 15px; margin: 15px 0; }
        .loading-spinner { display: none; text-align: center; margin: 20px 0; }
        .info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #a05a5a; }
    </style>
</head>
<body>

<div class="header">
    <i class="fas fa-arrow-left" onclick="history.back()"></i>
    <span style="font-size: 18px; font-weight: bold;">💳 SweetPay Invoice</span>
    <i class="fas fa-user-circle"></i>
</div>

<div class="container">
    <h2 class="invoice-title">📋 Invoice Pembayaran</h2>
    
    <div class="info-card">
        <div class="row">
            <div class="col-md-6">
                <strong>📄 Invoice:</strong> <?= $invoiceNumber ?>
            </div>
            <div class="col-md-6 text-md-end">
                <strong>📅 Tanggal:</strong> <?= date('d-m-Y H:i') ?>
            </div>
        </div>
    </div>

    <form id="payment-form" method="POST" action="">
        <div class="mb-4">
            <label class="form-label"><strong>📱 No. Telepon Member:</strong></label>
            <input type="text" id="no-tlp" name="no_tlp" class="form-control" placeholder="Masukkan nomor telepon member..." required>
            <div class="form-text">Minimal 8 digit nomor telepon</div>
        </div>
        
        <div id="member-info" class="member-info">
            <h6><strong>👤 Informasi Member</strong></h6>
            <div class="row">
                <div class="col-md-6">
                    <div><strong>Nama:</strong> <span id="nama-member-display">-</span></div>
                    <div><strong>Point Tersedia:</strong> <span id="point-display">0</span> poin</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><strong>Gunakan Point:</strong></label>
                    <input type="number" id="points-used" name="points_used" class="form-control" value="0" min="0">
                    <small class="text-muted">💡 1 point = Rp. 100 diskon</small>
                </div>
            </div>
        </div>

        <div class="loading-spinner" id="loading-member">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Mencari data member...</div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table" id="invoice-table">
                <thead>
                    <tr>
                        <th>📦 Nama Produk</th>
                        <th>🔢 Qty</th>
                        <th>💰 Harga</th>
                        <th>💵 Total</th>
                    </tr>
                </thead>
                <tbody id="invoice-body">
                    <tr>
                        <td colspan="4" class="text-center">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            Memuat data keranjang...
                        </td>
                    </tr>
                </tbody>
                <tfoot style="background-color: #f8f9fa;">
                    <tr>
                        <td colspan="3"><strong>Total Harga</strong></td>
                        <td id="grand-total"><strong>Rp. 0</strong></td>
                    </tr>
                    <tr>
                        <td colspan="3"><strong>🎁 Diskon Point</strong></td>
                        <td id="diskon-total" style="color: #28a745;"><strong>Rp. 0</strong></td>
                    </tr>
                    <tr style="background-color: #a05a5a; color: white;">
                        <td colspan="3"><strong>💳 TOTAL BAYAR</strong></td>
                        <td id="total-bayar-akhir"><strong>Rp. 0</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mb-4">
            <label class="form-label"><strong>💵 Nominal Uang Dibayar:</strong></label>
            <input type="number" id="uang-dibayar" name="uang_dibayar" class="form-control" placeholder="Masukkan nominal uang..." required>
            <div id="peringatan-uang" class="alert alert-danger mt-2 d-none">
                ❌ Uang yang dibayarkan kurang dari total bayar.
            </div>
        </div>

        <div id="change-calculation" class="change-info d-none">
            <h6><strong>💰 Perhitungan Kembalian</strong></h6>
            <div class="row">
                <div class="col-md-4">
                    <div><strong>Uang Dibayar:</strong></div>
                    <div id="uang-display" class="fs-6 text-primary">Rp. 0</div>
                </div>
                <div class="col-md-4">
                    <div><strong>Total Bayar:</strong></div>
                    <div id="total-display" class="fs-6 text-danger">Rp. 0</div>
                </div>
                <div class="col-md-4">
                    <div><strong>Kembalian:</strong></div>
                    <div id="kembalian-display" class="fs-5 text-success fw-bold">Rp. 0</div>
                </div>
            </div>
        </div>

        <input type="hidden" name="total_harga" id="total-harga">
        <input type="hidden" name="cart_data" id="cart-data">
        <input type="hidden" name="id_member" id="id-member">
        <input type="hidden" name="total_bayar_akhir" id="total-bayar-hidden">
        <input type="hidden" name="total_keuntungan" id="total-keuntungan">

        <button type="submit" name="konfirmasi_pembayaran" class="btn btn-pay" id="btn-konfirmasi">
            <i class="fas fa-credit-card"></i> Konfirmasi Pembayaran
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectedItems = JSON.parse(localStorage.getItem('selectedItems') || '[]');
    const cart = JSON.parse(localStorage.getItem('cart') || '{}');
    
    console.log('Selected items:', selectedItems);
    console.log('Cart data:', cart);
    
    const tbody = document.getElementById('invoice-body');
    const grandTotalEl = document.getElementById('grand-total');
    const totalBayarAkhirEl = document.getElementById('total-bayar-akhir');
    const btnKonfirmasi = document.getElementById('btn-konfirmasi');
    
    const cartData = [];
    let grandTotal = 0;
    let totalKeuntungan = 0;
    let totalBayarAkhir = 0;
    let memberPoint = 0;
    let memberId = '';

    function loadInvoiceData() {
        tbody.innerHTML = '';
        
        if (selectedItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada item yang dipilih untuk checkout</td></tr>';
            btnKonfirmasi.disabled = true;
            return;
        }

        selectedItems.forEach(id => {
            if (cart[id]) {
                const item = cart[id];
                const total = item.qty * item.harga;
                const keuntungan = item.qty * (item.harga - (item.modal || 0));
                
                grandTotal += total;
                totalKeuntungan += keuntungan;
                
                // Ensure consistent id_produk for cart data
                const cartItem = {...item};
                if (!cartItem.id_produk && cartItem.id) {
                    cartItem.id_produk = cartItem.id;
                }
                if (!cartItem.nama_produk && cartItem.nama) {
                    cartItem.nama_produk = cartItem.nama;
                }
                
                cartData.push(cartItem);
                
                tbody.innerHTML += `
                    <tr>
                        <td>${item.nama}</td>
                        <td>${item.qty}</td>
                        <td>Rp. ${item.harga.toLocaleString()}</td>
                        <td>Rp. ${total.toLocaleString()}</td>
                    </tr>
                `;
            }
        });

        updateTotals();
        
        document.getElementById('total-harga').value = grandTotal;
        document.getElementById('total-keuntungan').value = totalKeuntungan;
        document.getElementById('cart-data').value = JSON.stringify(cartData);
        
        console.log('Invoice loaded:', { grandTotal, totalKeuntungan, cartData });
    }

    function updateTotals() {
        totalBayarAkhir = grandTotal;
        
        grandTotalEl.textContent = `Rp. ${grandTotal.toLocaleString()}`;
        totalBayarAkhirEl.textContent = `Rp. ${totalBayarAkhir.toLocaleString()}`;
        document.getElementById('total-bayar-hidden').value = totalBayarAkhir;
    }

    function hitungDiskonDanTotal() {
        const pointsUsed = parseInt(document.getElementById('points-used').value) || 0;
        const diskon = pointsUsed * 100;
        
        totalBayarAkhir = grandTotal - diskon;
        
        document.getElementById('diskon-total').innerHTML = `<strong>Rp. ${diskon.toLocaleString()}</strong>`;
        totalBayarAkhirEl.innerHTML = `<strong>Rp. ${totalBayarAkhir.toLocaleString()}</strong>`;
        document.getElementById('total-bayar-hidden').value = totalBayarAkhir;
        
        hitungKembalian();
    }

    function hitungKembalian() {
        const uang = parseInt(document.getElementById('uang-dibayar').value) || 0;
        const kembalian = uang - totalBayarAkhir;
        const peringatanUang = document.getElementById('peringatan-uang');
        const changeCalculation = document.getElementById('change-calculation');
        
        if (uang > 0) {
            changeCalculation.classList.remove('d-none');
            document.getElementById('uang-display').textContent = `Rp. ${uang.toLocaleString()}`;
            document.getElementById('total-display').textContent = `Rp. ${totalBayarAkhir.toLocaleString()}`;
            
            if (kembalian >= 0) {
                document.getElementById('kembalian-display').textContent = `Rp. ${kembalian.toLocaleString()}`;
                document.getElementById('kembalian-display').className = 'fs-5 text-success fw-bold';
                peringatanUang.classList.add('d-none');
                btnKonfirmasi.disabled = false;
            } else {
                document.getElementById('kembalian-display').textContent = `Kurang Rp. ${Math.abs(kembalian).toLocaleString()}`;
                document.getElementById('kembalian-display').className = 'fs-5 text-danger fw-bold';
                peringatanUang.classList.remove('d-none');
                btnKonfirmasi.disabled = true;
            }
        } else {
            changeCalculation.classList.add('d-none');
            peringatanUang.classList.add('d-none');
            btnKonfirmasi.disabled = cartData.length === 0;
        }
    }

    async function cariMember() {
        const noTlp = document.getElementById('no-tlp').value.trim();
        const loadingMember = document.getElementById('loading-member');
        const memberInfo = document.getElementById('member-info');
        
        if (noTlp.length < 8) {
            memberInfo.style.display = 'none';
            return;
        }
        
        loadingMember.style.display = 'block';
        memberInfo.style.display = 'none';
        
        try {
            const response = await fetch('cari_member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `no_tlp=${encodeURIComponent(noTlp)}`
            });
            
            const result = await response.json();
            loadingMember.style.display = 'none';
            
            if (result.success) {
                document.getElementById('nama-member-display').textContent = result.data.nama_member;
                document.getElementById('point-display').textContent = result.data.point;
                document.getElementById('points-used').max = result.data.point;
                document.getElementById('id-member').value = result.data.id_member;
                
                memberPoint = result.data.point;
                memberId = result.data.id_member;
                
                memberInfo.style.display = 'block';
            } else {
                alert('Member tidak ditemukan');
            }
        } catch (error) {
            loadingMember.style.display = 'none';
            console.error('Error fetching member:', error);
            alert('Terjadi kesalahan saat mencari member');
        }
    }

    document.getElementById('no-tlp').addEventListener('input', debounce(cariMember, 500));
    document.getElementById('points-used').addEventListener('input', function() {
        const maxPoints = parseInt(this.max) || 0;
        const currentValue = parseInt(this.value) || 0;
        
        if (currentValue > maxPoints) {
            this.value = maxPoints;
        }
        
        hitungDiskonDanTotal();
    });
    document.getElementById('uang-dibayar').addEventListener('input', hitungKembalian);

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    loadInvoiceData();
});
</script>

</body>
</html>