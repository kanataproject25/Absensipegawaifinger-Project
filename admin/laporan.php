<?php
require_once 'header.php';

$filter_month = $_GET['month'] ?? date('m');
$filter_year  = $_GET['year']  ?? date('Y');
$filter_user_id = $_GET['user_id'] ?? '';

$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',    '04' => 'April',
    '05' => 'Mei',     '06' => 'Juni',     '07' => 'Juli',     '08' => 'Agustus',
    '09' => 'September','10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Build query
$query = "SELECT p.*, u.nama_lengkap, u.nip, u.user_id, j.nama_jabatan 
          FROM presensi p 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN jabatan j ON u.jabatan_id = j.id
          WHERE MONTH(p.tanggal) = :month AND YEAR(p.tanggal) = :year";

$params = [':month' => $filter_month, ':year' => $filter_year];

if (!empty($filter_user_id)) {
    $query .= " AND p.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}

$query .= " ORDER BY p.tanggal ASC, u.nama_lengkap ASC";

$presensi_list = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $presensi_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $presensi_list = [];
}

// Summary Rekap
$rekap_hadir     = 0;
$rekap_terlambat = 0;
$rekap_alpha     = 0;
$rekap_sakit     = 0;
$rekap_izin      = 0;
$total_late_min  = 0;
$total_early_min = 0;

foreach ($presensi_list as $p) {
    switch ($p['status']) {
        case 'hadir':     $rekap_hadir++;     break;
        case 'terlambat': $rekap_terlambat++; break;
        case 'alpha':     $rekap_alpha++;     break;
        case 'sakit':     $rekap_sakit++;     break;
        case 'izin':      $rekap_izin++;      break;
    }
    $total_late_min  += (int)($p['late_minute']  ?? 0);
    $total_early_min += (int)($p['early_minute'] ?? 0);
}

// Staff dropdown
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role = 'staf' ORDER BY nama_lengkap ASC");
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Laporan Presensi</h4>
        <p class="text-muted mb-0">Rekap dan cetak laporan presensi pegawai per bulan.</p>
    </div>
    <?php if (!empty($presensi_list)): ?>
        <a href="cetak_pdf.php?month=<?= $filter_month ?>&year=<?= $filter_year ?>&user_id=<?= $filter_user_id ?>" 
           target="_blank" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf me-2"></i> Cetak PDF
        </a>
    <?php endif; ?>
</div>

