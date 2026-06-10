<?php
require_once 'header.php';

$success = '';
$error   = '';
$user_id = $_SESSION['user_id'];

// Handle Update Profile POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username     = trim($_POST['username']);
    $password     = trim($_POST['password']);

    if (!empty($nama_lengkap) && !empty($username)) {
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id = ?");
                $stmt->execute([$nama_lengkap, $username, $hash, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?");
                $stmt->execute([$nama_lengkap, $username, $user_id]);
            }
            $_SESSION['nama_lengkap'] = $nama_lengkap;
            $_SESSION['username']     = $username;
            $success = "Profil Anda berhasil diperbarui!";
        } catch (PDOException $e) {
            $error = "Gagal memperbarui profil. Username mungkin sudah digunakan.";
        }
    } else {
        $error = "Nama Lengkap dan Username tidak boleh kosong.";
    }
}

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT u.*, j.nama_jabatan 
                           FROM users u 
                           LEFT JOIN jabatan j ON u.jabatan_id = j.id
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    // Fallback tanpa join jabatan (jika kolom jabatan_id tidak ada)
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $user['nama_jabatan'] = $user['jabatan'] ?? '-';
    } catch (PDOException $e2) {
        $error = "Gagal memuat profil.";
        $user  = [];
    }
}

// Hitung statistik kehadiran total
try {
    $stmt_all = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('hadir','terlambat') THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
        SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha
        FROM presensi WHERE user_id = ?");
    $stmt_all->execute([$user_id]);
    $stat_all = $stmt_all->fetch();

    $persen = ($stat_all['total'] > 0)
        ? round(($stat_all['hadir'] / $stat_all['total']) * 100, 1)
        : 0;
} catch (PDOException $e) {
    $stat_all = ['total' => 0, 'hadir' => 0, 'terlambat' => 0, 'alpha' => 0];
    $persen   = 0;
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Profil Saya</h4>
        <p class="text-muted mb-0">Lihat dan perbarui informasi akun Anda.</p>
    </div>
    <div>
        <span class="badge bg-light text-dark border py-2 px-3">
            <i class="bi bi-calendar3 me-2 text-primary"></i><?= date('d F Y') ?>
        </span>
    </div>
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

    <!-- Kartu Info Profil -->
    <div class="col-lg-4">
        <div class="panel-card shadow-sm p-4 text-center h-100">
            <!-- Avatar -->
            <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center"
                 style="width: 90px; height: 90px; background: linear-gradient(135deg, #1E3A5F, #3498DB);">
                <i class="bi bi-person-fill text-white" style="font-size: 2.8rem;"></i>
            </div>
            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($user['nama_lengkap'] ?? '-') ?></h5>
            <p class="text-muted mb-3"><?= htmlspecialchars($user['nama_jabatan'] ?? ($user['jabatan'] ?? 'Staf')) ?></p>

            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-4">
                <i class="bi bi-person-badge me-1"></i> Staf Kantor Desa
            </span>

            <hr>

            <!-- Info Detail -->
            <div class="text-start">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-card-text text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">NIP</div>
                        <div class="fw-semibold"><?= htmlspecialchars($user['nip'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-person text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Username</div>
                        <div class="fw-semibold"><?= htmlspecialchars($user['username'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-briefcase text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Bergabung Sejak</div>
                        <div class="fw-semibold">
                            <?= isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '-' ?>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Statistik Kehadiran Total -->
            <h6 class="fw-bold text-dark text-start mb-3">Statistik Kehadiran (Total)</h6>
            <div class="row g-2 text-center">
                <div class="col-4">
                    <div class="bg-light rounded-3 py-2">
                        <div class="fw-bold" style="color: var(--color-hadir);"><?= $stat_all['hadir'] ?></div>
                        <small class="text-muted">Hadir</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bg-light rounded-3 py-2">
                        <div class="fw-bold" style="color: var(--color-terlambat);"><?= $stat_all['terlambat'] ?></div>
                        <small class="text-muted">Terlambat</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bg-light rounded-3 py-2">
                        <div class="fw-bold" style="color: var(--color-alpha);"><?= $stat_all['alpha'] ?></div>
                        <small class="text-muted">Alpha</small>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Tingkat Kehadiran</span>
                    <span class="fw-semibold text-primary"><?= $persen ?>%</span>
                </div>
                <div class="progress-custom">
                    <div class="progress-bar bg-primary" style="width: <?= $persen ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Edit Profil -->
    <div class="col-lg-8">
        <div class="panel-card shadow-sm p-4">
            <h5 class="fw-bold text-dark mb-4">
                <i class="bi bi-person-gear me-2 text-primary"></i>Edit Informasi Akun
            </h5>
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nama_lengkap" class="form-label fw-semibold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap"
                               value="<?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label fw-semibold text-secondary">Username Login <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="nip" class="form-label fw-semibold text-secondary">NIP</label>
                        <input type="text" class="form-control bg-light" id="nip"
                               value="<?= htmlspecialchars($user['nip'] ?? '-') ?>" disabled>
                        <div class="form-text text-muted">NIP dikelola oleh Administrator.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="jabatan_field" class="form-label fw-semibold text-secondary">Jabatan</label>
                        <input type="text" class="form-control bg-light" id="jabatan_field"
                               value="<?= htmlspecialchars($user['nama_jabatan'] ?? ($user['jabatan'] ?? '-')) ?>" disabled>
                        <div class="form-text text-muted">Jabatan dikelola oleh Administrator.</div>
                    </div>
                    <div class="col-12">
                        <hr class="my-1">
                        <h6 class="fw-bold text-dark mt-2 mb-3">
                            <i class="bi bi-shield-lock me-2 text-primary"></i>Ubah Kata Sandi
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label fw-semibold text-secondary">Kata Sandi Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Kosongkan jika tidak diubah">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                    onclick="togglePass()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted">Minimal 8 karakter, kombinasikan huruf dan angka.</div>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary px-5 py-2">
                            <i class="bi bi-save me-2"></i> Simpan Perubahan
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2 ms-2">
                            <i class="bi bi-arrow-left me-1"></i> Batal
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tips Keamanan -->
        <div class="panel-card shadow-sm p-4 mt-4" style="background: linear-gradient(135deg, #EBF5FB, #F8F9FA);">
            <h6 class="fw-bold text-dark mb-3">
                <i class="bi bi-info-circle-fill me-2 text-primary"></i>Tips Keamanan Akun
            </h6>
            <ul class="text-secondary small mb-0 ps-3">
                <li class="mb-1">Gunakan minimal <strong>8 karakter</strong> untuk kata sandi.</li>
                <li class="mb-1">Kombinasikan huruf besar, huruf kecil, angka, dan simbol.</li>
                <li class="mb-1">Jangan bagikan kata sandi Anda kepada siapapun.</li>
                <li>Ganti kata sandi secara berkala untuk keamanan optimal.</li>
            </ul>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const input   = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>

<?php require_once 'footer.php'; ?>
