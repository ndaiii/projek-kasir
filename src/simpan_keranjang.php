<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) $data = $_POST;

$action = $data['action'] ?? '';

switch ($action) {
    case 'add_to_cart':
        addToCart($conn, $data);
        break;
    case 'get_product_info':
        getProductInfo($conn, $data);
        break;
    case 'validate_stock':
        validateStock($conn, $data);
        break;
    default:
        sendResponse(false, 'Action tidak valid');
}

function addToCart($conn, $data) {
    if (!isset($data['id_produk']) || !isset($data['qty'])) {
        sendResponse(false, 'Data produk tidak lengkap');
    }
    
    $id_produk = intval($data['id_produk']);
    $qty = intval($data['qty']);
    
    if ($qty <= 0) {
        sendResponse(false, 'Quantity harus lebih dari 0');
    }
    
    $sql = "SELECT id_produk, nama_produk, harga_jual, stok, modal FROM produk WHERE id_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Produk tidak ditemukan');
    }
    
    $produk = $result->fetch_assoc();
    $stmt->close();
    
    if ($produk['stok'] < $qty) {
        sendResponse(false, 'Stok tidak mencukupi. Stok tersedia: ' . $produk['stok']);
    }
    
    $product_data = [
        'id' => $produk['id_produk'],
        'nama' => $produk['nama_produk'],
        'harga' => $produk['harga_jual'],
        'stok' => $produk['stok'],
        'modal' => $produk['modal'] ?? 0,
        'qty' => $qty
    ];
    
    sendResponse(true, 'Produk berhasil ditambahkan ke keranjang', $product_data);
}

function getProductInfo($conn, $data) {
    if (!isset($data['id_produk'])) {
        sendResponse(false, 'ID produk tidak ditemukan');
    }
    
    $id_produk = intval($data['id_produk']);
    
    $sql = "SELECT id_produk, nama_produk, harga_jual, stok, modal, gambar FROM produk WHERE id_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Produk tidak ditemukan');
    }
    
    $produk = $result->fetch_assoc();
    $stmt->close();
    
    $product_data = [
        'id' => $produk['id_produk'],
        'nama' => $produk['nama_produk'],
        'harga' => $produk['harga_jual'],
        'stok' => $produk['stok'],
        'modal' => $produk['modal'] ?? 0,
        'gambar' => $produk['gambar'] ?? ''
    ];
    
    sendResponse(true, 'Data produk berhasil diambil', $product_data);
}

function validateStock($conn, $data) {
    if (!isset($data['cart_data'])) {
        sendResponse(false, 'Data keranjang tidak ditemukan');
    }
    
    $cartData = $data['cart_data'];
    $errors = [];
    
    foreach ($cartData as $item) {
        $sql = "SELECT nama_produk, stok FROM produk WHERE id_produk = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $item['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $produk = $result->fetch_assoc();
            if ($produk['stok'] < $item['qty']) {
                $errors[] = [
                    'id' => $item['id'],
                    'nama' => $produk['nama_produk'],
                    'stok_tersedia' => $produk['stok'],
                    'qty_diminta' => $item['qty']
                ];
            }
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        sendResponse(true, 'Stok semua produk mencukupi');
    } else {
        sendResponse(false, 'Beberapa produk memiliki stok tidak mencukupi', $errors);
    }
}
?>

<script>
class CartManager {
    constructor() {
        this.storageKey = 'cart';
        this.selectedKey = 'selectedItems';
    }
    
    getCart() {
        const cart = localStorage.getItem(this.storageKey);
        return cart ? JSON.parse(cart) : {};
    }
    
    saveCart(cart) {
        localStorage.setItem(this.storageKey, JSON.stringify(cart));
    }
    
    getSelectedItems() {
        const selected = localStorage.getItem(this.selectedKey);
        return selected ? JSON.parse(selected) : [];
    }
    
    saveSelectedItems(items) {
        localStorage.setItem(this.selectedKey, JSON.stringify(items));
    }
    
    async addToCart(productId, qty = 1) {
        try {
            const response = await fetch('simpan_keranjang.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'add_to_cart',
                    id_produk: productId,
                    qty: qty
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const cart = this.getCart();
                const product = result.data;
                
                if (cart[productId]) {
                    cart[productId].qty += qty;
                } else {
                    cart[productId] = product;
                }
                
                this.saveCart(cart);
                return { success: true, message: result.message, data: product };
            } else {
                return { success: false, message: result.message };
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            return { success: false, message: 'Terjadi kesalahan saat menambah ke keranjang' };
        }
    }
    
    removeFromCart(productId) {
        const cart = this.getCart();
        delete cart[productId];
        this.saveCart(cart);
        
        const selected = this.getSelectedItems();
        const newSelected = selected.filter(id => id != productId);
        this.saveSelectedItems(newSelected);
    }
    
    clearCart() {
        localStorage.removeItem(this.storageKey);
        localStorage.removeItem(this.selectedKey);
    }
    
    getTotalItems() {
        const cart = this.getCart();
        let total = 0;
        for (const item of Object.values(cart)) {
            total += item.qty;
        }
        return total;
    }
    
    getTotalPrice() {
        const cart = this.getCart();
        let total = 0;
        for (const item of Object.values(cart)) {
            total += item.qty * item.harga;
        }
        return total;
    }
    
    getSelectedCartData() {
        const cart = this.getCart();
        const selected = this.getSelectedItems();
        const cartData = [];
        
        selected.forEach(id => {
            if (cart[id]) {
                cartData.push(cart[id]);
            }
        });
        
        return cartData;
    }
    
    async validateStock() {
        const cartData = this.getSelectedCartData();
        
        try {
            const response = await fetch('simpan_keranjang.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'validate_stock',
                    cart_data: cartData
                })
            });
            
            return await response.json();
        } catch (error) {
            console.error('Error validating stock:', error);
            return { success: false, message: 'Terjadi kesalahan saat validasi stok' };
        }
    }
}

// Initialize cart manager
const cartManager = new CartManager();

// Add to cart button handler
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-add-cart').forEach(button => {
        button.addEventListener('click', async function() {
            const productId = this.dataset.productId;
            const qty = parseInt(this.dataset.qty || 1);
            
            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            const result = await cartManager.addToCart(productId, qty);
            
            this.disabled = false;
            this.innerHTML = originalText;
            
            if (result.success) {
                updateCartBadge();
                showNotification(result.message, 'success');
            } else {
                showNotification(result.message, 'error');
            }
        });
    });
    
    updateCartBadge();
});

function updateCartBadge() {
    const totalItems = cartManager.getTotalItems();
    const badges = document.querySelectorAll('.cart-badge, .badge-cart');
    
    badges.forEach(badge => {
        badge.textContent = totalItems;
        badge.style.display = totalItems > 0 ? 'inline' : 'none';
    });
}

function showNotification(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: type === 'success' ? 'Berhasil!' : 'Error!',
            text: message,
            icon: type,
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        alert(message);
    }
}

// Export for global access
window.CartManager = CartManager;
window.cartManager = cartManager;
</script>