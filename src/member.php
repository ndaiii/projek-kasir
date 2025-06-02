<?php
session_start();
include 'connection.php'; 

$error_message = '';

$check_column = "SHOW COLUMNS FROM member LIKE 'last_activity'";
$column_exists = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_exists) == 0) {
    $add_column = "ALTER TABLE member ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP";
    mysqli_query($conn, $add_column);
}

$update_from_transaksi = "UPDATE member m
                          LEFT JOIN (
                              SELECT fid_member, MAX(tanggal_pembelian) as last_transaction 
                              FROM transaksi 
                              GROUP BY fid_member
                          ) t ON m.id_member = t.fid_member
                          SET m.last_activity = COALESCE(t.last_transaction, m.last_activity),
                              m.status = CASE 
                                  WHEN t.fid_member IS NOT NULL THEN 'Aktif' 
                                  WHEN m.last_activity >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 'Aktif'
                                  ELSE 'Tidak Aktif'
                              END";
mysqli_query($conn, $update_from_transaksi);

// Hapus member yang tidak aktif selama lebih dari 3 bulan (setelah status tidak aktif)
$delete_inactive_members = "DELETE FROM member WHERE status = 'Tidak Aktif' AND last_activity < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
mysqli_query($conn, $delete_inactive_members);

// Update last_activity untuk user yang sedang login (tetap dipertahankan)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $update_activity = "UPDATE member SET last_activity = NOW(), status = 'Aktif' WHERE id_member = '$user_id'";
    mysqli_query($conn, $update_activity);
}

// DIHAPUS: Update point berdasarkan transaksi - karena point sudah dikelola dalam proses transaksi
// $query_update_point = "UPDATE member 
//                        SET point = (
//                            SELECT IFNULL(COUNT(*), 0) 
//                            FROM transaksi 
//                            WHERE transaksi.fid_member = member.id_member
//                        )";
// mysqli_query($conn, $query_update_point);

// Ambil data member
$query = "SELECT m.*, 
          DATEDIFF(NOW(), m.last_activity) AS days_since_activity,
          MAX(t.tanggal_pembelian) as last_transaction,
          COUNT(t.id_transaksi) as transaksi_count
          FROM member m
          LEFT JOIN transaksi t ON m.id_member = t.fid_member
          GROUP BY m.id_member
          ORDER BY m.id_member ASC";
$result = mysqli_query($conn, $query);

// Tambah member
if (isset($_POST['tambah_member'])) {
    $nama_member = mysqli_real_escape_string($conn, $_POST['nama_member']);
    $no_tlp = mysqli_real_escape_string($conn, $_POST['no_tlp']);

    // Cek apakah sudah ada member dengan nama dan no_tlp yang sama
    $check_member = "SELECT id_member FROM member WHERE nama_member = '$nama_member' OR no_tlp = '$no_tlp'";
    $check_result = mysqli_query($conn, $check_member);

    if (mysqli_num_rows($check_result) > 0) {
        $error_message = "Member dengan nama atau nomor telepon ini sudah terdaftar.";
    } else {
        $query_insert = "INSERT INTO member (nama_member, no_tlp, point, status, last_activity) 
                        VALUES ('$nama_member', '$no_tlp', 0, 'Aktif', NOW())";
        if (mysqli_query($conn, $query_insert)) {
            header('Location: member.php');
            exit;
        } else {
            $error_message = "Terjadi kesalahan saat menambah member: " . mysqli_error($conn);
        }
    }
}

// Edit member
if (isset($_POST['edit_member'])) {
    $id_member = mysqli_real_escape_string($conn, $_POST['id_member']);
    $nama_member = mysqli_real_escape_string($conn, $_POST['nama_member']);
    $no_tlp = mysqli_real_escape_string($conn, $_POST['no_tlp']);

    // Cek apakah sudah ada member lain dengan nama atau nomor telepon yang sama
    $check_member = "SELECT id_member FROM member WHERE (nama_member = '$nama_member' OR no_tlp = '$no_tlp') AND id_member != '$id_member'";
    $check_result = mysqli_query($conn, $check_member);

    if (mysqli_num_rows($check_result) > 0) {
        echo "Member dengan nama atau nomor telepon ini sudah terdaftar.";
    } else {
        $query_update = "UPDATE member 
                        SET nama_member='$nama_member', 
                            no_tlp='$no_tlp', 
                            last_activity=NOW(),
                            status='Aktif'
                        WHERE id_member='$id_member'";
        if (mysqli_query($conn, $query_update)) {
            header('Location: member.php');
            exit;
        } else {
            echo "Terjadi kesalahan saat mengupdate member: " . mysqli_error($conn);
        }
    }
}

