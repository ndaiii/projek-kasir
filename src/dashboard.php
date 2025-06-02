<?php
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
include 'connection.php';

// ngambil data transaksi dari database berdasarkan rentang tanggal
function getTransactionDataByDateRange($conn, $start_date, $end_date) {
    // Query untuk mengambil data transaksi berdasarkan rentang tanggal dari detail_transaksi dan produk
    $sql = "SELECT 
                DATE(dt.created_at) as tanggal,
                SUM(dt.subtotal) as pendapatan,
                SUM(dt.qty * (p.harga_jual - p.modal)) as profit
            FROM detail_transaksi dt
            INNER JOIN produk p ON dt.id_produk = p.id_produk
            WHERE DATE(dt.created_at) BETWEEN ? AND ?
            GROUP BY DATE(dt.created_at)
            ORDER BY DATE(dt.created_at)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    // Menghitung total keuntungan dan pendapatan dalam rentang tanggal
    $sql_total = "SELECT 
                    SUM(dt.subtotal) as total_pendapatan,
                    SUM(dt.qty * (p.harga_jual - p.modal)) as total_profit
                  FROM detail_transaksi dt
                  INNER JOIN produk p ON dt.id_produk = p.id_produk
                  WHERE DATE(dt.created_at) BETWEEN ? AND ?";
    
    $stmt_total = $conn->prepare($sql_total);
    $stmt_total->bind_param("ss", $start_date, $end_date);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    
    $total_profit = 0;
    $total_pendapatan = 0;
    
    if ($result_total->num_rows > 0) {
        $row = $result_total->fetch_assoc();
        $total_profit = $row['total_profit'] ?? 0;
        $total_pendapatan = $row['total_pendapatan'] ?? 0;
    }
    
    return [
        'data' => $data,
        'total_profit' => $total_profit,
        'total_pendapatan' => $total_pendapatan
    ];
}

// Mengatur tanggal default
$today = date('Y-m-d');
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : $today;

// Mengambil data transaksi sebenarnya dari database
$transaction_data = getTransactionDataByDateRange($conn, $start_date, $end_date);

// Mengubah format data untuk chart
$labels = [];
$pendapatan_data = [];
$profit_data = [];

$data_transaksi = $transaction_data['data'];
$total_profit = $transaction_data['total_profit'];
$total_pendapatan = $transaction_data['total_pendapatan'];

foreach ($data_transaksi as $item) {
    $labels[] = date('d/m/Y', strtotime($item['tanggal']));
    $pendapatan_data[] = $item['pendapatan'];
    $profit_data[] = $item['profit'];
}

