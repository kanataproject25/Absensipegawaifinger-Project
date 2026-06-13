<?php
require_once 'header.php';

$error = '';

// Filters setup
$filter_date_start = $_GET['date_start'] ?? date('Y-m-d');
$filter_date_end = $_GET['date_end'] ?? date('Y-m-d');
$filter_user_id = $_GET['user_id'] ?? '';

// Build Query with Filters
$query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
          FROM presensi p 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN jabatan j ON u.jabatan_id = j.id
          WHERE p.tanggal BETWEEN :start_date AND :end_date";

$params = [
    ':start_date' => $filter_date_start,
    ':end_date' => $filter_date_end
];

if (!empty($filter_user_id)) {
    $query .= " AND p.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}

$query .= " ORDER BY p.tanggal DESC, p.jam_masuk DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $presensi_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data presensi: " . $e->getMessage();
    $presensi_list = [];
}

// Group records by employee
$employees = [];
foreach ($presensi_list as $p) {
    $uid = $p['user_id'];
    if (!isset($employees[$uid])) {
        $employees[$uid] = [
            'nama_lengkap' => $p['nama_lengkap'],
            'nama_jabatan' => $p['nama_jabatan'] ?? 'Staf',
            'hadir'        => 0,
            'terlambat'    => 0,
            'alpha'        => 0,
            'sakit'        => 0,
            'izin'         => 0,
            'logs'         => []
        ];
    }
    $employees[$uid]['logs'][] = $p;
    switch ($p['status']) {
        case 'hadir':     $employees[$uid]['hadir']++;     break;
        case 'terlambat': $employees[$uid]['terlambat']++; break;
        case 'alpha':     $employees[$uid]['alpha']++;     break;
        case 'sakit':     $employees[$uid]['sakit']++;     break;
        case 'izin':      $employees[$uid]['izin']++;      break;
    }
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Monitoring Presensi</h4>
        <p class="text-muted mb-0">Tinjau ringkasan presensi staf kantor desa secara real-time berdasarkan filter tanggal.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filters Card -->
<div class="card panel-card py-3 px-4 mb-4">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="date_start" class="form-label text-secondary fw-semibold">Tanggal Mulai</label>
            <input type="date" class="form-control" id="date_start" name="date_start" value="<?= htmlspecialchars($filter_date_start) ?>">
        </div>
        <div class="col-md-3">
            <label for="date_end" class="form-label text-secondary fw-semibold">Tanggal Selesai</label>
            <input type="date" class="form-control" id="date_end" name="date_end" value="<?= htmlspecialchars($filter_date_end) ?>">
        </div>
        <div class="col-md-3">
            <label for="filter_user" class="form-label text-secondary fw-semibold">Nama Staf</label>
            <select class="form-select" id="filter_user" name="user_id">
                <option value="">-- Semua Pegawai --</option>
                <?php foreach ($staff_members as $member): ?>
                    <option value="<?= $member['id'] ?>" <?= $filter_user_id == $member['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($member['nama_lengkap']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <a href="monitoring.php" class="btn btn-outline-secondary w-100 py-2">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Panel -->
<div class="card panel-card p-4">
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Pegawai</th>
                    <th class="text-center">Hadir</th>
                    <th class="text-center">Terlambat</th>
                    <th class="text-center">Sakit</th>
                    <th class="text-center">Izin</th>
                    <th class="text-center">Alpha</th>
                    <th class="text-center" style="width: 120px;">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Tidak ada data presensi untuk filter ini.</td>
                    </tr>
                <?php else: $no = 1; foreach ($employees as $uid => $emp): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($emp['nama_lengkap']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($emp['nama_jabatan']) ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-hadir px-2.5 py-1.5 rounded-pill"><?= $emp['hadir'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-terlambat px-2.5 py-1.5 rounded-pill"><?= $emp['terlambat'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-sakit px-2.5 py-1.5 rounded-pill"><?= $emp['sakit'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-izin px-2.5 py-1.5 rounded-pill"><?= $emp['izin'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-alpha px-2.5 py-1.5 rounded-pill"><?= $emp['alpha'] ?></span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#detail-<?= $uid ?>" aria-expanded="false">
                                <i class="bi bi-eye me-1"></i> Lihat Log
                            </button>
                        </td>
                    </tr>
                    <tr class="collapse" id="detail-<?= $uid ?>">
                        <td colspan="8" class="p-3 bg-light">
                            <div class="card p-3 shadow-sm border-0">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-1"></i> Rincian Harian: <?= htmlspecialchars($emp['nama_lengkap']) ?></h6>
                                <table class="table table-bordered table-sm mb-0" style="font-size:0.875rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jam Masuk</th>
                                            <th>Jam Pulang</th>
                                            <th class="text-center">Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emp['logs'] as $log): ?>
                                            <tr>
                                                <td><?= date('d-m-Y', strtotime($log['tanggal'])) ?></td>
                                                <td><?= $log['jam_masuk'] ? date('H:i', strtotime($log['jam_masuk'])) : '-' ?></td>
                                                <td><?= $log['jam_keluar'] ? date('H:i', strtotime($log['jam_keluar'])) : '-' ?></td>
                                                <td class="text-center">
                                                    <?php if ($log['status'] === 'hadir'): ?>
                                                        <span class="badge badge-hadir px-2.5 py-1.5 rounded-pill">Hadir</span>
                                                    <?php elseif ($log['status'] === 'terlambat'): ?>
                                                        <span class="badge badge-terlambat px-2.5 py-1.5 rounded-pill">Terlambat</span>
                                                    <?php elseif ($log['status'] === 'alpha'): ?>
                                                        <span class="badge badge-alpha px-2.5 py-1.5 rounded-pill">Alpha</span>
                                                    <?php elseif ($log['status'] === 'sakit'): ?>
                                                        <span class="badge badge-sakit px-2.5 py-1.5 rounded-pill">Sakit</span>
                                                    <?php elseif ($log['status'] === 'izin'): ?>
                                                        <span class="badge badge-izin px-2.5 py-1.5 rounded-pill">Izin</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($log['keterangan'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
