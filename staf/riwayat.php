<?php
require_once 'header.php';

$user_id = $_SESSION['user_id'];

// Filter bulan & tahun
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validasi range
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('m');
if ($tahun < 2020 || $tahun > 2099) $tahun = (int)date('Y');

$nama_bulan_label = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));

try {
    // Ambil semua riwayat presensi sesuai filter
    $stmt = $pdo->prepare("SELECT * FROM presensi 
                           WHERE user_id = ? 
                           AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
                           ORDER BY tanggal DESC");
    $stmt->execute([$user_id, $bulan, $tahun]);
    $riwayat = $stmt->fetchAll();

    // Ringkasan statistik bulan ini
    $stmt_stat = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
        SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin
        FROM presensi 
        WHERE user_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_stat->execute([$user_id, $bulan, $tahun]);
    $stat = $stmt_stat->fetch();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Riwayat Presensi</h4>
        <p class="text-muted mb-0">Rincian kehadiran Anda — <?= $nama_bulan_label ?></p>
    </div>
    <div>
        <span class="badge bg-light text-dark border py-2 px-3">
            <i class="bi bi-calendar3 me-2 text-primary"></i><?= date('d F Y') ?>
        </span>
    </div>
</div>

<!-- Filter Bulan & Tahun -->
<div class="panel-card shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label fw-semibold text-secondary mb-1 small">Bulan</label>
            <select name="bulan" id="bulan" class="form-select form-select-sm" style="min-width: 130px;">
                <?php
                $bulan_names = ['Januari','Februari','Maret','April','Mei','Juni',
                                'Juli','Agustus','September','Oktober','November','Desember'];
                for ($i = 1; $i <= 12; $i++):
                ?>
                    <option value="<?= $i ?>" <?= ($i == $bulan) ? 'selected' : '' ?>>
                        <?= $bulan_names[$i-1] ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label fw-semibold text-secondary mb-1 small">Tahun</label>
            <select name="tahun" id="tahun" class="form-select form-select-sm" style="min-width: 90px;">
                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= ($y == $tahun) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm px-4">
                <i class="bi bi-funnel me-1"></i> Tampilkan
            </button>
        </div>
    </form>
</div>

<!-- Statistik Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col">
        <div class="metric-card hadir text-center py-3">
            <h3 class="fw-bold mb-0" style="color: var(--color-hadir);"><?= $stat['hadir'] ?? 0 ?></h3>
            <small class="text-muted fw-semibold">Hadir</small>
            <div class="metric-icon"><i class="bi bi-check-circle-fill" style="color: var(--color-hadir);"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="metric-card terlambat text-center py-3">
            <h3 class="fw-bold mb-0" style="color: var(--color-terlambat);"><?= $stat['terlambat'] ?? 0 ?></h3>
            <small class="text-muted fw-semibold">Terlambat</small>
            <div class="metric-icon"><i class="bi bi-clock-fill" style="color: var(--color-terlambat);"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="metric-card total text-center py-3">
            <h3 class="fw-bold mb-0" style="color: var(--color-alpha);"><?= $stat['alpha'] ?? 0 ?></h3>
            <small class="text-muted fw-semibold">Alpha</small>
            <div class="metric-icon"><i class="bi bi-x-circle-fill" style="color: var(--color-alpha);"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="metric-card persen text-center py-3">
            <h3 class="fw-bold mb-0" style="color: #3498DB;"><?= $stat['sakit'] ?? 0 ?></h3>
            <small class="text-muted fw-semibold">Sakit</small>
            <div class="metric-icon"><i class="bi bi-heart-pulse-fill" style="color: #3498DB;"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="metric-card text-center py-3" style="border-left: 4px solid #9B59B6;">
            <h3 class="fw-bold mb-0" style="color: #8E44AD;"><?= $stat['izin'] ?? 0 ?></h3>
            <small class="text-muted fw-semibold">Izin</small>
            <div class="metric-icon"><i class="bi bi-file-earmark-check-fill" style="color: #8E44AD;"></i></div>
        </div>
    </div>
</div>

<!-- Tabel Riwayat -->
<div class="panel-card shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-table me-2 text-primary"></i>Detail Presensi
        </h5>
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
            <?= count($riwayat) ?> data ditemukan
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 45px;">#</th>
                    <th>Tanggal</th>
                    <th>Hari</th>
                    <th class="text-center">Jam Masuk</th>
                    <th class="text-center">Jam Pulang</th>
                    <th class="text-center">Durasi Kerja</th>
                    <th class="text-center">Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayat)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                            Tidak ada data presensi untuk periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $status_map = [
                        'hadir'     => ['class' => 'badge-hadir',     'label' => 'Hadir',     'icon' => 'check-circle-fill'],
                        'terlambat' => ['class' => 'badge-terlambat', 'label' => 'Terlambat', 'icon' => 'clock-fill'],
                        'alpha'     => ['class' => 'badge-alpha',     'label' => 'Alpha',     'icon' => 'x-circle-fill'],
                        'sakit'     => ['class' => 'badge-sakit',     'label' => 'Sakit',     'icon' => 'heart-pulse-fill'],
                        'izin'      => ['class' => 'badge-izin',      'label' => 'Izin',      'icon' => 'file-earmark-check-fill'],
                    ];
                    $no = 1;
                    foreach ($riwayat as $r):
                        $s = $status_map[$r['status']] ?? ['class' => 'bg-secondary', 'label' => ucfirst($r['status']), 'icon' => 'dash-circle'];

                        // Hitung durasi kerja
                        $durasi = '-';
                        if ($r['jam_masuk'] && $r['jam_keluar']) {
                            $masuk  = strtotime($r['jam_masuk']);
                            $keluar = strtotime($r['jam_keluar']);
                            $diff   = $keluar - $masuk;
                            if ($diff > 0) {
                                $jam  = floor($diff / 3600);
                                $menit = floor(($diff % 3600) / 60);
                                $durasi = "{$jam}j {$menit}m";
                            }
                        }
                    ?>
                    <tr>
                        <td class="text-muted small"><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold"><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                        </td>
                        <td class="text-muted"><?= date('l', strtotime($r['tanggal'])) ?></td>
                        <td class="text-center">
                            <?php if ($r['jam_masuk']): ?>
                                <span class="fw-semibold"><?= date('H:i', strtotime($r['jam_masuk'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['jam_keluar']): ?>
                                <span class="fw-semibold"><?= date('H:i', strtotime($r['jam_keluar'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="text-muted small"><?= $durasi ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $s['class'] ?> px-3 py-2 rounded-pill">
                                <i class="bi bi-<?= $s['icon'] ?> me-1"></i><?= $s['label'] ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
