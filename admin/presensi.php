<?php
require_once 'header.php';

$success = '';
$error = '';

// Handle Manual Attendance Override/Create/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // CREATE/UPDATE PRESENSI MANUAL
        if ($action === 'save') {
            $user_id = $_POST['user_id'];
            $tanggal = $_POST['tanggal'];
            $jam_masuk = !empty($_POST['jam_masuk']) ? $_POST['jam_masuk'] : null;
            $jam_keluar = !empty($_POST['jam_keluar']) ? $_POST['jam_keluar'] : null;
            $status = $_POST['status'];
            $keterangan = trim($_POST['keterangan']);

            if (!empty($user_id) && !empty($tanggal) && !empty($status)) {
                try {
                    // Check if entry already exists
                    $stmt_check = $pdo->prepare("SELECT id FROM presensi WHERE user_id = ? AND tanggal = ?");
                    $stmt_check->execute([$user_id, $tanggal]);
                    $existing = $stmt_check->fetch();

                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE presensi SET jam_masuk = ?, jam_keluar = ?, status = ?, keterangan = ? WHERE id = ?");
                        $stmt->execute([$jam_masuk, $jam_keluar, $status, $keterangan ? $keterangan : null, $existing['id']]);
                        $success = "Data presensi berhasil diperbarui!";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO presensi (user_id, tanggal, jam_masuk, jam_keluar, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $tanggal, $jam_masuk, $jam_keluar, $status, $keterangan ? $keterangan : null]);
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

// Fetch all staff for dropdown filters
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role = 'staf' ORDER BY nama_lengkap ASC");
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

<!-- Filters Card -->
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
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <a href="presensi.php" class="btn btn-outline-secondary w-100 py-2">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Panel -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th class="text-center">Status</th>
                    <th>Keterangan</th>
                    <th class="text-end" style="width: 180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presensi_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Tidak ada data presensi untuk filter ini.</td>
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
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editPresensiModal" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-userid="<?= $p['user_id'] ?>" 
                                    data-tanggal="<?= $p['tanggal'] ?>" 
                                    data-masuk="<?= $p['jam_masuk'] ? substr($p['jam_masuk'], 0, 5) : '' ?>" 
                                    data-keluar="<?= $p['jam_keluar'] ? substr($p['jam_keluar'], 0, 5) : '' ?>" 
                                    data-status="<?= $p['status'] ?>" 
                                    data-keterangan="<?= htmlspecialchars($p['keterangan'] ?? '') ?>">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deletePresensiModal" 
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
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="manualPresensiModalLabel">Input Presensi Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user_id" class="form-label text-secondary fw-semibold">Pilih Pegawai *</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">-- Pilih Pegawai --</option>
                            <?php foreach ($staff_members as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="tanggal" class="form-label text-secondary fw-semibold">Tanggal *</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="jam_masuk" class="form-label text-secondary fw-semibold">Jam Masuk</label>
                            <input type="time" class="form-control" id="jam_masuk" name="jam_masuk">
                        </div>
                        <div class="col-6">
                            <label for="jam_keluar" class="form-label text-secondary fw-semibold">Jam Pulang</label>
                            <input type="time" class="form-control" id="jam_keluar" name="jam_keluar">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label text-secondary fw-semibold">Status Kehadiran *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="hadir">Hadir</option>
                            <option value="terlambat">Terlambat</option>
                            <option value="alpha">Alpha</option>
                            <option value="sakit">Sakit</option>
                            <option value="izin">Izin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="keterangan" class="form-label text-secondary fw-semibold">Keterangan / Alasan</label>
                        <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Contoh: Sakit dengan surat dokter, Dinas luar">
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
    <div class="modal-dialog">
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
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Pegawai</label>
                        <input type="text" class="form-control bg-light" id="edit_nama_disabled" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Tanggal</label>
                        <input type="text" class="form-control bg-light" id="edit_tanggal_disabled" disabled>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="edit_jam_masuk" class="form-label text-secondary fw-semibold">Jam Masuk</label>
                            <input type="time" class="form-control" id="edit_jam_masuk" name="jam_masuk">
                        </div>
                        <div class="col-6">
                            <label for="edit_jam_keluar" class="form-label text-secondary fw-semibold">Jam Pulang</label>
                            <input type="time" class="form-control" id="edit_jam_keluar" name="jam_keluar">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label text-secondary fw-semibold">Status Kehadiran *</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="hadir">Hadir</option>
                            <option value="terlambat">Terlambat</option>
                            <option value="alpha">Alpha</option>
                            <option value="sakit">Sakit</option>
                            <option value="izin">Izin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_keterangan" class="form-label text-secondary fw-semibold">Keterangan / Alasan</label>
                        <input type="text" class="form-control" id="edit_keterangan" name="keterangan">
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
<div class="modal fade" id="deletePresensiModal" tabindex="-1" aria-labelledby="deletePresensiModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deletePresensiModalLabel">Hapus Data Presensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
        const button = event.relatedTarget;
        
        // Find row values
        const row = button.closest('tr');
        const nama = row.querySelector('.fw-semibold').textContent;
        
        document.getElementById('edit_user_id').value = button.getAttribute('data-userid');
        document.getElementById('edit_tanggal').value = button.getAttribute('data-tanggal');
        document.getElementById('edit_nama_disabled').value = nama;
        document.getElementById('edit_tanggal_disabled').value = button.getAttribute('data-tanggal');
        
        document.getElementById('edit_jam_masuk').value = button.getAttribute('data-masuk');
        document.getElementById('edit_jam_keluar').value = button.getAttribute('data-keluar');
        document.getElementById('edit_status').value = button.getAttribute('data-status');
        document.getElementById('edit_keterangan').value = button.getAttribute('data-keterangan');
    });

    const deleteModal = document.getElementById('deletePresensiModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('delete_id').value = button.getAttribute('data-id');
        document.getElementById('delete_nama_label').textContent = button.getAttribute('data-nama');
        document.getElementById('delete_tanggal_label').textContent = button.getAttribute('data-tanggal');
    });
</script>

<?php require_once 'footer.php'; ?>
