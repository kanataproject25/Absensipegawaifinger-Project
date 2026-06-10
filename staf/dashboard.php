<?php
require_once 'header.php';

$user_id = $_SESSION['user_id'];
$bulan   = date('m');
$tahun   = date('Y');
$nama_bulan = date('F Y');

try {
    // 1. Hitung total hari kerja bulan ini (hari Senin-Jumat)
    $total_hari_kerja = 0;
    $hari_dalam_bulan = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
    for ($d = 1; $d <= $hari_dalam_bulan; $d++) {
        $dow = date('N', mktime(0, 0, 0, $bulan, $d, $tahun));
        if ($dow < 6) $total_hari_kerja++;
    }

    // 2. Kehadiran bulan ini (hadir + terlambat = masuk)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM presensi 
                           WHERE user_id = ? 
                           AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
                           AND status IN ('hadir', 'terlambat')");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $total_hadir = $stmt->fetchColumn();

    // 3. Jumlah terlambat bulan ini
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM presensi 
                           WHERE user_id = ? 
                           AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
                           AND status = 'terlambat'");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $total_terlambat = $stmt->fetchColumn();

    // 4. Jumlah alpha bulan ini
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM presensi 
                           WHERE user_id = ? 
                           AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
                           AND status = 'alpha'");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $total_alpha = $stmt->fetchColumn();

    // 5. Persentase kehadiran
    $persen_hadir = ($total_hari_kerja > 0) 
        ? round(($total_hadir / $total_hari_kerja) * 100, 1) 
        : 0;

    // 6. Riwayat presensi bulan ini (terbaru di atas)
    $stmt = $pdo->prepare("SELECT * FROM presensi 
                           WHERE user_id = ? 
                           AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
                           ORDER BY tanggal DESC");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $riwayat = $stmt->fetchAll();

    // 7. Data chart 7 hari terakhir (untuk grafik personal)
    $chart_stmt = $pdo->prepare("SELECT tanggal, status FROM presensi 
                                 WHERE user_id = ? 
                                 AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                                 ORDER BY tanggal ASC");
    $chart_stmt->execute([$user_id]);
    $chart_raw = $chart_stmt->fetchAll();

    // Siapkan label 7 hari
    $chart_labels = [];
    $chart_status_map = [];
    for ($i = 6; $i >= 0; $i--) {
        $tgl = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('d M', strtotime($tgl));
        $chart_status_map[$tgl] = null;
    }
    foreach ($chart_raw as $r) {
        $chart_status_map[$r['tanggal']] = $r['status'];
    }

    $chart_hadir     = [];
    $chart_terlambat = [];
    $chart_alpha     = [];
    foreach ($chart_status_map as $status) {
        $chart_hadir[]     = ($status === 'hadir')     ? 1 : 0;
        $chart_terlambat[] = ($status === 'terlambat') ? 1 : 0;
        $chart_alpha[]     = ($status === 'alpha')     ? 1 : 0;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Dashboard Staf</h4>
        <p class="text-muted mb-0">Selamat datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>. Berikut ringkasan kehadiran Anda.</p>
    </div>
    <div>
        <span class="badge bg-light text-dark border py-2 px-3">
            <i class="bi bi-calendar3 me-2 text-primary"></i><?= date('d F Y') ?>
        </span>
    </div>
</div>

<!-- Metric Cards -->
<div class="row g-4 mb-4">
    <!-- Kehadiran Bulan Ini -->
    <div class="col-md-3">
        <div class="metric-card hadir">
            <h6 class="text-muted fw-semibold mb-2">Kehadiran Bulan Ini</h6>
            <h2 class="fw-bold mb-1" style="color: var(--color-hadir);"><?= $total_hadir ?></h2>
            <small class="text-muted">dari <?= $total_hari_kerja ?> hari kerja</small>
            <div class="metric-icon"><i class="bi bi-check-circle-fill" style="color: var(--color-hadir);"></i></div>
        </div>
    </div>
    <!-- Jumlah Terlambat -->
    <div class="col-md-3">
        <div class="metric-card terlambat">
            <h6 class="text-muted fw-semibold mb-2">Jumlah Terlambat</h6>
            <h2 class="fw-bold mb-1" style="color: var(--color-terlambat);"><?= $total_terlambat ?></h2>
            <small class="text-muted">kali terlambat bulan ini</small>
            <div class="metric-icon"><i class="bi bi-clock-fill" style="color: var(--color-terlambat);"></i></div>
        </div>
    </div>
    <!-- Persentase Kehadiran -->
    <div class="col-md-3">
        <div class="metric-card persen">
            <h6 class="text-muted fw-semibold mb-2">Persentase Kehadiran</h6>
            <h2 class="fw-bold mb-1" style="color: #3498DB;"><?= $persen_hadir ?>%</h2>
            <div class="progress-custom mt-2">
                <div class="progress-bar bg-primary" role="progressbar" 
                     style="width: <?= $persen_hadir ?>%;" 
                     aria-valuenow="<?= $persen_hadir ?>" aria-valuemin="0" aria-valuemax="100">
                </div>
            </div>
            <div class="metric-icon"><i class="bi bi-bar-chart-fill" style="color: #3498DB;"></i></div>
        </div>
    </div>
    <!-- Alpha -->
    <div class="col-md-3">
        <div class="metric-card total">
            <h6 class="text-muted fw-semibold mb-2">Alpha Bulan Ini</h6>
            <h2 class="fw-bold mb-1" style="color: var(--color-alpha);"><?= $total_alpha ?></h2>
            <small class="text-muted">kali tidak hadir tanpa ket.</small>
            <div class="metric-icon"><i class="bi bi-x-circle-fill" style="color: var(--color-alpha);"></i></div>
        </div>
    </div>
</div>

<!-- Chart + Riwayat Presensi -->
<div class="row g-4">
    <!-- Chart 7 Hari Terakhir -->
    <div class="col-lg-7">
        <div class="panel-card shadow-sm p-4">
            <h5 class="fw-bold mb-4">Kehadiran Saya (7 Hari Terakhir)</h5>
            <canvas id="myAttendanceChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- Riwayat Presensi Bulan Ini -->
    <div class="col-lg-5">
        <div class="panel-card shadow-sm p-4 d-flex flex-column" style="min-height: 380px;">
            <h5 class="fw-bold mb-4">Riwayat Presensi &mdash; <?= $nama_bulan ?></h5>
            <div class="table-responsive flex-grow-1">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                    Belum ada data presensi bulan ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat as $r): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                                        <small class="text-muted"><?= date('l', strtotime($r['tanggal'])) ?></small>
                                    </td>
                                    <td><?= $r['jam_masuk'] ? date('H:i', strtotime($r['jam_masuk'])) : '-' ?></td>
                                    <td class="text-center">
                                        <?php
                                        $status_map = [
                                            'hadir'     => ['class' => 'badge-hadir',     'label' => 'Hadir'],
                                            'terlambat' => ['class' => 'badge-terlambat', 'label' => 'Terlambat'],
                                            'alpha'     => ['class' => 'badge-alpha',     'label' => 'Alpha'],
                                            'sakit'     => ['class' => 'badge-sakit',     'label' => 'Sakit'],
                                            'izin'      => ['class' => 'badge-izin',      'label' => 'Izin'],
                                        ];
                                        $s = $status_map[$r['status']] ?? ['class' => 'bg-secondary', 'label' => ucfirst($r['status'])];
                                        ?>
                                        <span class="badge <?= $s['class'] ?> px-3 py-2 rounded-pill">
                                            <?= $s['label'] ?>
                                        </span>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('myAttendanceChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_values($chart_labels)) ?>,
            datasets: [
                {
                    label: 'Hadir',
                    data: <?= json_encode($chart_hadir) ?>,
                    backgroundColor: '#2ECC71',
                    borderRadius: 6,
                },
                {
                    label: 'Terlambat',
                    data: <?= json_encode($chart_terlambat) ?>,
                    backgroundColor: '#E67E22',
                    borderRadius: 6,
                },
                {
                    label: 'Alpha',
                    data: <?= json_encode($chart_alpha) ?>,
                    backgroundColor: '#E74C3C',
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: 'Outfit' } }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Outfit' } }
                },
                y: {
                    beginAtZero: true,
                    max: 1,
                    grid: { borderDash: [5, 5] },
                    ticks: {
                        font: { family: 'Outfit' },
                        stepSize: 1,
                        callback: val => val === 1 ? 'Ya' : (val === 0 ? 'Tidak' : '')
                    }
                }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>
