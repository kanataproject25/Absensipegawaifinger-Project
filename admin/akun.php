<?php
require_once 'header.php';

$success = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // CREATE NEW ACCOUNT
        if ($action === 'create') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $role = $_POST['role'];
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $nip = trim($_POST['nip']);

            if (!empty($username) && !empty($password) && !empty($role) && !empty($nama_lengkap)) {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap, nip) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hash, $role, $nama_lengkap, $nip ? $nip : null]);
                    $success = "Akun baru '$username' berhasil dibuat!";
                } catch (PDOException $e) {
                    $error = "Gagal membuat akun. Username atau NIP mungkin sudah terdaftar.";
                }
            } else {
                $error = "Kolom bertanda bintang (*) wajib diisi.";
            }
        }

        // RESET PASSWORD
        elseif ($action === 'reset_password') {
            $id = $_POST['id'];
            $new_password = trim($_POST['new_password']);

            if (!empty($id) && !empty($new_password)) {
                try {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hash, $id]);
                    $success = "Password berhasil di-reset!";
                } catch (PDOException $e) {
                    $error = "Gagal mereset password: " . $e->getMessage();
                }
            } else {
                $error = "Password baru wajib diisi.";
            }
        }

        // DELETE ACCOUNT
        elseif ($action === 'delete') {
            $id = $_POST['id'];
            // Protect current logged-in user from deletion
            if ($id == $_SESSION['user_id']) {
                $error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.";
            } elseif (!empty($id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Akun berhasil dihapus!";
                } catch (PDOException $e) {
                    $error = "Gagal menghapus akun: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all accounts
$accounts = [];
try {
    $stmt = $pdo->query("SELECT id, username, role, nama_lengkap, nip FROM users ORDER BY role ASC, nama_lengkap ASC");
    $accounts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data akun: " . $e->getMessage();
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Kelola Akun & Hak Akses</h4>
        <p class="text-muted mb-0">Kelola credentials login admin, kepala desa, dan staf.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAccountModal">
        <i class="bi bi-person-plus me-2"></i> Tambah Akun Baru
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

<!-- Accounts Table Panel -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Pengguna</th>
                    <th>Username</th>
                    <th>NIP</th>
                    <th>Hak Akses / Role</th>
                    <th class="text-end" style="width: 280px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($accounts as $acc): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($acc['nama_lengkap']) ?></td>
                        <td><code><?= htmlspecialchars($acc['username']) ?></code></td>
                        <td><?= $acc['nip'] ? htmlspecialchars($acc['nip']) : '-' ?></td>
                        <td>
                            <?php if ($acc['role'] === 'admin'): ?>
                                <span class="badge bg-primary px-2.5 py-1.5">Admin</span>
                            <?php elseif ($acc['role'] === 'kepala_desa'): ?>
                                <span class="badge bg-success px-2.5 py-1.5">Kepala Desa</span>
                            <?php else: ?>
                                <span class="badge bg-secondary px-2.5 py-1.5">Staf</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-warning me-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#resetPasswordModal" 
                                    data-id="<?= $acc['id'] ?>" 
                                    data-nama="<?= htmlspecialchars($acc['nama_lengkap']) ?>">
                                <i class="bi bi-key me-1"></i> Reset Password
                            </button>
                            <?php if ($acc['id'] != $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteAccountModal" 
                                        data-id="<?= $acc['id'] ?>" 
                                        data-nama="<?= htmlspecialchars($acc['nama_lengkap']) ?>">
                                    <i class="bi bi-trash3 me-1"></i> Hapus
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-light text-muted" disabled>
                                    <i class="bi bi-trash3 me-1"></i> Hapus
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Account Modal -->
<div class="modal fade" id="createAccountModal" tabindex="-1" aria-labelledby="createAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="createAccountModalLabel">Tambah Akun Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label text-secondary fw-semibold">Nama Lengkap *</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label for="nip" class="form-label text-secondary fw-semibold">NIP (Opsional)</label>
                        <input type="text" class="form-control" id="nip" name="nip">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label text-secondary fw-semibold">Role / Hak Akses *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="admin">Admin (Petugas Pengelola)</option>
                            <option value="kepala_desa">Kepala Desa (Pimpinan)</option>
                            <option value="staf">Staf (Pegawai Biasa)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label text-secondary fw-semibold">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label text-secondary fw-semibold">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="resetPasswordModalLabel">Reset Password Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_id">
                <div class="modal-body">
                    <p>Mereset password untuk akun: <strong id="reset_nama_label"></strong></p>
                    <div class="mb-3">
                        <label for="new_password" class="form-label text-secondary fw-semibold">Password Baru *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Masukkan password baru" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteAccountModalLabel">Hapus Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus akun <strong id="delete_nama_label"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const resetModal = document.getElementById('resetPasswordModal');
    resetModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('reset_id').value = button.getAttribute('data-id');
        document.getElementById('reset_nama_label').textContent = button.getAttribute('data-nama');
        document.getElementById('new_password').value = '';
    });

    const deleteModal = document.getElementById('deleteAccountModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('delete_id').value = button.getAttribute('data-id');
        document.getElementById('delete_nama_label').textContent = button.getAttribute('data-nama');
    });
</script>

<?php require_once 'footer.php'; ?>
