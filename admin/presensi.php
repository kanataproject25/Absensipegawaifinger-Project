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
$query = "SELECT p.*, u.nama_lengkap, u.nip, u.user_id, j.nama_jabatan 
          FROM presensi p 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN jabatan j ON u.jabatan_id = j.id
          WHERE p.tanggal BETWEEN :start_date AND :end_date";

$params = [':start_date' => $filter_date_start, ':end_date' => $filter_date_end];

if (!empty($filter_user_id)) {
    $query .= " AND p.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}

$query .= " ORDER BY p.tanggal DESC, u.nama_lengkap ASC";

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

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Data Presensi Staf</h4>
        <p class="text-muted mb-0">Lihat dan kelola riwayat presensi harian staf desa.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualPresensiModal">
        <i class="bi bi-calendar-plus me-2"></i> Input Presensi Manual
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card card-custom py-3 px-4 mb-4">
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
                        <?= $member['user_id'] ? ' (ID: '.$member['user_id'].')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <a href="presensi.php" class="btn btn-outline-secondary w-100 py-2">Reset</a>
        </div>
    </form>
</div>

<!-- Table Panel -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
            <thead class="table-light">
                <tr>
                    <th style="width: 45px;">No</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal</th>
                    <th class="text-center" style="background: rgba(46,204,113,0.07);">
                        <i class="bi bi-sunrise me-1 text-success"></i>AM In
                    </th>
                    <th class="text-center" style="background: rgba(46,204,113,0.07);">AM Out</th>
                    <th class="text-center" style="background: rgba(52,152,219,0.07);">
                        <i class="bi bi-sunset me-1 text-primary"></i>PM In
                    </th>
                    <th class="text-center" style="background: rgba(52,152,219,0.07);">PM Out</th>
                    <th class="text-center">
                        <span class="text-danger">Late</span><br><small class="text-muted">(mnt)</small>
                    </th>
                    <th class="text-center">
                        <span class="text-warning">Early</span><br><small class="text-muted">(mnt)</small>
                    </th>
                    <th class="text-center">Status</th>
                    <th class="text-end" style="width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presensi_list)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x display-6 d-block mb-2 text-secondary"></i>
                            Tidak ada data presensi untuk filter ini.
                        </td>
                    </tr>
                <?php else: $no = 1; foreach ($presensi_list as $p): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($p['nama_jabatan'] ?? 'Staf') ?></small>
                            <?php if ($p['user_id']): ?>
                                <br><small class="text-primary"><i class="bi bi-fingerprint"></i> <?= htmlspecialchars($p['user_id']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d-m-Y', strtotime($p['tanggal'])) ?></td>
                        <!-- AM In -->
                        <td class="text-center" style="background: rgba(46,204,113,0.04);">
                            <?= !empty($p['am_in']) ? '<span class="fw-semibold text-success">'.date('H:i', strtotime($p['am_in'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- AM Out -->
                        <td class="text-center" style="background: rgba(46,204,113,0.04);">
                            <?= !empty($p['am_out']) ? date('H:i', strtotime($p['am_out'])) : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- PM In -->
                        <td class="text-center" style="background: rgba(52,152,219,0.04);">
                            <?= !empty($p['pm_in']) ? date('H:i', strtotime($p['pm_in'])) : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- PM Out -->
                        <td class="text-center" style="background: rgba(52,152,219,0.04);">
                            <?= !empty($p['pm_out']) ? '<span class="fw-semibold text-primary">'.date('H:i', strtotime($p['pm_out'])).'</span>' : '<span class="text-muted">-</span>' ?>
                        </td>
                        <!-- Late -->
                        <td class="text-center">
                            <?php if ((int)($p['late_minute'] ?? 0) > 0): ?>
                                <span class="badge bg-danger bg-opacity-15 text-danger px-2"><?= $p['late_minute'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <!-- Early -->
                        <td class="text-center">
                            <?php if ((int)($p['early_minute'] ?? 0) > 0): ?>
                                <span class="badge bg-warning bg-opacity-15 text-warning px-2"><?= $p['early_minute'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <!-- Status -->
                        <td class="text-center">
                            <?php
                            $s = $p['status'];
                            $badge_map = ['hadir'=>'badge-hadir','terlambat'=>'badge-terlambat','alpha'=>'badge-alpha','sakit'=>'badge-sakit','izin'=>'badge-izin'];
                            $bc = $badge_map[$s] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $bc ?> px-2 py-1 rounded-pill"><?= ucfirst($s) ?></span>
                        </td>
                        <!-- Actions -->
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                    data-bs-toggle="modal" data-bs-target="#editPresensiModal" 
                                    data-id="<?= $p['id'] ?>"
                                    data-userid="<?= $p['user_id'] ?>" 
                                    data-tanggal="<?= $p['tanggal'] ?>"
                                    data-amin="<?= $p['am_in']  ? substr($p['am_in'], 0, 5)  : '' ?>"
                                    data-amout="<?= $p['am_out'] ? substr($p['am_out'], 0, 5) : '' ?>"
                                    data-pmin="<?= $p['pm_in']  ? substr($p['pm_in'], 0, 5)  : '' ?>"
                                    data-pmout="<?= $p['pm_out'] ? substr($p['pm_out'], 0, 5) : '' ?>"
                                    data-status="<?= $p['status'] ?>"
                                    data-keterangan="<?= htmlspecialchars($p['keterangan'] ?? '') ?>">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#deletePresensiModal"
                                    data-id="<?= $p['id'] ?>"
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>"
                                    data-tanggal="<?= date('d-m-Y', strtotime($p['tanggal'])) ?>">
                                <i class="bi bi-trash3 me-1"></i> Hapus
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="manualPresensiModalLabel">Input Presensi Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="user_id" class="form-label text-secondary fw-semibold">Pilih Pegawai *</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($staff_members as $member): ?>
                                    <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['nama_lengkap']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label text-secondary fw-semibold">Tanggal *</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12"><hr class="my-1"><p class="small text-muted fw-semibold mb-1"><i class="bi bi-sunrise me-1 text-success"></i>Sesi Pagi (AM)</p></div>
                        <div class="col-md-6">
                            <label for="am_in" class="form-label text-secondary fw-semibold">AM In (Scan Masuk Pagi)</label>
                            <input type="time" class="form-control" id="am_in" name="am_in">
                        </div>
                        <div class="col-md-6">
                            <label for="am_out" class="form-label text-secondary fw-semibold">AM Out (Scan Keluar Pagi)</label>
                            <input type="time" class="form-control" id="am_out" name="am_out">
                        </div>
                        <div class="col-12"><hr class="my-1"><p class="small text-muted fw-semibold mb-1"><i class="bi bi-sunset me-1 text-primary"></i>Sesi Siang (PM)</p></div>
                        <div class="col-md-6">
                            <label for="pm_in" class="form-label text-secondary fw-semibold">PM In (Scan Masuk Siang)</label>
                            <input type="time" class="form-control" id="pm_in" name="pm_in">
                        </div>
                        <div class="col-md-6">
                            <label for="pm_out" class="form-label text-secondary fw-semibold">PM Out (Scan Keluar Sore)</label>
                            <input type="time" class="form-control" id="pm_out" name="pm_out">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label text-secondary fw-semibold">Status Kehadiran *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="hadir">Hadir</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="alpha">Alpha</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="keterangan" class="form-label text-secondary fw-semibold">Keterangan / Alasan</label>
                            <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Contoh: Sakit dengan surat dokter">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editPresensiModal" tabindex="-1" aria-labelledby="editPresensiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editPresensiModalLabel">Edit Presensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="tanggal" id="edit_tanggal">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Pegawai</label>
                            <input type="text" class="form-control bg-light" id="edit_nama_disabled" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Tanggal</label>
                            <input type="text" class="form-control bg-light" id="edit_tanggal_disabled" disabled>
                        </div>
                        <div class="col-12"><hr class="my-1"><p class="small text-muted fw-semibold mb-1"><i class="bi bi-sunrise me-1 text-success"></i>Sesi Pagi (AM)</p></div>
                        <div class="col-md-6">
                            <label for="edit_am_in" class="form-label text-secondary fw-semibold">AM In</label>
                            <input type="time" class="form-control" id="edit_am_in" name="am_in">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_am_out" class="form-label text-secondary fw-semibold">AM Out</label>
                            <input type="time" class="form-control" id="edit_am_out" name="am_out">
                        </div>
                        <div class="col-12"><hr class="my-1"><p class="small text-muted fw-semibold mb-1"><i class="bi bi-sunset me-1 text-primary"></i>Sesi Siang (PM)</p></div>
                        <div class="col-md-6">
                            <label for="edit_pm_in" class="form-label text-secondary fw-semibold">PM In</label>
                            <input type="time" class="form-control" id="edit_pm_in" name="pm_in">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_pm_out" class="form-label text-secondary fw-semibold">PM Out</label>
                            <input type="time" class="form-control" id="edit_pm_out" name="pm_out">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label text-secondary fw-semibold">Status Kehadiran *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="hadir">Hadir</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="alpha">Alpha</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_keterangan" class="form-label text-secondary fw-semibold">Keterangan</label>
                            <input type="text" class="form-control" id="edit_keterangan" name="keterangan">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deletePresensiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Hapus Data Presensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data presensi <strong id="delete_nama_label"></strong> pada tanggal <strong id="delete_tanggal_label"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const editModal = document.getElementById('editPresensiModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const row = btn.closest('tr');
        const nama = row.querySelector('.fw-semibold.text-dark').textContent.trim();

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

<?php require_once 'footer.php'; ?>
