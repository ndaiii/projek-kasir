<?php
session_start();
include 'connection.php';

function getProdukByKode($kode) {
    global $koneksi;
    $query = "SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.fid_kategori = k.id_kategori WHERE p.kode_produk = '$kode' AND p.stok > 0 AND p.expired > NOW()";
    $result = mysqli_query($koneksi, $query);
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
}

if (isset($_GET['kode'])) {
    $produk = getProdukByKode($_GET['kode']);
    if ($produk) {
        $cart = $_SESSION['keranjang'] ?? [];
        $id = $produk['id_produk'];
        
        if (isset($cart[$id])) {
            if ($cart[$id]['qty'] < $produk['stok']) $cart[$id]['qty']++;
        } else {
            $cart[$id] = [
                'nama' => $produk['nama_produk'],
                'harga' => $produk['harga'],
                'qty' => 1,
                'tanggal' => date('Y-m-d'),
                'admin' => 'Guest',
                'id_produk' => $id,
            ];
        }
        $_SESSION['keranjang'] = $cart;
    } else {
        setcookie("product_deleted", "true", time() + 5, "/");
        setcookie("deleted_product_id", $_GET['kode'], time() + 5, "/");
    }
    header("Location: keranjang.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SweetPay - Keranjang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background-color: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background-color: #a05a5a; color: white; padding: 10px; text-align: center; position: relative; }
        .header .fa-user-circle, .header .fa-arrow-left { position: absolute; top: 10px; }
        .header .fa-user-circle { right: 10px; }
        .header .fa-arrow-left { left: 10px; cursor: pointer; }
        .container { background-color: #f5e0c8; margin-top: 20px; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; }
        .container::before { content: ""; background-image: url('https://placehold.co/800x600'); background-size: cover; opacity: 0.2; position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: 10px; z-index: -1; }
        .container h2 { text-align: center; margin-bottom: 20px; }
        .table { background-color: #f5e0c8; position: relative; z-index: 1; }
        .btn-pay { background-color: #a58b6f; color: white; border: none; padding: 10px 20px; border-radius: 5px; display: block; margin: 20px auto 0; cursor: pointer; }
        .empty-cart { text-align: center; padding: 30px; }
        .checkbox-column, .action-column { width: 50px; text-align: center; }
        .total-section { margin-top: 20px; text-align: right; font-weight: bold; }
        .notification, #message-toast { background-color: #ffeeba; color: #856404; border: 1px solid #ffeeba; border-radius: 5px; padding: 10px; margin-bottom: 15px; display: none; }
        #message-toast { position: fixed; bottom: 20px; right: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; }
        #reader { margin-bottom: 20px; }
        .qty-control { display: flex; align-items: center; justify-content: center; }
        .qty-control button { width: 30px; height: 30px; padding: 0; line-height: 1; border-radius: 4px; display: flex; align-items: center; justify-content: center; }
        .qty-control span { margin: 0 8px; font-weight: bold; display: inline-block; min-width: 30px; text-align: center; }
        .countdown { font-size: 12px; color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <i class="fas fa-arrow-left" onclick="window.location.href='dashboard.php'"></i>
        <span>SweetPay</span>
        <i class="fas fa-user-circle"></i>
    </div>
    <div class="container">
        <h2>Keranjang</h2>
        <div class="notification" id="notification"></div>
        <div id="message-toast"></div>
        <div style="width: 500px; margin: auto;" id="reader"></div>
        <h5>Pesanan Anda</h5>
        <form id="checkout-form" action="invoice.php" method="POST">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th class="checkbox-column">Pilih</th>
                        <th>No</th>
                        <th>Nama produk</th>
                        <th>Tanggal</th>
                        <th>Admin</th>
                        <th>Jumlah</th>
                        <th>Harga satuan</th>
                        <th>Total</th>
                        <th>Countdown</th>
                        <th class="action-column">Aksi</th>
                    </tr>
                </thead>
                <tbody id="cart-body">
                    <tr><td colspan="10" class="text-center">Memuat...</td></tr>
                </tbody>
            </table>
            <div class="total-section">
                Total: <span id="total-selected">Rp. 0</span>
            </div>
            <button type="submit" class="btn-pay">Bayar</button>
        </form>
    </div>

    <script>
    let countdownIntervals = {};
    
    document.addEventListener('DOMContentLoaded', function() {
        const cart = getCartFromStorage();
        cleanExpiredItems();
        checkDeletedProductCookie();
        checkProductAvailability().then(renderCart);

        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.id);
            if (selected.length === 0) return alert('Pilih minimal satu produk');
            localStorage.setItem('selectedItems', JSON.stringify(selected));
            window.location.href = 'invoice.php';
        });

        const scanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
        scanner.render(onScanSuccess);
        
        setInterval(() => checkProductAvailability().then(renderCart), 30000);
    });

    function checkDeletedProductCookie() {
        if (getCookie('product_deleted') === 'true') {
            const id = getCookie('deleted_product_id');
            if (id) {
                const cart = getCartFromStorage();
                delete cart[id];
                saveCartToStorage(cart);
                showNotification("Produk dihapus karena tidak tersedia");
                document.cookie = "product_deleted=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                document.cookie = "deleted_product_id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            }
        }
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        return parts.length === 2 ? parts.pop().split(';').shift() : null;
    }

    function checkProductAvailability() {
        const cart = getCartFromStorage();
        const ids = Object.keys(cart);
        if (ids.length === 0) return Promise.resolve(0);
        
        return fetch('cek_stok.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(ids)
        })
        .then(r => r.json())
        .then(data => {
            const updated = { ...cart };
            let deleted = 0;
            
            for (const id in cart) {
                if (!(id in data) || data[id] <= 0) {
                    delete updated[id];
                    deleted++;
                } else if (cart[id].qty > data[id]) {
                    updated[id].qty = Math.min(data[id], 9);
                } else if (cart[id].qty > 9) {
                    updated[id].qty = 9;
                }
            }
            
            if (deleted > 0 || JSON.stringify(cart) !== JSON.stringify(updated)) {
                saveCartToStorage(updated);
            }
            return deleted;
        });
    }

    function onScanSuccess(code) {
        fetch(`get_produk.php?kode=${code}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const cart = getCartFromStorage();
                    const p = data.produk;
                    
                    if (cart[p.id]) {
                        if (cart[p.id].qty >= 9) return showNotification("Maksimal 9 item per produk!");
                        if (cart[p.id].qty < p.stok) cart[p.id].qty++;
                        else return showNotification("Stok tidak mencukupi!");
                    } else {
                        cart[p.id] = {
                            nama: p.nama,
                            harga: parseFloat(p.harga),
                            qty: 1,
                            timestamp: Date.now()
                        };
                    }
                    
                    saveCartToStorage(cart);
                    renderCart();
                    updateTotalSelected();
                    showNotification("Produk ditambahkan ke keranjang");
                } else {
                    showNotification("Produk tidak ditemukan");
                }
            })
            .catch(() => showNotification("Gagal memuat produk"));
    }

    function showNotification(msg) {
        const toast = document.getElementById('message-toast');
        toast.textContent = msg;
        toast.style.display = 'block';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    function getCartFromStorage() {
        return JSON.parse(localStorage.getItem('cart') || '{}');
    }

    function saveCartToStorage(cart) {
        localStorage.setItem('cart', JSON.stringify(cart));
    }

    function cleanExpiredItems() {
        const cart = getCartFromStorage();
        const now = Date.now();
        let updated = false;

        for (const id in cart) {
            if (!cart[id].timestamp) {
                cart[id].timestamp = now;
                updated = true;
            } else if (now - cart[id].timestamp > 60000) { 
                delete cart[id];
                updated = true;
                if (countdownIntervals[id]) {
                    clearInterval(countdownIntervals[id]);
                    delete countdownIntervals[id];
                }
            }
        }

        if (updated) {
            saveCartToStorage(cart);
        }
    }

    function removeItem(id) {
        const cart = getCartFromStorage();
        delete cart[id];
        saveCartToStorage(cart);
        if (countdownIntervals[id]) {
            clearInterval(countdownIntervals[id]);
            delete countdownIntervals[id];
        }
        renderCart();
        updateTotalSelected();
    }

    function decreaseQty(id) {
        const cart = getCartFromStorage();
        if (cart[id] && cart[id].qty > 1) {
            cart[id].qty--;
            saveCartToStorage(cart);
            renderCart();
            updateTotalSelected();
        }
    }
 
    function increaseQty(id) {
        const cart = getCartFromStorage();
        if (!cart[id]) return;
        
        if (cart[id].qty >= 9) return showNotification("Maksimal 9 item per produk!");
        
        fetch(`cek_stok_item.php?id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (data.stok > cart[id].qty) {
                    cart[id].qty++;
                    saveCartToStorage(cart);
                    renderCart();
                    updateTotalSelected();
                } else {
                    showNotification("Stok tidak mencukupi!");
                }
            })
            .catch(() => {
                if (cart[id].qty < 9) {
                    cart[id].qty++;
                    saveCartToStorage(cart);
                    renderCart();
                    updateTotalSelected();
                }
            });
    }

    function startCountdown(id, timestamp) {
        if (countdownIntervals[id]) clearInterval(countdownIntervals[id]);
        
        countdownIntervals[id] = setInterval(() => {
            const elapsed = Date.now() - timestamp;
            const remaining = Math.max(0, 60 - Math.floor(elapsed / 60000));
            
            const countdownEl = document.getElementById(`countdown-${id}`);
            if (countdownEl) {
                countdownEl.textContent = `${remaining}s`;
                if (remaining === 0) {
                    clearInterval(countdownIntervals[id]);
                    delete countdownIntervals[id];
                    removeItem(id);
                    showNotification("Produk dihapus otomatis setelah 60 detik");
                }
            }
        }, 60000);
    }

    function renderCart() {
        const cart = getCartFromStorage();
        const tbody = document.getElementById('cart-body');
        
        // Clear existing intervals
        Object.values(countdownIntervals).forEach(clearInterval);
        countdownIntervals = {};
        
        if (Object.keys(cart).length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="empty-cart">Keranjang kosong</td></tr>';
            document.querySelector('.btn-pay').disabled = true;
            return;
        }

        let html = '';
        let i = 1;
        const admin = "<?= $_SESSION['username'] ?? 'Admin' ?>";

        for (const id in cart) {
            const item = cart[id];
            const total = item.qty * item.harga;
            const date = new Date(item.timestamp || Date.now()).toLocaleDateString('id-ID');
            
            html += `
                <tr data-id="${id}">
                    <td><input type="checkbox" class="item-checkbox" data-id="${id}" onchange="updateTotalSelected()"></td>
                    <td>${i++}</td>
                    <td>${item.nama}</td>
                    <td>${date}</td>
                    <td>${admin}</td>
                    <td>
                        <div class="qty-control">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="decreaseQty('${id}')">-</button>
                            <span>${item.qty}</span>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="increaseQty('${id}')">+</button>
                        </div>
                    </td>
                    <td>Rp. ${item.harga.toLocaleString()}</td>
                    <td>Rp. ${total.toLocaleString()}</td>
                    <td><div class="countdown" id="countdown-${id}">60s</div></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem('${id}')"><i class="fas fa-trash"></i></button></td>
                </tr>
            `;
            
         
            startCountdown(id, item.timestamp || Date.now());
        }
        
        tbody.innerHTML = html;
        document.querySelector('.btn-pay').disabled = false;
    }

    function updateTotalSelected() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        const cart = getCartFromStorage();
        let total = 0;

        checkboxes.forEach(cb => {
            const id = cb.dataset.id;
            if (cart[id]) total += cart[id].qty * cart[id].harga;
        });

        document.getElementById('total-selected').textContent = `Rp. ${total.toLocaleString()}`;
    }
    </script>
</body>
</html>