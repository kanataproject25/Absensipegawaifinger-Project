<?php
require_once 'header.php';

$success = '';
$error   = '';

// Handle Manual Attendance Override/Create/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // CREATE/UPDATE PRESENSI MANUAL
        if ($action === 'save') {
            $user_id    = $_POST['user_id'];
            $tanggal    = $_POST['tanggal'];
            $am_in      = !empty($_POST['am_in'])  ? $_POST['am_in']  : null;
            $am_out     = !empty($_POST['am_out']) ? $_POST['am_out'] : null;
            $pm_in      = !empty($_POST['pm_in'])  ? $_POST['pm_in']  : null;
            $pm_out     = !empty($_POST['pm_out']) ? $_POST['pm_out'] : null;
            $status     = $_POST['status'];
            $keterangan = trim($_POST['keterangan']);

            // Derive jam_masuk / jam_keluar for backward compatibility
            $jam_masuk  = $am_in  ?: null;
            $jam_keluar = $pm_out ?: ($am_out ?: null);

            // Calculate late/early minutes based on shift
            $late_minute  = 0;
            $early_minute = 0;
            if ($am_in && $tanggal) {
                $stmt_shifts = $pdo->query("SELECT * FROM jam_kerja");
                $shifts = $stmt_shifts->fetchAll();
                $day_map = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
                $indo_day = $day_map[date('l', strtotime($tanggal))] ?? '';
                foreach ($shifts as $s) {
                    if (strpos($s['hari'], $indo_day) !== false) {
                        if (strtotime($am_in) > strtotime($s['jam_masuk'])) {
                            $late_minute = (int)floor((strtotime($am_in) - strtotime($s['jam_masuk'])) / 60);
                        }
                        if ($pm_out && strtotime($pm_out) < strtotime($s['jam_pulang'])) {
                            $early_minute = (int)floor((strtotime($s['jam_pulang']) - strtotime($pm_out)) / 60);
                        }
                        break;
                    }
                }
            }

            if (!empty($user_id) && !empty($tanggal) && !empty($status)) {
                try {
                    $stmt_check = $pdo->prepare("SELECT id FROM presensi WHERE user_id = ? AND tanggal = ?");
                    $stmt_check->execute([$user_id, $tanggal]);
                    $existing = $stmt_check->fetch();

                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE presensi SET jam_masuk=?, jam_keluar=?, am_in=?, am_out=?, pm_in=?, pm_out=?, late_minute=?, early_minute=?, status=?, keterangan=? WHERE id=?");
                        $stmt->execute([$jam_masuk, $jam_keluar, $am_in, $am_out, $pm_in, $pm_out, $late_minute, $early_minute, $status, $keterangan ?: null, $existing['id']]);
                        $success = "Data presensi berhasil diperbarui!";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO presensi (user_id, tanggal, jam_masuk, jam_keluar, am_in, am_out, pm_in, pm_out, late_minute, early_minute, status, keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->execute([$user_id, $tanggal, $jam_masuk, $jam_keluar, $am_in, $am_out, $pm_in, $pm_out, $late_minute, $early_minute, $status, $keterangan ?: null]);
                        $success = "Data presensi berhasil dicatat!";
                    }
                } catch (PDOException $e) {
                    $error = "Gagal mencatat presensi: " . $e->getMessage();
                }
            } else {
                $error = "Kolom Pegawai, Tanggal, dan Status wajib diisi.";
            }
        }

        // DELETE PRESENSI
        elseif ($action === 'delete') {
            $id = $_POST['id'];
            if (!empty($id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM presensi WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Data presensi berhasil dihapus!";
                } catch (PDOException $e) {
                    $error = "Gagal menghapus data: " . $e->getMessage();
                }
            }
        }
    }
}

// Filters
$filter_date_start = $_GET['date_start'] ?? date('Y-m-d');
$filter_date_end   = $_GET['date_end']   ?? date('Y-m-d');
$filter_user_id    = $_GET['user_id']    ?? '';

// Build Query
$query = "SELECT p.*, u.nama_lengkap, u.nip, u.user_id AS barcode_id, j.nama_jabatan 
          FROM presensi p 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN jabatan j ON u.jabatan_id = j.id
          WHERE p.tanggal BETWEEN :start_date AND :end_date";

$params = [':start_date' => $filter_date_start, ':end_date' => $filter_date_end];

if (!empty($filter_user_id)) {
    $query .= " AND p.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}

$query .= " ORDER BY u.nama_lengkap ASC, p.tanggal DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $presensi_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data presensi: " . $e->getMessage();
    $presensi_list = [];
}