// Aktivasi manual member
if (isset($_POST['aktivasi_member'])) {
    $id_member = mysqli_real_escape_string($conn, $_POST['id_member']);
    $query_aktivasi = "UPDATE member 
                    SET last_activity=NOW(),
                        status='Aktif'
                    WHERE id_member='$id_member'";
    if (mysqli_query($conn, $query_aktivasi)) {
        header('Location: member.php');
        exit;
    } else {
        echo "Terjadi kesalahan saat mengaktifkan member: " . mysqli_error($conn);
    }
}

// Hapus member - HANYA untuk member yang tidak aktif
if (isset($_POST['hapus_member'])) {
    $id_member = mysqli_real_escape_string($conn, $_POST['id_member']);
    
    // Cek status member terlebih dahulu
    $check_status = "SELECT status, nama_member FROM member WHERE id_member='$id_member'";
    $status_result = mysqli_query($conn, $check_status);
    $member_data = mysqli_fetch_assoc($status_result);
    
    if ($member_data['status'] == 'Aktif') {
        $error_message = "Member '{$member_data['nama_member']}' masih aktif dan tidak dapat dihapus. Hanya member yang tidak aktif yang dapat dihapus.";
    } else {
        $query_delete = "DELETE FROM member WHERE id_member='$id_member'";
        if (mysqli_query($conn, $query_delete)) {
            header('Location: member.php');
            exit;
        } else {
            $error_message = "Terjadi kesalahan saat menghapus member: " . mysqli_error($conn);
        }
    }
}

// Function untuk menentukan status tampilan
function getStatusDisplay($status, $days, $has_transactions) {
    if ($has_transactions) {
        return "<button class='btn btn-success'>Aktif</button>";
    } elseif ($status == 'Aktif') {
        return "<button class='btn btn-success'>Aktif</button>";
    } else {
        $months_inactive = floor($days / 30);
        $remaining_months = 3 - $months_inactive;
        
        if ($remaining_months <= 0) {
            return "<button class='btn btn-danger'>Tidak Aktif <span class='badge bg-warning text-dark'>Akan segera dihapus</span></button>";
        } else {
            return "<button class='btn btn-danger'>Tidak Aktif <span class='badge bg-warning text-dark'>$remaining_months bulan lagi akan dihapus</span></button>";
        }
    }
}

