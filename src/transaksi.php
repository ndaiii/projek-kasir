<?php
session_start();
include 'connection.php';

$today = date('Y-m-d');
$sql = "SELECT * FROM produk WHERE stok > 0 AND tanggal_exp > ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $today);
$stmt->execute();
$result = $stmt->get_result();
$produk = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transaksi - SweetPay</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background-color: #a05a5a; color: white; text-align: center; padding: 1px 0; position: relative; }
        .header i { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); font-size: 30px; cursor: pointer; }
        .header .back-icon { right: 97%; }
        .cart-count { position: absolute; top: 10px; right: 10px; background-color: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; }
        .content { background-color: #f8d5e0; padding: 20px; border-radius: 10px; margin: 20px; position: relative; }
        .content::before {
            content: ''; background-image: url('https://placehold.co/1000x1000');
            background-size: cover; opacity: 0.2;
            position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: -1;
        }
        .product-card { background-color: #f8d5e0; border: none; text-align: center; margin-bottom: 15px; }
        .product-card img { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; }
        .product-card .card-body { padding: 10px; }
        .product-card .btn { background-color: #a68b8b; color: white; border: none; }
        .cart { 
            background-color: #fff8dc; 
            border-radius: 10px; 
            padding: 20px; 
            margin-top: 20px; 
            display: none; 
            position: fixed;
            right: 20px;
            top: 60px;
            width: 350px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .mini-cart {
            background-color: #fff8dc; 
            border-radius: 10px; 
            padding: 15px; 
            position: fixed;
            right: 20px;
            top: 60px;
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
        .cart-item { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 10px; 
            align-items: center; 
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .cart-item img { width: 50px; height: 50px; border-radius: 5px; object-fit: cover; }
        .cart-item .quantity input { width: 40px; text-align: center; }
        .cart-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        .stock-info {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
        .out-of-stock {
            color: #d9534f;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>SweetPay</h1>
    <i class="fas fa-shopping-cart" onclick="toggleMiniCart()"></i>
    <i class="fas fa-arrow-left back-icon" onclick="window.location.href='dashboard.php'"></i>
    <span id="cart-count" class="cart-count" style="display: none;">0</span>
</div>

<!-- Mini Cart (tampilan kecil saat icon keranjang diklik) -->
<div id="mini-cart" class="mini-cart">
    <h5>Keranjang Belanja</h5>
    <div id="mini-cart-items">
        <!-- Item keranjang akan ditampilkan di sini -->
    </div>
    <div class="text-end mt-3">
        <p><strong>Total:</strong> Rp. <span id="mini-total">0</span></p>
        <div class="cart-buttons">
            <button class="btn btn-success btn-sm" onclick="goToCart()">Checkout</button>
            <button class="btn btn-secondary btn-sm" onclick="toggleMiniCart()">Tutup</button>
        </div>
    </div>
</div>

<div class="container content">
    <h2 class="text-center">Transaksi</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="row">
                <?php foreach ($produk as $p): ?>
                    <div class="col-md-3">
                        <div class="card product-card">
                            <img src="<?= $p['gambar']; ?>" alt="<?= $p['nama_produk']; ?>">
                            <div class="card-body">
                                <p><?= $p['nama_produk']; ?><br>Rp. <?= number_format($p['harga_jual'], 0, ',', '.'); ?></p>
                                <div class="stock-info">
                                    Stock: <?= $p['stok']; ?> pcs
                                </div>
                                <button class="btn add-to-cart"
                                        data-id="<?= $p['id_produk']; ?>"
                                        data-nama="<?= $p['nama_produk']; ?>"
                                        data-harga="<?= $p['harga_jual']; ?>"
                                        data-stok="<?= $p['stok']; ?>"
                                        data-gambar="<?= $p['gambar']; ?>"
                                        <?= $p['stok'] <= 0 ? 'disabled' : ''; ?>>
                                    <?= $p['stok'] > 0 ? 'Masukkan keranjang' : 'Stok Habis'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Mendapatkan keranjang dari localStorage
function getCart() {
    const cartData = localStorage.getItem('cart');
    return cartData ? JSON.parse(cartData) : {};
}

// Menyimpan keranjang ke localStorage
function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
}

// Inisialisasi keranjang
let cart = getCart();

// Membersihkan item yang sudah expired (lebih dari 1 hari)
function cleanExpiredItems() {
    const now = new Date().getTime();
    let isUpdated = false;
    
    for (const id in cart) {
        // Periksa apakah item memiliki tanggal timestamp
        if (cart[id].timestamp) {
            // 24 jam = 86400000 milidetik
            if (now - cart[id].timestamp > 86400000) {
                delete cart[id];
                isUpdated = true;
            }
        } else {
            // Tambahkan timestamp untuk item yang belum memiliki timestamp
            cart[id].timestamp = now;
            isUpdated = true;
        }
    }
    
    if (isUpdated) {
        saveCart(cart);
    }
}

// Jalankan pembersihan saat halaman dimuat
cleanExpiredItems();

// Render keranjang mini
function renderMiniCart() {
    let html = '';
    let total = 0;
    let itemCount = 0;
    
    for (const id in cart) {
        const item = cart[id];
        total += item.harga * item.qty;
        itemCount += parseInt(item.qty);
        
        html += `
            <div class="cart-item">
                <img src="${item.gambar}" alt="${item.nama}">
                <div>
                    <strong>${item.nama}</strong><br>
                    ${item.qty} x Rp. ${item.harga.toLocaleString()}
                </div>
            </div>`;
    }
    
    if (itemCount === 0) {
        html = '<p class="text-center">Keranjang kosong</p>';
    }
    
    document.getElementById('mini-cart-items').innerHTML = html;
    document.getElementById('mini-total').textContent = total.toLocaleString();
    document.getElementById('cart-count').textContent = itemCount;
    document.getElementById('cart-count').style.display = itemCount > 0 ? 'block' : 'none';
}

function tambah(id) {
    const stok = parseInt(cart[id].stok);
    if (cart[id].qty < stok && getCartItemCount() < 9) {
        cart[id].qty++;
        saveCart(cart);
        renderMiniCart();
    } else if (getCartItemCount() >= 9) {
        alert("Maksimal 9 item di keranjang!");
    } else {
        alert("Stok tidak mencukupi!");
    }
}

function kurangi(id) {
    if (cart[id].qty > 1) {
        cart[id].qty--;
    } else {
        delete cart[id];
    }
    saveCart(cart);
    renderMiniCart();
}

function getCartItemCount() {
    let count = 0;
    for (const id in cart) {
        count += parseInt(cart[id].qty);
    }
    return count;
}

// Event listener untuk tombol "Masukkan keranjang"
document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const now = new Date().getTime();
        
        if (cart[id]) {
            tambah(id);
        } else if (getCartItemCount() < 9) {
            cart[id] = {
                id: id,
                nama: this.dataset.nama,
                harga: parseFloat(this.dataset.harga),
                stok: parseInt(this.dataset.stok),
                gambar: this.dataset.gambar,
                qty: 1,
                timestamp: now
            };
            saveCart(cart);
            renderMiniCart();
            // Tampilkan mini cart saat produk ditambahkan
            document.getElementById('mini-cart').style.display = 'block';
        } else {
            alert("Maksimal 9 item di keranjang!");
        }
    });
});

// Toggle mini cart
function toggleMiniCart() {
    const miniCart = document.getElementById('mini-cart');
    
    if (miniCart.style.display === 'none' || miniCart.style.display === '') {
        renderMiniCart();
        miniCart.style.display = 'block';
    } else {
        miniCart.style.display = 'none';
    }
}

// Navigasi ke halaman keranjang
function goToCart() {
    window.location.href = 'keranjang.php';
}

// Merender keranjang ketika halaman dimuat
renderMiniCart();

// Menutup mini cart jika diklik di luar
document.addEventListener('click', function(event) {
    const miniCart = document.getElementById('mini-cart');
    const cartIcon = document.querySelector('.fa-shopping-cart');
    
    if (miniCart.style.display === 'block' && 
        !miniCart.contains(event.target) && 
        event.target !== cartIcon) {
        miniCart.style.display = 'none';
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>