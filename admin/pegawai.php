<?php
require_once 'header.php';

$success = '';
$error = '';

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // CREATE PEGAWAI
        if ($action === 'create') {
            $username         = trim($_POST['username']);
            $password         = trim($_POST['password']);
            $nama_lengkap     = trim($_POST['nama_lengkap']);
            $nip              = trim($_POST['nip']);
            $jabatan_id       = $_POST['jabatan_id'];
            $user_id          = trim($_POST['user_id']);

            if (!empty($username) && !empty($password) && !empty($nama_lengkap) && !empty($jabatan_id)) {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap, nip, jabatan_id, user_id) VALUES (?, ?, 'staf', ?, ?, ?, ?)");
                    $stmt->execute([$username, $hash, $nama_lengkap, $nip ? $nip : null, $jabatan_id, $user_id ? $user_id : null]);
                    $success = "Pegawai '$nama_lengkap' berhasil ditambahkan!";
                } catch (PDOException $e) {
                    $error = "Gagal menambah pegawai. Username, NIP, atau User ID mungkin sudah digunakan.";
                }
            } else {
                $error = "Kolom bertanda bintang (*) wajib diisi.";
            }
        }

        // UPDATE PEGAWAI
        elseif ($action === 'update') {
            $id               = $_POST['id'];
            $username         = trim($_POST['username']);
            $nama_lengkap     = trim($_POST['nama_lengkap']);
            $nip              = trim($_POST['nip']);
            $jabatan_id       = $_POST['jabatan_id'];
            $password         = trim($_POST['password']);
            $user_id          = trim($_POST['user_id']);

            if (!empty($id) && !empty($username) && !empty($nama_lengkap) && !empty($jabatan_id)) {
                try {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ?, nip = ?, jabatan_id = ?, user_id = ? WHERE id = ? AND role = 'staf'");
                        $stmt->execute([$username, $hash, $nama_lengkap, $nip ? $nip : null, $jabatan_id, $user_id ? $user_id : null, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, nama_lengkap = ?, nip = ?, jabatan_id = ?, user_id = ? WHERE id = ? AND role = 'staf'");
                        $stmt->execute([$username, $nama_lengkap, $nip ? $nip : null, $jabatan_id, $user_id ? $user_id : null, $id]);
                    }
                    $success = "Data pegawai berhasil diperbarui!";
                } catch (PDOException $e) {
                    $error = "Gagal memperbarui pegawai. Username, NIP, atau User ID mungkin sudah digunakan.";
                }
            } else {
                $error = "Kolom bertanda bintang (*) wajib diisi.";
            }
        }

        // DELETE PEGAWAI
        elseif ($action === 'delete') {
            $id = $_POST['id'];
            if (!empty($id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staf'");
                    $stmt->execute([$id]);
                    $success = "Pegawai berhasil dihapus!";
                } catch (PDOException $e) {
                    $error = "Gagal menghapus pegawai: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all staff
$pegawai_list = [];
try {
    $stmt = $pdo->query("SELECT u.*, j.nama_jabatan 
                         FROM users u 
                         LEFT JOIN jabatan j ON u.jabatan_id = j.id 
                         WHERE u.role = 'staf' 
                         ORDER BY u.nama_lengkap ASC");
    $pegawai_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data pegawai: " . $e->getMessage();
}

// Fetch all jabatan for dropdown
$jabatan_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM jabatan ORDER BY nama_jabatan ASC");
    $jabatan_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data jabatan: " . $e->getMessage();
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Data Pegawai</h4>
        <p class="text-muted mb-0">Kelola informasi seluruh staf Kantor Desa Sungai Rambut.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPegawaiModal">
        <i class="bi bi-plus-lg me-2"></i> Tambah Pegawai
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

<!-- Table Panel -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Lengkap</th>
                    <th>User ID</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th class="text-end" style="width: 220px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pegawai_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada data pegawai.</td>
                    </tr>
                <?php else: $no = 1; foreach ($pegawai_list as $p): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                        <td>
                            <?php if ($p['user_id']): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded">
                                    <i class="bi bi-fingerprint me-1"></i><?= htmlspecialchars($p['user_id']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['nip'] ? htmlspecialchars($p['nip']) : '-' ?></td>
                        <td><?= htmlspecialchars($p['nama_jabatan'] ?? 'Belum Ditentukan') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#detailPegawaiModal" 
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>"
                                    data-nip="<?= htmlspecialchars($p['nip'] ?? '-') ?>"
                                    data-fp="<?= htmlspecialchars($p['user_id'] ?? '-') ?>"
                                    data-jabatan="<?= htmlspecialchars($p['nama_jabatan'] ?? 'Belum Ditentukan') ?>"
                                    data-username="<?= htmlspecialchars($p['username']) ?>">
                                <i class="bi bi-eye me-1"></i> Detail
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editPegawaiModal" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>"
                                    data-nip="<?= htmlspecialchars($p['nip'] ?? '') ?>"
                                    data-fp="<?= htmlspecialchars($p['user_id'] ?? '') ?>"
                                    data-jabatan="<?= $p['jabatan_id'] ?>"
                                    data-username="<?= htmlspecialchars($p['username']) ?>">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deletePegawaiModal" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>">
                                <i class="bi bi-trash3 me-1"></i> Hapus
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addPegawaiModal" tabindex="-1" aria-labelledby="addPegawaiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addPegawaiModalLabel">Tambah Pegawai Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nama_lengkap" class="form-label text-secondary fw-semibold">Nama Lengkap *</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Contoh: Budi Setiawan" required>
                        </div>
                        <div class="col-md-6">
                            <label for="user_id" class="form-label text-secondary fw-semibold">
                                <i class="bi bi-fingerprint me-1 text-primary"></i>User ID *
                            </label>
                            <input type="text" class="form-control" id="user_id" name="user_id" placeholder="Contoh: 1, 2, 10 (dari mesin absensi)" required>
                            <div class="form-text">ID yang terdaftar di mesin absensi (Abnormal Report)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nip" class="form-label text-secondary fw-semibold">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Contoh: 199508202021021003">
                        </div>
                        <div class="col-md-6">
                            <label for="jabatan_id" class="form-label text-secondary fw-semibold">Jabatan *</label>
                            <select class="form-select" id="jabatan_id" name="jabatan_id" required>
                                <option value="">-- Pilih Jabatan --</option>
                                <?php foreach ($jabatan_list as $j): ?>
                                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jabatan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label text-secondary fw-semibold">Username Login *</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Contoh: budi_setiawan" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label text-secondary fw-semibold">Password Login *</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter" required>
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
<div class="modal fade" id="editPegawaiModal" tabindex="-1" aria-labelledby="editPegawaiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editPegawaiModalLabel">Edit Data Pegawai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_nama_lengkap" class="form-label text-secondary fw-semibold">Nama Lengkap *</label>
                            <input type="text" class="form-control" id="edit_nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_user_id" class="form-label text-secondary fw-semibold">
                                <i class="bi bi-fingerprint me-1 text-primary"></i>User ID *
                            </label>
                            <input type="text" class="form-control" id="edit_user_id" name="user_id" placeholder="ID dari mesin absensi" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_nip" class="form-label text-secondary fw-semibold">NIP</label>
                            <input type="text" class="form-control" id="edit_nip" name="nip">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_jabatan_id" class="form-label text-secondary fw-semibold">Jabatan *</label>
                            <select class="form-select" id="edit_jabatan_id" name="jabatan_id" required>
                                <option value="">-- Pilih Jabatan --</option>
                                <?php foreach ($jabatan_list as $j): ?>
                                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jabatan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_username" class="form-label text-secondary fw-semibold">Username Login *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_password" class="form-label text-secondary fw-semibold">Password Baru (kosong = tidak diganti)</label>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Masukkan password baru">
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

<!-- Detail Modal -->
<div class="modal fade" id="detailPegawaiModal" tabindex="-1" aria-labelledby="detailPegawaiModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="detailPegawaiModalLabel">Detail Informasi Pegawai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered mb-0">
                    <tr>
                        <th class="w-40 text-secondary bg-light">Nama Lengkap</th>
                        <td id="detail_nama" class="fw-semibold"></td>
                    </tr>
                    <tr>
                        <th class="text-secondary bg-light"><i class="bi bi-fingerprint me-1 text-primary"></i>User ID</th>
                        <td id="detail_fp"><span class="badge bg-primary bg-opacity-10 text-primary"></span></td>
                    </tr>
                    <tr>
                        <th class="text-secondary bg-light">NIP</th>
                        <td id="detail_nip"></td>
                    </tr>
                    <tr>
                        <th class="text-secondary bg-light">Jabatan</th>
                        <td id="detail_jabatan"></td>
                    </tr>
                    <tr>
                        <th class="text-secondary bg-light">Username Login</th>
                        <td><code id="detail_username"></code></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deletePegawaiModal" tabindex="-1" aria-labelledby="deletePegawaiModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deletePegawaiModalLabel">Hapus Pegawai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data pegawai <strong id="delete_nama_label"></strong>?</p>
                    <div class="alert alert-warning py-2 px-3 mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <small>Semua riwayat presensi pegawai ini juga akan dihapus secara permanen dari sistem.</small>
                    </div>
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
    const detailModal = document.getElementById('detailPegawaiModal');
    detailModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('detail_nama').textContent = button.getAttribute('data-nama');
        document.getElementById('detail_fp').textContent   = button.getAttribute('data-fp');
        document.getElementById('detail_nip').textContent  = button.getAttribute('data-nip');
        document.getElementById('detail_jabatan').textContent    = button.getAttribute('data-jabatan');
        document.getElementById('detail_username').textContent   = button.getAttribute('data-username');
    });

    const editModal = document.getElementById('editPegawaiModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('edit_id').value                  = button.getAttribute('data-id');
        document.getElementById('edit_nama_lengkap').value        = button.getAttribute('data-nama');
        document.getElementById('edit_user_id').value = button.getAttribute('data-fp');
        document.getElementById('edit_nip').value                 = button.getAttribute('data-nip');
        document.getElementById('edit_jabatan_id').value          = button.getAttribute('data-jabatan');
        document.getElementById('edit_username').value            = button.getAttribute('data-username');
        document.getElementById('edit_password').value            = '';
    });

    const deleteModal = document.getElementById('deletePegawaiModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('delete_id').value              = button.getAttribute('data-id');
        document.getElementById('delete_nama_label').textContent = button.getAttribute('data-nama');
    });
</script>

<?php require_once 'footer.php'; ?>