// Function untuk menentukan apakah member bisa dihapus
function canDeleteMember($status, $has_transactions) {
    // Member aktif atau yang memiliki transaksi tidak bisa dihapus
    if ($status == 'Aktif' || $has_transactions) {
        return false;
    }
    return true;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f8d5e0; font-family: Arial, sans-serif; }
        .header { background-color: #a05a5a; color: white; text-align: center; padding: 10px 0; position: relative; }
        .header .back-btn, .header .profile-icon { position: absolute; top: 50%; transform: translateY(-50%); font-size: 24px; }
        .back-btn { left: 10px; }
        .profile-icon { right: 10px; cursor: pointer; }
        .container { background-color: #ffebcd; border-radius: 10px; padding: 20px; margin-top: 20px; }
        .table th, .table td { vertical-align: middle; }
        .badge { font-size: 80%; }
        .btn-disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="header">
        <a href="dashboard.php" class="text-white back-btn"><i class="fas fa-arrow-left"></i></a>
        <span>Sweetpay</span>
        <i class="fas fa-user-circle profile-icon"></i>
    </div>
    <div class="container">
        <h2 class="text-center">Data Member</h2>
        <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?= $error_message; ?>
        </div>
        <?php endif; ?>
        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle"></i> Member akan otomatis tidak aktif setelah 3 bulan tidak ada transaksi, dan akan dihapus setelah tidak aktif selama 3 bulan tambahan. Member yang memiliki transaksi akan selalu berstatus aktif. Point dikelola melalui sistem transaksi (1 point per transaksi, dikurangi saat digunakan). <strong>Member aktif tidak dapat dihapus secara manual.</strong>
        </div>
        <button class="btn btn-light mb-3 float-end" data-bs-toggle="modal" data-bs-target="#tambahMember">Tambah Pengguna</button>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Member</th>
                    <th>No Telepon</th>
                    <th>Point</th>
                    <th>Status</th>
                    <th>Terakhir Aktif</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; while ($row = mysqli_fetch_assoc($result)) : ?>
                <?php $has_transactions = !empty($row['last_transaction']) || $row['transaksi_count'] > 0; ?>
                <?php $can_delete = canDeleteMember($row['status'], $has_transactions); ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><?= htmlspecialchars($row['nama_member']); ?></td>
                    <td><?= htmlspecialchars($row['no_tlp']); ?></td>
                    <td>
                        <span class="badge bg-primary"><?= $row['point']; ?> Point</span>
                        <?php if($row['transaksi_count'] > 0): ?>
                        <br><small class="text-muted">Total transaksi: <?= $row['transaksi_count']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= getStatusDisplay($row['status'], $row['days_since_activity'], $has_transactions); ?>
                    </td>
                    <td>
                        <?= date('d M Y', strtotime($row['last_activity'])); ?>
                        <br>
                        <small class="text-muted"><?= $row['days_since_activity']; ?> hari yang lalu</small>
                        <?php if(!empty($row['last_transaction'])): ?>
                        <br>
                        <small class="text-info">Transaksi terakhir: <?= date('d M Y', strtotime($row['last_transaction'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editMember<?= $row['id_member']; ?>">Edit</button>
                        
                        <?php if ($can_delete): ?>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus member ini?');">
                                <input type="hidden" name="id_member" value="<?= $row['id_member']; ?>">
                                <button type="submit" name="hapus_member" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-danger btn-sm btn-disabled" disabled title="Member aktif tidak dapat dihapus">
                                <i class="fas fa-lock"></i> Hapus
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Modal Edit -->
                <div class="modal fade" id="editMember<?= $row['id_member']; ?>" tabindex="-1" aria-labelledby="editMemberLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editMemberLabel">Edit Member</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="" method="POST">
                                    <input type="hidden" name="id_member" value="<?= $row['id_member']; ?>">
                                    <div class="mb-3">
                                        <label for="nama_member" class="form-label">Nama Member</label>
                                        <input type="text" class="form-control" name="nama_member" value="<?= htmlspecialchars($row['nama_member']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="no_tlp" class="form-label">Nomor Telepon</label>
                                        <input type="text" class="form-control" name="no_tlp" value="<?= htmlspecialchars($row['no_tlp']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <div class="alert alert-info">
                                            <small><i class="fas fa-info-circle"></i> Point saat ini: <strong><?= $row['point']; ?></strong> (Point tidak dapat diedit manual, dikelola otomatis melalui transaksi)</small>
                                        </div>
                                    </div>
                                    <?php if (!$can_delete): ?>
                                    <div class="mb-3">
                                        <div class="alert alert-warning">
                                            <small><i class="fas fa-shield-alt"></i> Member ini tidak dapat dihapus karena masih aktif atau memiliki riwayat transaksi.</small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <button type="submit" name="edit_member" class="btn btn-primary w-100">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Tambah Member -->
    <div class="modal fade" id="tambahMember" tabindex="-1" aria-labelledby="tambahMemberLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahMemberLabel">Tambah Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="nama_member" class="form-label">Nama Member</label>
                            <input type="text" class="form-control" name="nama_member" required>
                        </div>
                        <div class="mb-3">
                            <label for="no_tlp" class="form-label">Nomor Telepon</label>
                            <input type="text" class="form-control" name="no_tlp" required>
                        </div>
                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle"></i> Member baru akan dimulai dengan 0 point. Point akan bertambah setiap kali melakukan transaksi.</small>
                        </div>
                        <button type="submit" name="tambah_member" class="btn btn-primary w-100">Tambah</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>