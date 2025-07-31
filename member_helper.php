<?php
// File helper untuk mengelola status member otomatis
include_once 'connection.php';

/**
 * Fungsi untuk mengaktifkan member saat melakukan transaksi
 * Dipanggil saat transaksi berhasil dikonfirmasi
 */
function aktivasiMemberSaatTransaksi($id_member, $conn) {
    // Update last_transaction_time dan status menjadi aktif
    $query = "UPDATE member SET 
              last_transaction_time = NOW(), 
              status = 'aktif',
              last_activity = NOW()
              WHERE id_member = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id_member);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Fungsi untuk update status semua member berdasarkan waktu transaksi terakhir
 * Dipanggil secara berkala atau saat load halaman
 */
function updateStatusMemberOtomatis($conn) {
    // Update status member berdasarkan logika bisnis:
    // - Aktif jika transaksi dalam 2 menit terakhir
    // - Tidak aktif jika lebih dari 2 menit atau belum pernah transaksi
    $query = "UPDATE member SET 
              status = CASE 
                  WHEN last_transaction_time IS NULL THEN 'tidak_aktif'
                  WHEN TIMESTAMPDIFF(MINUTE, last_transaction_time, NOW()) <= 2 THEN 'aktif'
                  ELSE 'tidak_aktif'
              END";
    
    return mysqli_query($conn, $query);
}

/**
 * Fungsi untuk mendapatkan status member saat ini
 */
function getStatusMember($id_member, $conn) {
    $query = "SELECT status, 
              last_transaction_time,
              TIMESTAMPDIFF(MINUTE, last_transaction_time, NOW()) as minutes_since_transaction
              FROM member 
              WHERE id_member = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id_member);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $member = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $member;
}

/**
 * Fungsi untuk cek apakah member masih aktif
 */
function isMemberAktif($id_member, $conn) {
    $member = getStatusMember($id_member, $conn);
    
    if (!$member || !$member['last_transaction_time']) {
        return false;
    }
    
    // Member aktif jika transaksi terakhir dalam 2 menit
    return $member['minutes_since_transaction'] <= 2;
}

/**
 * Fungsi untuk menambah point member saat transaksi
 */
function tambahPointMember($id_member, $point_tambahan, $conn) {
    $query = "UPDATE member SET 
              point = point + ?
              WHERE id_member = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $point_tambahan, $id_member);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Fungsi untuk mengurangi point member saat digunakan
 */
function kurangiPointMember($id_member, $point_digunakan, $conn) {
    // Cek apakah point mencukupi
    $query_check = "SELECT point FROM member WHERE id_member = ?";
    $stmt_check = mysqli_prepare($conn, $query_check);
    mysqli_stmt_bind_param($stmt_check, "i", $id_member);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $member = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);
    
    if (!$member || $member['point'] < $point_digunakan) {
        return false; // Point tidak mencukupi
    }
    
    // Kurangi point
    $query = "UPDATE member SET 
              point = point - ?
              WHERE id_member = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $point_digunakan, $id_member);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Fungsi untuk log aktivitas member (opsional untuk tracking)
 */
function logAktivitasMember($id_member, $aktivitas, $conn) {
    // Buat tabel log jika belum ada
    $create_table = "CREATE TABLE IF NOT EXISTS member_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_member INT,
        aktivitas VARCHAR(255),
        waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_member) REFERENCES member(id_member) ON DELETE CASCADE
    )";
    mysqli_query($conn, $create_table);
    
    // Insert log
    $query = "INSERT INTO member_log (id_member, aktivitas) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $id_member, $aktivitas);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Auto-update status saat file ini di-include
updateStatusMemberOtomatis($conn);
?>