// Staff dropdown
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT id, nama_lengkap, user_id FROM users WHERE role = 'staf' ORDER BY nama_lengkap ASC");
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data pegawai: " . $e->getMessage();
}
?>

<?php
// Pre-calculate statistics for the summary panel and individual employee groups
$stats = [
    'total' => 0,
    'hadir' => 0,
    'terlambat' => 0,
    'sakit_izin' => 0,
    'alpha' => 0
];

$employee_stats = [];

foreach ($presensi_list as $p) {
    $stats['total']++;
    $status = strtolower($p['status'] ?? '');
    if ($status === 'hadir') {
        $stats['hadir']++;
    } elseif ($status === 'terlambat') {
        $stats['terlambat']++;
    } elseif ($status === 'sakit' || $status === 'izin') {
        $stats['sakit_izin']++;
    } elseif ($status === 'alpha') {
        $stats['alpha']++;
    }

    $uid = $p['user_id'];
    if (!isset($employee_stats[$uid])) {
        $employee_stats[$uid] = [
            'hadir' => 0,
            'terlambat' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpha' => 0,
            'total' => 0
        ];
    }
    $employee_stats[$uid]['total']++;
    if (isset($employee_stats[$uid][$status])) {
        $employee_stats[$uid][$status]++;
    }
}
?>

