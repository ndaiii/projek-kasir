<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_tlp = isset($_POST['no_tlp']) ? trim($_POST['no_tlp']) : '';
    
    if (empty($no_tlp)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nomor telepon tidak boleh kosong'
        ]);
        exit;
    }
    
    try {
        // Query untuk mencari member berdasarkan nomor telepon
        $sql = "SELECT id_member, nama_member, point FROM member WHERE no_tlp = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('s', $no_tlp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $member = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => [
                    'id_member' => $member['id_member'],
                    'nama_member' => $member['nama_member'],
                    'point' => intval($member['point'])
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Member tidak ditemukan'
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error in cari_member.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan'
    ]);
}

$conn->close();
?>