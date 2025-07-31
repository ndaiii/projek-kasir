<?php
// File untuk auto-deaktivasi member yang tidak bertransaksi selama 2 menit
// File ini harus dijalankan secara periodik (misal setiap 30 detik) menggunakan cron job

include 'connection.php';

try {
    // Log untuk debugging
    error_log("Running auto-deactivation check at " . date('Y-m-d H:i:s'));
    
    // Query untuk mencari member yang aktif tapi tidak melakukan transaksi selama 2 menit
    // Berdasarkan last_activity yang diupdate setiap kali member melakukan transaksi
    $query_deactivate = "UPDATE member 
                        SET status = 'Tidak Aktif' 
                        WHERE status = 'Aktif' 
                        AND last_activity < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                        AND id_member NOT IN (
                            SELECT DISTINCT fid_member 
                            FROM transaksi 
                            WHERE tanggal_pembelian >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                            AND fid_member IS NOT NULL
                        )";
    
    $result = mysqli_query($conn, $query_deactivate);
    
    if ($result) {
        $affected_rows = mysqli_affected_rows($conn);
        if ($affected_rows > 0) {
            error_log("Auto-deactivated $affected_rows members for inactivity (2+ minutes)");
        }
        echo "Auto-deactivation check completed. $affected_rows members deactivated.\n";
    } else {
        error_log("Error in auto-deactivation: " . mysqli_error($conn));
        echo "Error in auto-deactivation: " . mysqli_error($conn) . "\n";
    }
    
} catch (Exception $e) {
    error_log("Exception in auto-deactivation: " . $e->getMessage());
    echo "Exception: " . $e->getMessage() . "\n";
}

mysqli_close($conn);
?>