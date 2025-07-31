<?php
// Background script untuk auto-deaktivasi member (untuk development)
// Jalankan dengan: php -f background_auto_deactivate.php &

set_time_limit(0); // Unlimited execution time
ignore_user_abort(true); // Continue even if user closes browser

echo "Starting background auto-deactivation service...\n";
echo "Press Ctrl+C to stop.\n\n";

while (true) {
    try {
        // Include dan jalankan auto_deactivate_members.php
        include 'auto_deactivate_members.php';
        
        echo "[" . date('Y-m-d H:i:s') . "] Auto-deactivation check completed\n";
        
        // Tunggu 30 detik sebelum check lagi
        sleep(30);
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
        sleep(30); // Tunggu sebelum retry
    }
}
?>