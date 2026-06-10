<?php
require_once 'header.php';

// Fetch Statistics
try {
    // 1. Total Staf (Pegawai)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staf'");
    $total_pegawai = $stmt->fetchColumn();

    // 2. Hadir Hari Ini
    $stmt = $pdo->query("SELECT COUNT(*) FROM presensi WHERE tanggal = CURDATE() AND status = 'hadir'");
    $hadir_hari_ini = $stmt->fetchColumn();

    // 3. Terlambat Hari Ini
    $stmt = $pdo->query("SELECT COUNT(*) FROM presensi WHERE tanggal = CURDATE() AND status = 'terlambat'");
    $terlambat_hari_ini = $stmt->fetchColumn();

    // 4. Alpha Hari Ini
    $stmt = $pdo->query("SELECT COUNT(*) FROM presensi WHERE tanggal = CURDATE() AND status = 'alpha'");
    $alpha_hari_ini = $stmt->fetchColumn();

    // Fetch Recent Activity for the Table
    $stmt = $pdo->query("SELECT p.*, u.nama_lengkap, j.nama_jabatan as jabatan, u.nip 
                         FROM presensi p 
                         JOIN users u ON p.user_id = u.id 
                         LEFT JOIN jabatan j ON u.jabatan_id = j.id
                         WHERE p.tanggal = CURDATE() 
                         ORDER BY p.jam_masuk DESC");
    $presensi_hari_ini = $stmt->fetchAll();

    // Fetch Last 7 Days Statistics for Chart.js
    $chart_stmt = $pdo->query("SELECT tanggal, 
                                      SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                                      SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                                      SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha
                               FROM presensi 
                               WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                               GROUP BY tanggal
                               ORDER BY tanggal ASC");
    $chart_data = $chart_stmt->fetchAll();

    // Prepare chart labels and values
    $dates = [];
    $hadir_series = [];
    $terlambat_series = [];
    $alpha_series = [];

    // Fill in default values if not enough data
    if (empty($chart_data)) {
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date('d M', strtotime("-$i days"));
            $hadir_series[] = 0;
            $terlambat_series[] = 0;
            $alpha_series[] = 0;
        }
    } else {
        foreach ($chart_data as $row) {
            $dates[] = date('d M', strtotime($row['tanggal']));
            $hadir_series[] = (int)$row['hadir'];
            $terlambat_series[] = (int)$row['terlambat'];
            $alpha_series[] = (int)$row['alpha'];
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Dashboard Admin</h4>
        <p class="text-muted mb-0">Selamat datang kembali, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>.</p>
    </div>
    <div>
        <span class="badge bg-light text-dark border py-2 px-3">
            <i class="bi bi-calendar3 me-2 text-primary"></i><?= date('d F Y') ?>
        </span>
    </div>
</div>

<!-- Metric Cards -->
<div class="row g-4 mb-4">
    <!-- Total Pegawai -->
    <div class="col-md-3">
        <div class="metric-card total">
            <h6 class="text-muted fw-semibold mb-2">Total Pegawai</h6>
            <h2 class="fw-bold mb-0 text-primary"><?= $total_pegawai ?></h2>
            <div class="metric-icon"><i class="bi bi-people-fill text-primary"></i></div>
        </div>
    </div>
    <!-- Hadir -->
    <div class="col-md-3">
        <div class="metric-card hadir">
            <h6 class="text-muted fw-semibold mb-2">Hadir Hari Ini</h6>
            <h2 class="fw-bold mb-0" style="color: var(--color-hadir);"><?= $hadir_hari_ini ?></h2>
            <div class="metric-icon"><i class="bi bi-check-circle-fill" style="color: var(--color-hadir);"></i></div>
        </div>
    </div>
    <!-- Terlambat -->
    <div class="col-md-3">
        <div class="metric-card terlambat">
            <h6 class="text-muted fw-semibold mb-2">Terlambat</h6>
            <h2 class="fw-bold mb-0" style="color: var(--color-terlambat);"><?= $terlambat_hari_ini ?></h2>
            <div class="metric-icon"><i class="bi bi-clock-fill" style="color: var(--color-terlambat);"></i></div>
        </div>
    </div>
    <!-- Alpha -->
    <div class="col-md-3">
        <div class="metric-card alpha">
            <h6 class="text-muted fw-semibold mb-2">Alpha</h6>
            <h2 class="fw-bold mb-0" style="color: var(--color-alpha);"><?= $alpha_hari_ini ?></h2>
            <div class="metric-icon"><i class="bi bi-x-circle-fill" style="color: var(--color-alpha);"></i></div>
        </div>
    </div>
</div>

<!-- Chart and Recent Activity -->
<div class="row g-4">
    <!-- Attendance Trend Chart -->
    <div class="col-lg-7">
        <div class="panel-card shadow-sm p-4">
            <h5 class="fw-bold mb-4">Tren Kehadiran (7 Hari Terakhir)</h5>
            <canvas id="attendanceChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <!-- Today's Attendance Table -->
    <div class="col-lg-5">
        <div class="panel-card shadow-sm p-4 d-flex flex-column" style="min-height: 380px;">
            <h5 class="fw-bold mb-4">Presensi Hari Ini</h5>
            <div class="table-responsive flex-grow-1">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Pegawai</th>
                            <th>Jam Masuk</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($presensi_hari_ini)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Belum ada data presensi hari ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($presensi_hari_ini as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($p['jabatan'] ?? 'Staf') ?></small>
                                    </td>
                                    <td><?= $p['jam_masuk'] ? date('H:i', strtotime($p['jam_masuk'])) : '-' ?></td>
                                    <td class="text-center">
                                        <?php if ($p['status'] === 'hadir'): ?>
                                            <span class="badge badge-hadir px-2.5 py-1.5 rounded-pill">Hadir</span>
                                        <?php elseif ($p['status'] === 'terlambat'): ?>
                                            <span class="badge badge-terlambat px-2.5 py-1.5 rounded-pill">Terlambat</span>
                                        <?php else: ?>
                                            <span class="badge badge-alpha px-2.5 py-1.5 rounded-pill">Alpha</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js and script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    // Colors corresponding to our design system
    const colorHadir = '#2ECC71';
    const colorTerlambat = '#E67E22';
    const colorAlpha = '#E74C3C';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($dates) ?>,
            datasets: [
                {
                    label: 'Hadir',
                    data: <?= json_encode($hadir_series) ?>,
                    backgroundColor: colorHadir,
                    borderRadius: 5,
                },
                {
                    label: 'Terlambat',
                    data: <?= json_encode($terlambat_series) ?>,
                    backgroundColor: colorTerlambat,
                    borderRadius: 5,
                },
                {
                    label: 'Alpha',
                    data: <?= json_encode($alpha_series) ?>,
                    backgroundColor: colorAlpha,
                    borderRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Outfit'
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: 'Outfit'
                        }
                    }
                },
                y: {
                    grid: {
                        borderDash: [5, 5]
                    },
                    ticks: {
                        font: {
                            family: 'Outfit'
                        },
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>
