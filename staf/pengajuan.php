<?php
require_once 'header.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Proses Form Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pengajuan'])) {
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $kategori = $_POST['kategori'];
    $keterangan = $_POST['keterangan'];
    
    // Upload File Bukti (Opsional)
    $bukti_file = null;
    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/bukti/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['bukti_file']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['bukti_file']['tmp_name'], $target_path)) {
            $bukti_file = $file_name;
        } else {
            $error_msg = "Gagal mengunggah file bukti.";
        }
    }

    if (empty($error_msg)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pengajuan_izin (user_id, tanggal_mulai, tanggal_selesai, kategori, keterangan, bukti_file) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $tanggal_mulai, $tanggal_selesai, $kategori, $keterangan, $bukti_file]);
            $success_msg = "Pengajuan berhasil dikirim dan menunggu persetujuan admin.";
        } catch (PDOException $e) {
            $error_msg = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Ambil Riwayat Pengajuan
$stmt = $pdo->prepare("SELECT * FROM pengajuan_izin WHERE user_id = ? ORDER BY tanggal_pengajuan DESC");
$stmt->execute([$user_id]);
$riwayat_pengajuan = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Pengajuan Izin / Sakit</h3>
</div>

<?php if ($success_msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($success_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($error_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Form Pengajuan -->
    <div class="col-lg-4 mb-4">
        <div class="card-custom">
            <h5 class="fw-bold mb-4">Form Pengajuan</h5>
            <form action="pengajuan.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="kategori" class="form-label">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" required>
                        <option value="">Pilih Kategori...</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" required>
                </div>
                <div class="mb-3">
                    <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                    <input type="date" class="form-control" id="tanggal_selesai" name="tanggal_selesai" required>
                </div>
                <div class="mb-3">
                    <label for="keterangan" class="form-label">Keterangan / Alasan</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="3" required></textarea>
                </div>
                <div class="mb-4">
                    <label for="bukti_file" class="form-label">File Bukti (Opsional)</label>
                    <input type="file" class="form-control" id="bukti_file" name="bukti_file" accept=".jpg,.jpeg,.png,.pdf">
                    <small class="text-muted">Format: JPG, PNG, PDF (Max 2MB)</small>
                </div>
                <button type="submit" name="submit_pengajuan" class="btn btn-primary w-100">Kirim Pengajuan</button>
            </form>
        </div>
    </div>

    <!-- Riwayat Pengajuan -->
    <div class="col-lg-8 mb-4">
        <div class="card-custom">
            <h5 class="fw-bold mb-4">Riwayat Pengajuan</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tgl Pengajuan</th>
                            <th>Tanggal Izin</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Bukti</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($riwayat_pengajuan) > 0): ?>
                            <?php foreach ($riwayat_pengajuan as $row): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal_pengajuan'])) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?> 
                                        <?= ($row['tanggal_mulai'] !== $row['tanggal_selesai']) ? ' - ' . date('d/m/Y', strtotime($row['tanggal_selesai'])) : '' ?>
                                    </td>
                                    <td>
                                        <?php if ($row['kategori'] === 'sakit'): ?>
                                            <span class="badge badge-sakit rounded-pill px-3 py-2">Sakit</span>
                                        <?php else: ?>
                                            <span class="badge badge-izin rounded-pill px-3 py-2">Izin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                    <td>
                                        <?php if ($row['bukti_file']): ?>
                                            <a href="../uploads/bukti/<?= htmlspecialchars($row['bukti_file']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-file-earmark-text"></i> Lihat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2">Pending</span>
                                        <?php elseif ($row['status'] === 'disetujui'): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2">Disetujui</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-2">Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat pengajuan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
