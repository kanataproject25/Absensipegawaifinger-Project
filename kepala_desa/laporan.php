<?php
require_once 'header.php';

$success = '';
$error = '';

$type = $_GET['type'] ?? 'harian';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$presensi_list = [];

// Prepare query based on report type
if ($type === 'harian') {
    $query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
              FROM presensi p 
              JOIN users u ON p.user_id = u.id 
              LEFT JOIN jabatan j ON u.jabatan_id = j.id
              WHERE p.tanggal = :date 
              ORDER BY u.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':date' => $date]);
    $presensi_list = $stmt->fetchAll();
} elseif ($type === 'bulanan') {
    $query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
              FROM presensi p 
              JOIN users u ON p.user_id = u.id 
              LEFT JOIN jabatan j ON u.jabatan_id = j.id
              WHERE MONTH(p.tanggal) = :month AND YEAR(p.tanggal) = :year 
              ORDER BY p.tanggal ASC, u.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':month' => $month, ':year' => $year]);
    $presensi_list = $stmt->fetchAll();
} else { // tahunan
    $query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
              FROM presensi p 
              JOIN users u ON p.user_id = u.id 
              LEFT JOIN jabatan j ON u.jabatan_id = j.id
              WHERE YEAR(p.tanggal) = :year 
              ORDER BY p.tanggal ASC, u.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':year' => $year]);
    $presensi_list = $stmt->fetchAll();
}

$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Laporan Presensi</h4>
        <p class="text-muted mb-0">Cetak dan tinjau laporan presensi pegawai secara harian, bulanan, atau tahunan.</p>
    </div>
    <?php if (!empty($presensi_list)): ?>
        <a href="cetak_pdf.php?type=<?= $type ?>&date=<?= $date ?>&month=<?= $month ?>&year=<?= $year ?>" 
           target="_blank" 
           class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf me-2"></i> Cetak Laporan (PDF)
        </a>
    <?php endif; ?>
</div>

<!-- Filters Panel -->
<div class="card panel-card py-3 px-4 mb-4">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="type" class="form-label text-secondary fw-semibold">Jenis Laporan</label>
            <select class="form-select" id="type" name="type" onchange="toggleFilterInputs(this.value)">
                <option value="harian" <?= $type === 'harian' ? 'selected' : '' ?>>Harian</option>
                <option value="bulanan" <?= $type === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                <option value="tahunan" <?= $type === 'tahunan' ? 'selected' : '' ?>>Tahunan</option>
            </select>
        </div>

        <!-- Date Input (For Harian) -->
        <div class="col-md-4 filter-input" id="harian-inputs" style="display: <?= $type === 'harian' ? 'block' : 'none' ?>;">
            <label for="date" class="form-label text-secondary fw-semibold">Pilih Tanggal</label>
            <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>">
        </div>

        <!-- Month & Year Input (For Bulanan) -->
        <div class="col-md-3 filter-input" id="bulanan-month" style="display: <?= $type === 'bulanan' ? 'block' : 'none' ?>;">
            <label for="month" class="form-label text-secondary fw-semibold">Bulan</label>
            <select class="form-select" id="month" name="month">
                <?php foreach ($months as $m_num => $m_name): ?>
                    <option value="<?= $m_num ?>" <?= $month === $m_num ? 'selected' : '' ?>><?= $m_name ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2 filter-input" id="bulanan-year" style="display: <?= ($type === 'bulanan' || $type === 'tahunan') ? 'block' : 'none' ?>;">
            <label for="year" class="form-label text-secondary fw-semibold">Tahun</label>
            <select class="form-select" id="year" name="year">
                <?php 
                $curr_yr = (int)date('Y');
                for ($yr = $curr_yr - 5; $yr <= $curr_yr + 5; $yr++): 
                ?>
                    <option value="<?= $yr ?>" <?= $year == $yr ? 'selected' : '' ?>><?= $yr ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-search me-1"></i> Tampilkan
            </button>
        </div>
    </form>
</div>

<!-- Preview Table -->
<div class="card panel-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0">Preview Laporan (<?= ucfirst($type) ?>)</h5>
        <?php if ($type === 'harian'): ?>
            <span class="badge bg-secondary px-3 py-2">Tanggal: <?= date('d-m-Y', strtotime($date)) ?></span>
        <?php elseif ($type === 'bulanan'): ?>
            <span class="badge bg-secondary px-3 py-2">Periode: <?= $months[$month] ?> <?= $year ?></span>
        <?php else: ?>
            <span class="badge bg-secondary px-3 py-2">Periode: Tahun <?= $year ?></span>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th class="text-center">Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presensi_list)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Tidak ada data presensi yang ditemukan untuk kriteria ini.</td>
                    </tr>
                <?php else: $no = 1; foreach ($presensi_list as $p): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($p['nama_jabatan'] ?? 'Staf') ?></small>
                        </td>
                        <td><?= date('d-m-Y', strtotime($p['tanggal'])) ?></td>
                        <td><?= $p['jam_masuk'] ? date('H:i', strtotime($p['jam_masuk'])) : '-' ?></td>
                        <td><?= $p['jam_keluar'] ? date('H:i', strtotime($p['jam_keluar'])) : '-' ?></td>
                        <td class="text-center">
                            <?php if ($p['status'] === 'hadir'): ?>
                                <span class="badge badge-hadir px-2.5 py-1.5 rounded-pill">Hadir</span>
                            <?php elseif ($p['status'] === 'terlambat'): ?>
                                <span class="badge badge-terlambat px-2.5 py-1.5 rounded-pill">Terlambat</span>
                            <?php elseif ($p['status'] === 'alpha'): ?>
                                <span class="badge badge-alpha px-2.5 py-1.5 rounded-pill">Alpha</span>
                            <?php elseif ($p['status'] === 'sakit'): ?>
                                <span class="badge badge-sakit px-2.5 py-1.5 rounded-pill">Sakit</span>
                            <?php elseif ($p['status'] === 'izin'): ?>
                                <span class="badge badge-izin px-2.5 py-1.5 rounded-pill">Izin</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary"><?= htmlspecialchars($p['keterangan'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleFilterInputs(value) {
        // Hide all
        document.getElementById('harian-inputs').style.display = 'none';
        document.getElementById('bulanan-month').style.display = 'none';
        document.getElementById('bulanan-year').style.display = 'none';

        // Show specific
        if (value === 'harian') {
            document.getElementById('harian-inputs').style.display = 'block';
        } else if (value === 'bulanan') {
            document.getElementById('bulanan-month').style.display = 'block';
            document.getElementById('bulanan-year').style.display = 'block';
        } else if (value === 'tahunan') {
            document.getElementById('bulanan-year').style.display = 'block';
        }
    }
</script>

<?php require_once 'footer.php'; ?>
