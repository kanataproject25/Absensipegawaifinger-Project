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

$grouped_employees = [];

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
    
    $uid = $p['user_id'];
    if (!isset($grouped_employees[$uid])) {
        $grouped_employees[$uid] = [
            'nama_lengkap' => $p['nama_lengkap'],
            'nip' => $p['nip'] ?? '-',
            'nama_jabatan' => $p['nama_jabatan'] ?? 'Staf',
            'hadir' => 0,
            'terlambat' => 0,
            'alpha' => 0,
            'sakit' => 0,
            'izin' => 0,
            'late_minute' => 0,
            'early_minute' => 0,
            'records' => []
        ];
    }
    switch ($p['status']) {
        case 'hadir':     $grouped_employees[$uid]['hadir']++;     break;
        case 'terlambat': $grouped_employees[$uid]['terlambat']++; break;
        case 'alpha':     $grouped_employees[$uid]['alpha']++;     break;
        case 'sakit':     $grouped_employees[$uid]['sakit']++;     break;
        case 'izin':      $grouped_employees[$uid]['izin']++;      break;
    }
    $grouped_employees[$uid]['late_minute']  += (int)($p['late_minute']  ?? 0);
    $grouped_employees[$uid]['early_minute'] += (int)($p['early_minute'] ?? 0);
    $grouped_employees[$uid]['records'][] = $p;
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
                    <th>Jabatan</th>
                    <th class="text-center">Hadir</th>
                    <th class="text-center">Terlambat</th>
                    <th class="text-center">Alpha</th>
                    <th class="text-center">Sakit</th>
                    <th class="text-center">Izin</th>
                    <th class="text-center">Late (m)</th>
                    <th class="text-center">Early (m)</th>
                    <th class="text-center" style="width:120px;">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grouped_employees)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x display-6 d-block mb-2 text-secondary"></i>
                            Tidak ada data presensi untuk periode <?= $months[$filter_month] ?> <?= $filter_year ?>.
                        </td>
                    </tr>
                <?php else: $no = 1; foreach ($grouped_employees as $uid => $emp): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($emp['nama_lengkap']) ?></div>
                            <small class="text-muted">NIP: <?= htmlspecialchars($emp['nip']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($emp['nama_jabatan']) ?></td>
                        <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success px-2"><?= $emp['hadir'] ?></span></td>
                        <td class="text-center"><span class="badge bg-warning bg-opacity-10 text-warning px-2"><?= $emp['terlambat'] ?></span></td>
                        <td class="text-center"><span class="badge bg-danger bg-opacity-10 text-danger px-2"><?= $emp['alpha'] ?></span></td>
                        <td class="text-center"><span class="badge bg-info bg-opacity-10 text-info px-2"><?= $emp['sakit'] ?></span></td>
                        <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary px-2"><?= $emp['izin'] ?></span></td>
                        <td class="text-center"><?= $emp['late_minute'] > 0 ? '<span class="text-danger fw-semibold">'.$emp['late_minute'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="text-center"><?= $emp['early_minute'] > 0 ? '<span class="text-warning fw-semibold">'.$emp['early_minute'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#detail-<?= $uid ?>" aria-expanded="false" aria-controls="detail-<?= $uid ?>">
                                <i class="bi bi-chevron-down me-1"></i> Detail
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="11" class="p-0 border-0">
                            <div class="collapse" id="detail-<?= $uid ?>">
                                <div class="p-3 bg-light rounded-3 m-2 border">
                                    <h6 class="fw-bold mb-3 text-secondary"><i class="bi bi-calendar3 me-2"></i>Detail Harian: <?= htmlspecialchars($emp['nama_lengkap']) ?></h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-hover align-middle bg-white mb-0" style="font-size:0.8rem;">
                                            <thead class="table-secondary">
                                                <tr class="text-center">
                                                    <th>Tanggal</th>
                                                    <th>AM In</th>
                                                    <th>AM Out</th>
                                                    <th>PM In</th>
                                                    <th>PM Out</th>
                                                    <th>Late (m)</th>
                                                    <th>Early (m)</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($emp['records'] as $r): ?>
                                                    <tr>
                                                        <td class="text-center fw-semibold">
                                                            <?= date('d M Y', strtotime($r['tanggal'])) ?> 
                                                            <br><small class="text-muted">(<?= date('l', strtotime($r['tanggal'])) ?>)</small>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= !empty($r['am_in'])  ? '<span class="text-success fw-semibold">'.date('H:i', strtotime($r['am_in'])).'</span>'  : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= !empty($r['am_out']) ? date('H:i', strtotime($r['am_out'])) : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= !empty($r['pm_in'])  ? date('H:i', strtotime($r['pm_in']))  : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= !empty($r['pm_out']) ? '<span class="text-primary fw-semibold">'.date('H:i', strtotime($r['pm_out'])).'</span>' : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php $lm = (int)($r['late_minute'] ?? 0); ?>
                                                            <?= $lm > 0 ? '<span class="badge bg-danger bg-opacity-15 text-danger px-2">'.$lm.'</span>' : '<span class="text-muted">0</span>' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php $em = (int)($r['early_minute'] ?? 0); ?>
                                                            <?= $em > 0 ? '<span class="badge bg-warning bg-opacity-15 text-warning px-2">'.$em.'</span>' : '<span class="text-muted">0</span>' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php
                                                            $badge_map = ['hadir'=>'badge-hadir','terlambat'=>'badge-terlambat','alpha'=>'badge-alpha','sakit'=>'badge-sakit','izin'=>'badge-izin'];
                                                            $bc = $badge_map[$r['status']] ?? 'bg-secondary';
                                                            ?>
                                                            <span class="badge <?= $bc ?> px-2 py-1 rounded-pill"><?= ucfirst($r['status']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
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