<!-- Filter Panel -->
<div class="card card-custom py-3 px-4 mb-4">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="month" class="form-label text-secondary fw-semibold">Bulan</label>
            <select class="form-select" id="month" name="month">
                <?php foreach ($months as $m_num => $m_name): ?>
                    <option value="<?= $m_num ?>" <?= $filter_month === $m_num ? 'selected' : '' ?>><?= $m_name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="year" class="form-label text-secondary fw-semibold">Tahun</label>
            <select class="form-select" id="year" name="year">
                <?php for ($yr = (int)date('Y') - 3; $yr <= (int)date('Y') + 1; $yr++): ?>
                    <option value="<?= $yr ?>" <?= $filter_year == $yr ? 'selected' : '' ?>><?= $yr ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_user" class="form-label text-secondary fw-semibold">Pegawai</label>
            <select class="form-select" id="filter_user" name="user_id">
                <option value="">-- Semua Pegawai --</option>
                <?php foreach ($staff_members as $member): ?>
                    <option value="<?= $member['id'] ?>" <?= $filter_user_id == $member['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($member['nama_lengkap']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-search me-1"></i> Tampilkan
            </button>
        </div>
        <div class="col-md-2">
            <a href="laporan.php" class="btn btn-outline-secondary w-100 py-2">Reset</a>
        </div>
    </form>
</div>

<!-- Rekap Summary Cards -->
<?php if (!empty($presensi_list)): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="text-center p-3 rounded-3" style="background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);">
            <div class="fw-bold fs-2" style="color:#27AE60;"><?= $rekap_hadir ?></div>
            <div class="small text-muted fw-semibold">Hadir</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="text-center p-3 rounded-3" style="background: rgba(230,126,34,0.1); border: 1px solid rgba(230,126,34,0.25);">
            <div class="fw-bold fs-2" style="color:#D35400;"><?= $rekap_terlambat ?></div>
            <div class="small text-muted fw-semibold">Terlambat</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="text-center p-3 rounded-3" style="background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.25);">
            <div class="fw-bold fs-2" style="color:#C0392B;"><?= $rekap_alpha ?></div>
            <div class="small text-muted fw-semibold">Alpha</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="text-center p-3 rounded-3" style="background: rgba(52,152,219,0.1); border: 1px solid rgba(52,152,219,0.25);">
            <div class="fw-bold fs-2" style="color:#2980B9;"><?= $rekap_sakit ?></div>
            <div class="small text-muted fw-semibold">Sakit</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="text-center p-3 rounded-3" style="background: rgba(155,89,182,0.1); border: 1px solid rgba(155,89,182,0.25);">
            <div class="fw-bold fs-2" style="color:#8E44AD;"><?= $rekap_izin ?></div>
            <div class="small text-muted fw-semibold">Izin</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="text-center p-3 rounded-3" style="background: rgba(30,58,95,0.06); border: 1px solid rgba(30,58,95,0.1);">
            <div class="fw-bold fs-2 text-primary"><?= count($presensi_list) ?></div>
            <div class="small text-muted fw-semibold">Total Record</div>
        </div>
    </div>
</div>

<!-- Additional Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card card-custom py-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" 
                     style="width:48px;height:48px;background:rgba(231,76,60,0.12);">
                    <i class="bi bi-clock-history text-danger fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Akumulasi Keterlambatan</div>
                    <div class="fw-bold fs-5 text-danger"><?= $total_late_min ?> <small class="fw-normal text-muted">menit</small>
                        <?php if ($total_late_min >= 60): ?>
                            <small class="text-muted fw-normal">(≈ <?= round($total_late_min/60, 1) ?> jam)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-custom py-3">
            <div class="d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" 
                     style="width:48px;height:48px;background:rgba(230,126,34,0.12);">
                    <i class="bi bi-box-arrow-right text-warning fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Akumulasi Pulang Cepat</div>
                    <div class="fw-bold fs-5 text-warning"><?= $total_early_min ?> <small class="fw-normal text-muted">menit</small>
                        <?php if ($total_early_min >= 60): ?>
                            <small class="text-muted fw-normal">(≈ <?= round($total_early_min/60, 1) ?> jam)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Data Table -->
<div class="card card-custom">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0">
            Detail Presensi — <?= $months[$filter_month] ?> <?= $filter_year ?>
            <?php if ($filter_user_id): ?>
                <?php foreach($staff_members as $sm): if ($sm['id'] == $filter_user_id): ?>
                    <small class="text-muted fw-normal">(<?= htmlspecialchars($sm['nama_lengkap']) ?>)</small>
                <?php endif; endforeach; ?>
            <?php endif; ?>
        </h5>
        <span class="badge bg-secondary px-3 py-2"><?= count($presensi_list) ?> record</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
            <thead class="table-light">
                <tr>
                    <th style="width:45px;">No</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal</th>
                    <th class="text-center" style="background:rgba(46,204,113,0.07);">AM In</th>
                    <th class="text-center" style="background:rgba(46,204,113,0.07);">AM Out</th>
                    <th class="text-center" style="background:rgba(52,152,219,0.07);">PM In</th>
                    <th class="text-center" style="background:rgba(52,152,219,0.07);">PM Out</th>
                    <th class="text-center"><span class="text-danger">Late</span><br><small>(mnt)</small></th>
                    <th class="text-center"><span class="text-warning">Early</span><br><small>(mnt)</small></th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presensi_list)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x display-6 d-block mb-2 text-secondary"></i>
                            Tidak ada data presensi untuk periode <?= $months[$filter_month] ?> <?= $filter_year ?>.
                        </td>
                    </tr>
                <?php else: $no = 1; foreach ($presensi_list as $p): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($p['nama_jabatan'] ?? 'Staf') ?></small>
                        </td>
                        <td>
                            <?= date('d M Y', strtotime($p['tanggal'])) ?>
                            <br><small class="text-muted"><?= date('l', strtotime($p['tanggal'])) ?></small>
                        </td>
                        <td class="text-center" style="background:rgba(46,204,113,0.04);">
                            <?= !empty($p['am_in'])  ? '<span class="text-success fw-semibold">'.date('H:i', strtotime($p['am_in'])).'</span>'  : '<span class="text-muted">-</span>' ?>
                        </td>
                        <td class="text-center" style="background:rgba(46,204,113,0.04);">
                            <?= !empty($p['am_out']) ? date('H:i', strtotime($p['am_out'])) : '<span class="text-muted">-</span>' ?>
                        </td>
                        <td class="text-center" style="background:rgba(52,152,219,0.04);">
                            <?= !empty($p['pm_in'])  ? date('H:i', strtotime($p['pm_in']))  : '<span class="text-muted">-</span>' ?>
                        </td>
                        <td class="text-center" style="background:rgba(52,152,219,0.04);">
                            <?= !empty($p['pm_out']) ? '<span class="text-primary fw-semibold">'.date('H:i', strtotime($p['pm_out'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php $lm = (int)($p['late_minute'] ?? 0); ?>
                            <?= $lm > 0 ? '<span class="badge bg-danger bg-opacity-15 text-danger px-2">'.$lm.'</span>' : '<span class="text-muted">0</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php $em = (int)($p['early_minute'] ?? 0); ?>
                            <?= $em > 0 ? '<span class="badge bg-warning bg-opacity-15 text-warning px-2">'.$em.'</span>' : '<span class="text-muted">0</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $badge_map = ['hadir'=>'badge-hadir','terlambat'=>'badge-terlambat','alpha'=>'badge-alpha','sakit'=>'badge-sakit','izin'=>'badge-izin'];
                            $bc = $badge_map[$p['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $bc ?> px-2 py-1 rounded-pill"><?= ucfirst($p['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($presensi_list)): ?>
    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
        <a href="cetak_pdf.php?month=<?= $filter_month ?>&year=<?= $filter_year ?>&user_id=<?= $filter_user_id ?>" 
           target="_blank" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf me-2"></i> Cetak PDF Laporan Ini
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