// Jika tidak ada data, tampilkan pesan kosong
$no_data = empty($data_transaksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {  
            background-color: #d2b4a3;
            padding: 20px;
            min-height: 100vh;
        }
        .sidebar img {
            width: 100px;
            margin-bottom: 10px;
        }
        .sidebar .nav-link {
            color: black;
            font-size: 18px;
            margin: 10px 0;
        }
        .sidebar .btn {
            background-color: red;
            color: white;
            width: 100%;
        }
        .content {
            background-color: #f8d5e0;
            padding: 20px;
            min-height: 100vh;
        }
        .btn-group .btn {
            background-color: #ffebcd;
            color: black;
            margin: 5px;
        }
        .chart-container {
            background-color: #ffebcd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }
        .profit-summary {
            background-color: #ffebcd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(165, 69, 0, 0.25);
        }
        .profit-card {
            background-color: #d2b4a3;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            box-shadow: 0 2px 4px rgba(165, 69, 0, 0.25);
        }
        .date-filter {
            background-color: #ffebcd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(165, 69, 0, 0.25);
        }
        .date-input {
            border: 1px solid #d2b4a3;
            border-radius: 5px;
            padding: 8px 12px;
            background-color: white;
        }
        .submit-btn {
            background-color: #d2b4a3;
            color: black;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .submit-btn:hover {
            background-color: #c0a292;
        }
        .print-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 10px;
        }
        .print-btn:hover {
            background-color: #218838;
        }
        .table-container {
            background-color: #ffebcd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(165, 69, 0, 0.25);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #d2b4a3;
        }
        .data-table th {
            background-color: #d2b4a3;
            color: black;
        }
        .data-table tr:hover {
            background-color: rgba(210, 180, 163, 0.2);
        }
        .no-data {
            text-align: center; 
            padding: 30px;
            font-style: italic;
            color: #666;
        }

        /* Print Styles - Khusus untuk detail transaksi */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-area, .print-area * {
                visibility: visible;
            }
            
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background-color: white !important;
            }
            
            .print-header-detail {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #333;
            }
            
            .print-header-detail h1 {
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .print-header-detail p {
                font-size: 14px;
                margin: 5px 0;
            }
            
            .data-table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            
            .data-table th, .data-table td {
                border: 1px solid #333 !important;
                padding: 8px !important;
                text-align: left !important;
            }
            
            .data-table th {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            .data-table tfoot tr {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            .print-btn {
                display: none !important;
            }
        }
        
        .print-header-detail {
            display: none;
        }
        
        .print-actions {
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar">
                <div class="text-center">
                <img src="src/uploads/Sweetpay.png" width="100" height="100">
                </div>
                <div class="nav flex-column">
                    <a class="nav-link" href="admin.php"><i class="fas fa-user-circle fa-2x me-2"></i> Admin</a>
                    <a class="nav-link" href="kategori.php"><i class="fas fa-th-list"></i> Kategori</a>
                    <a class="nav-link" href="transaksi.php"><i class="fas fa-exchange-alt"></i> Transaksi</a>
                    <a class="nav-link" href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a>
                </div>
                <a href="logout.php" class="btn mt-auto"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>

            <div class="col-md-10 content">
                <div class="d-flex justify-content-end">
                    <a class="nav-link d-flex align-items-center" href="profile.php">
                        <i class="fas fa-user-circle fa-2x me-2"></i> <?php echo htmlspecialchars($admin_name); ?>
                    </a>
                </div>
                <div class="d-flex flex-column align-items-center my-4">
                    <div class="d-flex justify-content-around w-100 mb-3">
                        <a href="member.php" class="btn"><i class="fas fa-users"></i> Data Member</a>
                        <a href="produk.php" class="btn"><i class="fas fa-shopping-cart"></i> Data Produk</a>
                        <a href="kategori.php" class="btn"><i class="fas fa-th-large"></i> Data Kategori</a>
                    </div>
                    

                    <div class="date-filter w-100">
                        <h4 class="text-center mb-3">Filter Laporan Berdasarkan Tanggal</h4>
                        <form method="post" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Tanggal Mulai:</label>
                                <input type="text" id="start_date" name="start_date" class="form-control date-input" value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">Tanggal Akhir:</label>
                                <input type="text" id="end_date" name="end_date" class="form-control date-input" value="<?php echo $end_date; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="submit-btn">Tampilkan</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Ringkasan Keuntungan -->
                    <div class="profit-summary w-100">
                        <h3 class="text-center mb-3">Ringkasan Keuntungan</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="profit-card">
                                    <h5>Total Pendapatan</h5>
                                    <h3 class="text-primary">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profit-card">
                                    <h5>Total Keuntungan</h5>
                                    <h3 class="text-success">Rp <?php echo number_format($total_profit, 0, ',', '.'); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- Grafik Pendapatan dan Keuntungan -->
                <div id="chart-section" class="chart-container">
                    <h4 id="chart-title">Pendapatan & Keuntungan <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></h4>
                    <?php if ($no_data): ?>
                        <div class="no-data">
                            <p>Tidak ada data transaksi untuk periode yang dipilih</p>
                        </div>
                    <?php else: ?>
                        <canvas id="profitChart"></canvas>
                    <?php endif; ?>
                </div>
                
                <!-- Tabel Data -->
                <div class="table-container mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="text-center mb-0">Detail Transaksi</h4>
                        <button type="button" class="print-btn" onclick="printDetailTransaksi()">
                            <i class="fas fa-print"></i> Print Detail Transaksi
                        </button>
                    </div>
                    <div class="table-responsive">
                        <!-- Print Header khusus detail transaksi -->
                        <div class="print-header-detail">
                            <h1>SWEETPAY</h1>
                            <p>Detail Transaksi</p>
                            <p>Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                            <p>Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?></p>
                        </div>
                        <?php if ($no_data): ?>
                            <div class="no-data">
                                <p>Tidak ada data transaksi untuk periode yang dipilih</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pendapatan</th>
                                        <th>Keuntungan</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_transaksi as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($item['tanggal'])); ?></td>
                                        <td>Rp <?php echo number_format($item['pendapatan'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($item['profit'], 0, ',', '.'); ?></td>
                                        <td><?php echo $item['pendapatan'] > 0 ? number_format(($item['profit'] / $item['pendapatan']) * 100, 2) : 0; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: bold; background-color: #d2b4a3;">
                                        <td>TOTAL</td>
                                        <td>Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($total_profit, 0, ',', '.'); ?></td>
                                        <td><?php echo $total_pendapatan > 0 ? number_format(($total_profit / $total_pendapatan) * 100, 2) : 0; ?>%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fungsi untuk print detail transaksi saja
        function printDetailTransaksi() {
            // Tambahkan class print-area ke table-container
            const tableContainer = document.querySelector('.table-container');
            tableContainer.classList.add('print-area');
            
            // Print
            window.print();
            
            // Hapus class print-area setelah print
            setTimeout(() => {
                tableContainer.classList.remove('print-area');
            }, 1000);
        }

        // Inisialisasi flatpickr (date picker)
        flatpickr("#start_date", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        flatpickr("#end_date", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        <?php if (!$no_data): ?>
        const labels = <?php echo json_encode($labels); ?>;
        const pendapatanData = <?php echo json_encode($pendapatan_data); ?>;
        const profitData = <?php echo json_encode($profit_data); ?>;
        
        // Membuat chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('profitChart').getContext('2d');
            
            const profitChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Pendapatan',
                            data: pendapatanData,
                            backgroundColor: 'hsla(342, 81.90%, 56.70%, 0.22)',
                            borderColor: 'rgb(235, 54, 108)',
                            borderWidth: 1
                        },
                        {
                            label: 'Keuntungan',
                            data: profitData,
                            backgroundColor: 'rgba(80, 41, 0, 0.38)',
                            borderColor: 'rgb(122, 79, 4)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>

        // Event listener untuk keyboard shortcut print detail transaksi (Ctrl+Shift+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                printDetailTransaksi();
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>