<?php
require_once 'header.php';

$success = '';
$error = '';

$user_id = $_SESSION['user_id'];

// Handle Update Profile POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($nama_lengkap) && !empty($username)) {
        try {
            if (!empty($password)) {
                // Update with password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id = ?");
                $stmt->execute([$nama_lengkap, $username, $hash, $user_id]);
            } else {
                // Update without password
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?");
                $stmt->execute([$nama_lengkap, $username, $user_id]);
            }
            
            // Update session variables
            $_SESSION['nama_lengkap'] = $nama_lengkap;
            $_SESSION['username'] = $username;
            $success = "Profil Anda berhasil diperbarui!";
        } catch (PDOException $e) {
            $error = "Gagal memperbarui profil. Username mungkin sudah digunakan oleh pengguna lain.";
        }
    } else {
        $error = "Nama Lengkap dan Username tidak boleh kosong.";
    }
}

// Fetch current details
try {
    $stmt = $pdo->prepare("SELECT u.*, j.nama_jabatan 
                           FROM users u 
                           LEFT JOIN jabatan j ON u.jabatan_id = j.id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $kades = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Gagal memuat profil: " . $e->getMessage();
}
?>

<!-- Page Header -->
<div class="page-header">
    <h4 class="fw-bold text-dark mb-1">Profil Kepala Desa</h4>
    <p class="text-muted mb-0">Perbarui informasi profil dan kata sandi akun Kepala Desa Anda.</p>
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

<div class="row g-4">
    <!-- Edit Profile Card -->
    <div class="col-lg-6">
        <div class="card panel-card p-4">
            <h5 class="fw-bold text-dark mb-4"><i class="bi bi-person-gear me-2 text-primary"></i>Detail Akun</h5>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label text-secondary fw-semibold">Nama Lengkap *</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($kades['nama_lengkap']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label text-secondary fw-semibold">Username Login *</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($kades['username']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="nip" class="form-label text-secondary fw-semibold">NIP</label>
                    <input type="text" class="form-control bg-light" id="nip" value="<?= htmlspecialchars($kades['nip'] ?? '-') ?>" disabled>
                    <div class="form-text text-muted">NIP tidak dapat diubah secara langsung. Hubungi operator/IT jika ada kesalahan.</div>
                </div>
                <div class="mb-3">
                    <label for="jabatan" class="form-label text-secondary fw-semibold">Jabatan</label>
                    <input type="text" class="form-control bg-light" id="jabatan" value="<?= htmlspecialchars($kades['nama_jabatan'] ?? 'Kepala Desa') ?>" disabled>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label text-secondary fw-semibold">Kata Sandi Baru (Biarkan kosong jika tidak diganti)</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password baru jika ingin mengubah">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2.5">
                    <i class="bi bi-save me-2"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- Info Card -->
    <div class="col-lg-6">
        <div class="card panel-card h-100 bg-light border-0 p-4">
            <div class="text-center py-4">
                <i class="bi bi-shield-lock-fill display-1 text-primary mb-3"></i>
                <h5 class="fw-bold text-dark">Keamanan Akun Kepala Desa</h5>
                <p class="text-muted px-4">Pastikan username dan kata sandi Anda terjaga kerahasiannya untuk memelihara integritas data laporan presensi staf Desa Sungai Rambut.</p>
            </div>
            <hr>
            <div class="p-3">
                <h6 class="fw-bold text-dark"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Tips Kata Sandi:</h6>
                <ul class="text-secondary small ps-3">
                    <li>Gunakan minimal 8 karakter.</li>
                    <li>Kombinasikan huruf besar, huruf kecil, angka, dan simbol.</li>
                    <li>Ganti kata sandi Anda secara berkala demi keamanan.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