<style>
    /* Styling overrides for premium feel */
    .btn-primary {
        background: linear-gradient(135deg, #1E3A5F 0%, #152943 100%);
        border: none;
        box-shadow: 0 4px 10px rgba(30, 58, 95, 0.15);
        transition: all 0.25s ease;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #254774 0%, #1c3557 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(30, 58, 95, 0.25);
    }
    .btn-outline-primary {
        color: #1E3A5F;
        border-color: #1E3A5F;
    }
    .btn-outline-primary:hover {
        background-color: #1E3A5F;
        border-color: #1E3A5F;
        color: #fff;
    }
    
    .card-stats {
        border-radius: 16px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        transition: all 0.25s ease;
        overflow: hidden;
        position: relative;
    }
    .card-stats:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    .card-stats::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    .card-stats.total::before { background-color: #6c757d; }
    .card-stats.hadir::before { background-color: #2ECC71; }
    .card-stats.terlambat::before { background-color: #E67E22; }
    .card-stats.sakit-izin::before { background-color: #8E44AD; }
    .card-stats.alpha::before { background-color: #E74C3C; }

    .card-stats .stat-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.5rem;
    }

    /* Custom Table Grouping style */
    .group-header {
        background-color: #fcfdfe !important;
        transition: background-color 0.2s ease;
        border-left: 4px solid #1E3A5F !important;
    }
    .group-header:hover {
        background-color: #f4f7fa !important;
    }
    .group-badge-container span {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-weight: 500;
    }

    /* Session headers */
    .session-am-th {
        background: rgba(46, 204, 113, 0.05) !important;
        border-bottom: 2px solid rgba(46, 204, 113, 0.2) !important;
    }
    .session-pm-th {
        background: rgba(52, 152, 219, 0.05) !important;
        border-bottom: 2px solid rgba(52, 152, 219, 0.2) !important;
    }

    /* Beautiful Modals */
    .modal-content-custom {
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border-radius: 20px;
        overflow: hidden;
    }
    .modal-header-custom {
        background: linear-gradient(135deg, #1E3A5F 0%, #112237 100%);
        color: #fff;
        border-bottom: none;
        padding: 1.5rem;
    }
    .modal-header-custom .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
    .modal-body-custom {
        padding: 2rem;
        background-color: #fdfdfd;
    }
    .modal-footer-custom {
        padding: 1.25rem 2rem;
        background-color: #f8f9fa;
        border-top: 1px solid #eee;
    }
    .modal-section-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.01);
    }
    .form-label-custom {
        font-size: 0.825rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #5a6a85;
        font-weight: 600;
    }
</style>

<!-- Page Header -->
<div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">Data Presensi Staf</h4>
        <p class="text-muted mb-0">Lihat, pantau, dan kelola riwayat presensi harian staf desa.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary px-4 py-2.5 rounded-3" data-bs-toggle="modal" data-bs-target="#manualPresensiModal">
            <i class="bi bi-calendar-plus me-2"></i> Input Presensi Manual
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-left: 4px solid #2ECC71 !important;">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-left: 4px solid #E74C3C !important;">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-4 me-3"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Real-time dynamic stats cards -->
<div class="row g-3 mb-4">
    <!-- Total Data -->
    <div class="col-6 col-lg-2.4 col-md-4">
        <div class="card card-stats total bg-white h-100">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-light text-secondary me-3">
                    <i class="bi bi-collection"></i>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase fw-semibold mb-0" style="font-size: 0.7rem;">Total Record</small>
                    <h4 class="fw-bold mb-0 text-dark"><?= $stats['total'] ?></h4>
                </div>
            </div>
        </div>
    </div>
    <!-- Hadir -->
    <div class="col-6 col-lg-2.4 col-md-4">
        <div class="card card-stats hadir bg-white h-100">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase fw-semibold mb-0" style="font-size: 0.7rem;">Hadir</small>
                    <h4 class="fw-bold mb-0 text-success"><?= $stats['hadir'] ?></h4>
                </div>
            </div>
        </div>
    </div>
    <!-- Terlambat -->
    <div class="col-6 col-lg-2.4 col-md-4">
        <div class="card card-stats terlambat bg-white h-100">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase fw-semibold mb-0" style="font-size: 0.7rem;">Terlambat</small>
                    <h4 class="fw-bold mb-0 text-warning"><?= $stats['terlambat'] ?></h4>
                </div>
            </div>
        </div>
    </div>
    <!-- Sakit & Izin -->
    <div class="col-6 col-lg-2.4 col-md-4">
        <div class="card card-stats sakit-izin bg-white h-100">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-envelope-open"></i>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase fw-semibold mb-0" style="font-size: 0.7rem;">Sakit/Izin</small>
                    <h4 class="fw-bold mb-0 text-info"><?= $stats['sakit_izin'] ?></h4>
                </div>
            </div>
        </div>
    </div>
    <!-- Alpha -->
    <div class="col-6 col-lg-2.4 col-md-4">
        <div class="card card-stats alpha bg-white h-100">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase fw-semibold mb-0" style="font-size: 0.7rem;">Alpha</small>
                    <h4 class="fw-bold mb-0 text-danger"><?= $stats['alpha'] ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card card-custom py-3.5 px-4 mb-4 border-0">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="date_start" class="form-label-custom">Tanggal Mulai</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-calendar-event"></i></span>
                <input type="date" class="form-control bg-light border-start-0 ps-0" id="date_start" name="date_start" value="<?= htmlspecialchars($filter_date_start) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <label for="date_end" class="form-label-custom">Tanggal Selesai</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-calendar-event"></i></span>
                <input type="date" class="form-control bg-light border-start-0 ps-0" id="date_end" name="date_end" value="<?= htmlspecialchars($filter_date_end) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <label for="filter_user" class="form-label-custom">Filter Pegawai</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-person"></i></span>
                <select class="form-select bg-light border-start-0 ps-0" id="filter_user" name="user_id">
                    <option value="">-- Semua Pegawai --</option>
                    <?php foreach ($staff_members as $member): ?>
                        <option value="<?= $member['id'] ?>" <?= $filter_user_id == $member['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($member['nama_lengkap']) ?>
                            <?= $member['user_id'] ? ' (ID: '.$member['user_id'].')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 py-2 rounded-3">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <a href="presensi.php" class="btn btn-outline-secondary w-100 py-2 rounded-3 d-flex align-items-center justify-content-center">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Panel Card -->
<div class="card card-custom p-0 overflow-hidden border-0">
    <div class="table-responsive">
        <table id="presensiTable" class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
            <thead>
                <tr class="table-light border-bottom">
                    <th class="ps-4" style="width: 55px;">No</th>
                    <th>Tanggal</th>
                    <th class="text-center session-am-th">
                        <i class="bi bi-sunrise me-1 text-success"></i>AM In
                    </th>
                    <th class="text-center session-am-th">AM Out</th>
                    <th class="text-center session-pm-th">
                        <i class="bi bi-sunset me-1 text-primary"></i>PM In
                    </th>
                    <th class="text-center session-pm-th">PM Out</th>
                    <th class="text-center">
                        <span class="text-danger fw-semibold">Late</span><br><small class="text-muted">(mnt)</small>
                    </th>
                    <th class="text-center">
                        <span class="text-warning fw-semibold">Early</span><br><small class="text-muted">(mnt)</small>
                    </th>
                    <th class="text-center">Status</th>
                    <th class="text-end pe-4" style="width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presensi_list)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x display-6 d-block mb-3 text-secondary opacity-50"></i>
                            <span class="d-block fw-semibold text-secondary">Tidak Ada Data Presensi</span>
                            <small class="text-muted">Gunakan form di atas untuk mengubah filter atau tambah data baru.</small>
                        </td>
                    </tr>
                <?php else: 
                    $no = 1; 
                    $current_name = '';
                    foreach ($presensi_list as $p): 
                        $show_name = ($p['nama_lengkap'] !== $current_name);
                        $current_name = $p['nama_lengkap'];
                ?>
                    <?php if ($show_name): ?>
                    <tr class="group-header" data-userid="<?= $p['user_id'] ?>" style="cursor: pointer;">
                        <td colspan="10" class="py-3 ps-4 pe-4 border-bottom">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 40px; height: 40px;">
                                        <i class="bi bi-person-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="mb-0 fw-bold text-dark fs-6"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                                        <div class="small text-muted d-flex align-items-center gap-2">
                                            <span><?= htmlspecialchars($p['nama_jabatan'] ?? 'Staf') ?></span>
                                            <?php if (!empty($p['barcode_id'])): ?>
                                                <span>&bull;</span>
                                                <span><i class="bi bi-fingerprint text-secondary"></i> ID: <?= htmlspecialchars($p['barcode_id']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <!-- Employee dynamic metrics in the group header -->
                                    <div class="group-badge-container d-none d-md-flex align-items-center gap-1.5 me-2">
                                        <span class="bg-success bg-opacity-10 text-success">Hadir: <?= $employee_stats[$p['user_id']]['hadir'] ?></span>
                                        <span class="bg-warning bg-opacity-10 text-warning">Terlambat: <?= $employee_stats[$p['user_id']]['terlambat'] ?></span>
                                        <span class="bg-info bg-opacity-10 text-info">Sakit/Izin: <?= $employee_stats[$p['user_id']]['sakit'] + $employee_stats[$p['user_id']]['izin'] ?></span>
                                        <span class="bg-danger bg-opacity-10 text-danger">Alpha: <?= $employee_stats[$p['user_id']]['alpha'] ?></span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border toggle-btn py-1 px-2.5 rounded-2 collapsed-group">
                                        <i class="bi bi-chevron-down me-1"></i> <span style="font-size: 0.8rem;">Buka</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="group-rows-<?= $p['user_id'] ?>" style="display: none;">
                        <td class="text-center text-muted ps-4"><?= $no++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= date('d M Y', strtotime($p['tanggal'])) ?></div>
                            <small class="text-muted"><?= date('l', strtotime($p['tanggal'])) ?></small>
                        </td>
                        <!-- AM In -->
                        <td class="text-center" style="background: rgba(46,204,113,0.015);">
                            <?= !empty($p['am_in']) ? '<span class="fw-bold text-success">'.date('H:i', strtotime($p['am_in'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- AM Out -->
                        <td class="text-center" style="background: rgba(46,204,113,0.015);">
                            <?= !empty($p['am_out']) ? '<span class="fw-semibold text-secondary">'.date('H:i', strtotime($p['am_out'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- PM In -->
                        <td class="text-center" style="background: rgba(52,152,219,0.015);">
                            <?= !empty($p['pm_in']) ? '<span class="fw-semibold text-secondary">'.date('H:i', strtotime($p['pm_in'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- PM Out -->
                        <td class="text-center" style="background: rgba(52,152,219,0.015);">
                            <?= !empty($p['pm_out']) ? '<span class="fw-bold text-primary">'.date('H:i', strtotime($p['pm_out'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- Late -->
                        <td class="text-center">
                            <?php if ((int)($p['late_minute'] ?? 0) > 0): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded px-2" style="font-size: 0.75rem; font-weight: 600;"><i class="bi bi-clock me-1"></i><?= $p['late_minute'] ?></span>
                            <?php else: ?>
                                <span class="text-muted opacity-50">-</span>
                            <?php endif; ?>
                        </td>
                        <!-- Early -->
                        <td class="text-center">
                            <?php if ((int)($p['early_minute'] ?? 0) > 0): ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning rounded px-2" style="font-size: 0.75rem; font-weight: 600;"><i class="bi bi-box-arrow-left me-1"></i><?= $p['early_minute'] ?></span>
                            <?php else: ?>
                                <span class="text-muted opacity-50">-</span>
                            <?php endif; ?>
                        </td>
                        <!-- Status -->
                        <td class="text-center">
                            <?php
                            $s = strtolower($p['status']);
                            $badge_map = [
                                'hadir' => 'badge-hadir',
                                'terlambat' => 'badge-terlambat',
                                'alpha' => 'badge-alpha',
                                'sakit' => 'badge-sakit',
                                'izin' => 'badge-izin'
                            ];
                            $bc = $badge_map[$s] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $bc ?> px-2.5 py-1.5 rounded-pill fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.3px;"><?= $s ?></span>
                        </td>
                        <!-- Actions -->
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 py-1 rounded-2" 
                                    data-bs-toggle="modal" data-bs-target="#editPresensiModal" 
                                    data-id="<?= $p['id'] ?>"
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>"
                                    data-userid="<?= $p['user_id'] ?>" 
                                    data-tanggal="<?= $p['tanggal'] ?>"
                                    data-amin="<?= $p['am_in']  ? substr($p['am_in'], 0, 5)  : '' ?>"
                                    data-amout="<?= $p['am_out'] ? substr($p['am_out'], 0, 5) : '' ?>"
                                    data-pmin="<?= $p['pm_in']  ? substr($p['pm_in'], 0, 5)  : '' ?>"
                                    data-pmout="<?= $p['pm_out'] ? substr($p['pm_out'], 0, 5) : '' ?>"
                                    data-status="<?= $p['status'] ?>"
                                    data-keterangan="<?= htmlspecialchars($p['keterangan'] ?? '') ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-1 rounded-2"
                                    data-bs-toggle="modal" data-bs-target="#deletePresensiModal"
                                    data-id="<?= $p['id'] ?>"
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>"
                                    data-tanggal="<?= date('d-m-Y', strtotime($p['tanggal'])) ?>">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Manual / Add Modal -->
<div class="modal fade" id="manualPresensiModal" tabindex="-1" aria-labelledby="manualPresensiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 text-white rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="bi bi-calendar-plus fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold" id="manualPresensiModalLabel">Input Presensi Manual</h5>
                        <p class="mb-0 text-white-50 small">Masukkan data kehadiran staf secara manual.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <div class="modal-body modal-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="user_id" class="form-label-custom">Pilih Pegawai <span class="text-danger">*</span></label>
                            <select class="form-select bg-light border-0 py-2.5 rounded-3" id="user_id" name="user_id" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($staff_members as $member): ?>
                                    <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['nama_lengkap']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label-custom">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control bg-light border-0 py-2.5 rounded-3" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <!-- Pagi Session AM -->
                        <div class="col-12 mt-4">
                            <div class="modal-section-card">
                                <div class="d-flex align-items-center mb-3 text-success">
                                    <i class="bi bi-sunrise-fill me-2 fs-5"></i>
                                    <h6 class="mb-0 fw-bold">Sesi Pagi (AM)</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="am_in" class="form-label-custom">AM In (Scan Masuk)</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="am_in" name="am_in">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="am_out" class="form-label-custom">AM Out (Scan Keluar)</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="am_out" name="am_out">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Siang Session PM -->
                        <div class="col-12">
                            <div class="modal-section-card">
                                <div class="d-flex align-items-center mb-3 text-primary">
                                    <i class="bi bi-sunset-fill me-2 fs-5"></i>
                                    <h6 class="mb-0 fw-bold">Sesi Siang (PM)</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="pm_in" class="form-label-custom">PM In (Scan Masuk)</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="pm_in" name="pm_in">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pm_out" class="form-label-custom">PM Out (Scan Keluar Sore)</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="pm_out" name="pm_out">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label-custom">Status Kehadiran <span class="text-danger">*</span></label>
                            <select class="form-select bg-light border-0 py-2.5 rounded-3" id="status" name="status" required>
                                <option value="hadir">Hadir</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="alpha">Alpha</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="keterangan" class="form-label-custom">Keterangan / Alasan</label>
                            <input type="text" class="form-control bg-light border-0 py-2.5 rounded-3" id="keterangan" name="keterangan" placeholder="Contoh: Izin urusan dinas">
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-4 py-2 rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editPresensiModal" tabindex="-1" aria-labelledby="editPresensiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 text-white rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="bi bi-pencil-square fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold" id="editPresensiModalLabel">Edit Presensi</h5>
                        <p class="mb-0 text-white-50 small">Perbarui data absensi karyawan terpilih.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="tanggal" id="edit_tanggal">
                <div class="modal-body modal-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Pegawai</label>
                            <input type="text" class="form-control bg-light border-0 py-2.5 rounded-3 fw-semibold text-dark" id="edit_nama_disabled" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Tanggal</label>
                            <input type="text" class="form-control bg-light border-0 py-2.5 rounded-3 fw-semibold text-dark" id="edit_tanggal_disabled" disabled>
                        </div>
                        
                        <!-- Pagi Session AM -->
                        <div class="col-12 mt-4">
                            <div class="modal-section-card">
                                <div class="d-flex align-items-center mb-3 text-success">
                                    <i class="bi bi-sunrise-fill me-2 fs-5"></i>
                                    <h6 class="mb-0 fw-bold">Sesi Pagi (AM)</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="edit_am_in" class="form-label-custom">AM In</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="edit_am_in" name="am_in">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_am_out" class="form-label-custom">AM Out</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="edit_am_out" name="am_out">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Siang Session PM -->
                        <div class="col-12">
                            <div class="modal-section-card">
                                <div class="d-flex align-items-center mb-3 text-primary">
                                    <i class="bi bi-sunset-fill me-2 fs-5"></i>
                                    <h6 class="mb-0 fw-bold">Sesi Siang (PM)</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="edit_pm_in" class="form-label-custom">PM In</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="edit_pm_in" name="pm_in">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_pm_out" class="form-label-custom">PM Out</label>
                                        <input type="time" class="form-control bg-light border-0 py-2 rounded-3" id="edit_pm_out" name="pm_out">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="edit_status" class="form-label-custom">Status Kehadiran <span class="text-danger">*</span></label>
                            <select class="form-select bg-light border-0 py-2.5 rounded-3" id="edit_status" name="status" required>
                                <option value="hadir">Hadir</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="alpha">Alpha</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_keterangan" class="form-label-custom">Keterangan</label>
                            <input type="text" class="form-control bg-light border-0 py-2.5 rounded-3" id="edit_keterangan" name="keterangan">
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-4 py-2 rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deletePresensiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header bg-danger text-white border-bottom-0 p-4">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 text-white rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="bi bi-trash3 fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Hapus Data Presensi</h5>
                        <p class="mb-0 text-white-50 small">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body modal-body-custom text-center py-4">
                    <i class="bi bi-exclamation-octagon text-danger display-4 mb-3 d-block"></i>
                    <h5>Apakah Anda yakin ingin menghapus data ini?</h5>
                    <p class="text-muted">Data presensi untuk <strong id="delete_nama_label" class="text-dark"></strong> pada tanggal <strong id="delete_tanggal_label" class="text-dark"></strong> akan dihapus secara permanen dari sistem.</p>
                </div>
                <div class="modal-footer modal-footer-custom d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-4 py-2 rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4 py-2 rounded-3">Ya, Hapus Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const editModal = document.getElementById('editPresensiModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const nama = btn.getAttribute('data-nama');

        document.getElementById('edit_user_id').value         = btn.getAttribute('data-userid');
        document.getElementById('edit_tanggal').value         = btn.getAttribute('data-tanggal');
        document.getElementById('edit_nama_disabled').value   = nama;
        document.getElementById('edit_tanggal_disabled').value = btn.getAttribute('data-tanggal');
        document.getElementById('edit_am_in').value           = btn.getAttribute('data-amin');
        document.getElementById('edit_am_out').value          = btn.getAttribute('data-amout');
        document.getElementById('edit_pm_in').value           = btn.getAttribute('data-pmin');
        document.getElementById('edit_pm_out').value          = btn.getAttribute('data-pmout');
        document.getElementById('edit_status').value          = btn.getAttribute('data-status');
        document.getElementById('edit_keterangan').value      = btn.getAttribute('data-keterangan');
    });

    const deleteModal = document.getElementById('deletePresensiModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('delete_id').value              = btn.getAttribute('data-id');
        document.getElementById('delete_nama_label').textContent = btn.getAttribute('data-nama');
        document.getElementById('delete_tanggal_label').textContent = btn.getAttribute('data-tanggal');
    });
</script>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Toggle rows functionality
        $('#presensiTable').on('click', '.group-header', function() {
            const userId = $(this).attr('data-userid');
            const rows = $('.group-rows-' + userId);
            const btn = $(this).find('.toggle-btn');
            const span = btn.find('span');
            const icon = btn.find('i');
            
            rows.toggle();
            
            if (rows.is(':hidden')) {
                btn.addClass('collapsed-group');
                icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                span.text('Buka');
            } else {
                btn.removeClass('collapsed-group');
                icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                span.text('Tutup');
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>
