<?php
require_once 'header.php';

$success = '';
$error = '';

// Handle POST Requests (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // CREATE JABATAN
        if ($action === 'create') {
            $nama_jabatan = trim($_POST['nama_jabatan']);
            if (!empty($nama_jabatan)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO jabatan (nama_jabatan) VALUES (?)");
                    $stmt->execute([$nama_jabatan]);
                    $success = "Jabatan '$nama_jabatan' berhasil ditambahkan!";
                } catch (PDOException $e) {
                    $error = "Gagal menambah jabatan. Mungkin nama jabatan sudah terdaftar.";
                }
            } else {
                $error = "Nama jabatan tidak boleh kosong.";
            }
        }

        // UPDATE JABATAN
        elseif ($action === 'update') {
            $id = $_POST['id'];
            $nama_jabatan = trim($_POST['nama_jabatan']);
            if (!empty($id) && !empty($nama_jabatan)) {
                try {
                    $stmt = $pdo->prepare("UPDATE jabatan SET nama_jabatan = ? WHERE id = ?");
                    $stmt->execute([$nama_jabatan, $id]);
                    $success = "Jabatan berhasil diperbarui!";
                } catch (PDOException $e) {
                    $error = "Gagal memperbarui jabatan. Nama jabatan mungkin sudah digunakan.";
                }
            } else {
                $error = "Data tidak lengkap.";
            }
        }

        // DELETE JABATAN
        elseif ($action === 'delete') {
            $id = $_POST['id'];
            if (!empty($id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM jabatan WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Jabatan berhasil dihapus!";
                } catch (PDOException $e) {
                    $error = "Gagal menghapus jabatan. Jabatan ini mungkin masih terikat dengan data pegawai.";
                }
            }
        }
    }
}

// Fetch all jabatan
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
        <h4 class="fw-bold text-dark mb-1">Data Jabatan</h4>
        <p class="text-muted mb-0">Kelola daftar jabatan staf Kantor Desa Sungai Rambut.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJabatanModal">
        <i class="bi bi-plus-lg me-2"></i> Tambah Jabatan
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
                    <th style="width: 80px;">No</th>
                    <th>Nama Jabatan</th>
                    <th class="text-end" style="width: 200px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jabatan_list)): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">Belum ada data jabatan.</td>
                    </tr>
                <?php else: $no = 1; foreach ($jabatan_list as $j): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($j['nama_jabatan']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editJabatanModal" 
                                    data-id="<?= $j['id'] ?>" 
                                    data-nama="<?= htmlspecialchars($j['nama_jabatan']) ?>">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteJabatanModal" 
                                    data-id="<?= $j['id'] ?>" 
                                    data-nama="<?= htmlspecialchars($j['nama_jabatan']) ?>">
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
<div class="modal fade" id="addJabatanModal" tabindex="-1" aria-labelledby="addJabatanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addJabatanModalLabel">Tambah Jabatan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_jabatan" class="form-label text-secondary fw-semibold">Nama Jabatan</label>
                        <input type="text" class="form-control" id="nama_jabatan" name="nama_jabatan" placeholder="Contoh: Kaur Kesra" required>
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
<div class="modal fade" id="editJabatanModal" tabindex="-1" aria-labelledby="editJabatanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editJabatanModalLabel">Edit Jabatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nama_jabatan" class="form-label text-secondary fw-semibold">Nama Jabatan</label>
                        <input type="text" class="form-control" id="edit_nama_jabatan" name="nama_jabatan" required>
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
<div class="modal fade" id="deleteJabatanModal" tabindex="-1" aria-labelledby="deleteJabatanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteJabatanModalLabel">Hapus Jabatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus jabatan <strong id="delete_nama_label"></strong>?</p>
                    <div class="alert alert-warning py-2 px-3 mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <small>Tindakan ini tidak dapat dibatalkan.</small>
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
    // JS to pass data to Modals
    const editModal = document.getElementById('editJabatanModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nama = button.getAttribute('data-nama');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama_jabatan').value = nama;
    });

    const deleteModal = document.getElementById('deleteJabatanModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nama = button.getAttribute('data-nama');
        
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_nama_label').textContent = nama;
    });
</script>

<?php require_once 'footer.php'; ?>
