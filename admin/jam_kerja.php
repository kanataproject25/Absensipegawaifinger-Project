<?php
require_once 'header.php';

$success = '';
$error = '';

// Handle Shift Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        $nama_shift = trim($_POST['nama_shift']);
        $hari = trim($_POST['hari']);
        $jam_masuk = trim($_POST['jam_masuk']);
        $jam_pulang = trim($_POST['jam_pulang']);

        if (!empty($id) && !empty($nama_shift) && !empty($hari) && !empty($jam_masuk) && !empty($jam_pulang)) {
            try {
                $stmt = $pdo->prepare("UPDATE jam_kerja SET nama_shift = ?, hari = ?, jam_masuk = ?, jam_pulang = ? WHERE id = ?");
                $stmt->execute([$nama_shift, $hari, $jam_masuk, $jam_pulang, $id]);
                $success = "Shift kerja '$nama_shift' berhasil diperbarui!";
            } catch (PDOException $e) {
                $error = "Gagal memperbarui shift kerja: " . $e->getMessage();
            }
        } else {
            $error = "Semua kolom wajib diisi.";
        }
    }
}

// Fetch all shifts
$shifts = [];
try {
    $stmt = $pdo->query("SELECT * FROM jam_kerja ORDER BY id ASC");
    $shifts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal memuat data shift kerja: " . $e->getMessage();
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">Pengaturan Jam Kerja</h4>
        <p class="text-muted mb-0">Atur jam masuk dan jam pulang per shift kerja staf.</p>
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

<!-- Shift Cards Layout -->
<div class="row g-4">
    <?php foreach ($shifts as $s): ?>
        <div class="col-md-6">
            <div class="card card-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-calendar-range me-2"></i><?= htmlspecialchars($s['nama_shift']) ?></h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editShiftModal" 
                            data-id="<?= $s['id'] ?>"
                            data-nama="<?= htmlspecialchars($s['nama_shift']) ?>"
                            data-hari="<?= htmlspecialchars($s['hari']) ?>"
                            data-masuk="<?= $s['jam_masuk'] ?>"
                            data-pulang="<?= $s['jam_pulang'] ?>">
                        <i class="bi bi-pencil-square me-1"></i> Edit Shift
                    </button>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">Hari Berlaku</div>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($s['hari']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Jam Kerja</div>
                        <div class="fw-semibold text-dark">
                            <?= date('H:i', strtotime($s['jam_masuk'])) ?> – <?= date('H:i', strtotime($s['jam_pulang'])) ?> WIB
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Edit Shift Modal -->
<div class="modal fade" id="editShiftModal" tabindex="-1" aria-labelledby="editShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editShiftModalLabel">Edit Jam Kerja Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nama_shift" class="form-label text-secondary fw-semibold">Nama Shift</label>
                        <input type="text" class="form-control" id="edit_nama_shift" name="nama_shift" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_hari" class="form-label text-secondary fw-semibold">Hari (Pemisah Koma)</label>
                        <input type="text" class="form-control" id="edit_hari" name="hari" placeholder="Contoh: Senin,Selasa,Rabu,Kamis" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="edit_jam_masuk" class="form-label text-secondary fw-semibold">Jam Masuk</label>
                            <input type="time" class="form-control" id="edit_jam_masuk" name="jam_masuk" required>
                        </div>
                        <div class="col-6">
                            <label for="edit_jam_pulang" class="form-label text-secondary fw-semibold">Jam Pulang</label>
                            <input type="time" class="form-control" id="edit_jam_pulang" name="jam_pulang" required>
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

<script>
    const editModal = document.getElementById('editShiftModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_nama_shift').value = button.getAttribute('data-nama');
        document.getElementById('edit_hari').value = button.getAttribute('data-hari');
        document.getElementById('edit_jam_masuk').value = button.getAttribute('data-masuk');
        document.getElementById('edit_jam_pulang').value = button.getAttribute('data-pulang');
    });
</script>

<?php require_once 'footer.php'; ?>
