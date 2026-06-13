<?php
require_once 'header.php';

$success_msg = '';
$error_msg = '';

// Proses Verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pengajuan_id = $_POST['pengajuan_id'];
    $action = $_POST['action']; // 'disetujui' atau 'ditolak'
    
    try {
        $pdo->beginTransaction();

        // Update status pengajuan
        $stmt = $pdo->prepare("UPDATE pengajuan_izin SET status = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$action, $pengajuan_id]);
        
        if ($stmt->rowCount() > 0 && $action === 'disetujui') {
            // Ambil data pengajuan
            $stmt_get = $pdo->prepare("SELECT * FROM pengajuan_izin WHERE id = ?");
            $stmt_get->execute([$pengajuan_id]);
            $pengajuan = $stmt_get->fetch();
            
            if ($pengajuan) {
                $user_id = $pengajuan['user_id'];
                $tanggal_mulai = new DateTime($pengajuan['tanggal_mulai']);
                $tanggal_selesai = new DateTime($pengajuan['tanggal_selesai']);
                $tanggal_selesai->modify('+1 day'); // Supaya tanggal selesai ikut dilooping
                
                $interval = DateInterval::createFromDateString('1 day');
                $period = new DatePeriod($tanggal_mulai, $interval, $tanggal_selesai);
                
                $status_presensi = $pengajuan['kategori']; // 'izin' atau 'sakit'
                $keterangan = "Pengajuan " . ucfirst($status_presensi) . " disetujui admin. (" . $pengajuan['keterangan'] . ")";
                
                $stmt_insert = $pdo->prepare("INSERT INTO presensi (user_id, tanggal, status, keterangan) 
                                              VALUES (?, ?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)");
                
                foreach ($period as $dt) {
                    $tgl = $dt->format("Y-m-d");
                    // Cek apakah bukan hari libur (Sabtu/Minggu), asumsi Senin-Jumat jam_kerja
                    // Tapi karena bisa saja Shift beda, kita insert saja.
                    $stmt_insert->execute([$user_id, $tgl, $status_presensi, $keterangan]);
                }
            }
        }
        
        $pdo->commit();
        $success_msg = "Pengajuan berhasil " . ($action === 'disetujui' ? 'disetujui' : 'ditolak') . ".";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Ambil semua data pengajuan beserta nama user
$stmt = $pdo->query("
    SELECT p.*, u.nama_lengkap 
    FROM pengajuan_izin p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY FIELD(p.status, 'pending', 'disetujui', 'ditolak'), p.tanggal_pengajuan DESC
");
$pengajuan_list = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Verifikasi Izin / Sakit</h3>
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

<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Waktu Pengajuan</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal Izin</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th>Bukti</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pengajuan_list) > 0): ?>
                    <?php foreach ($pengajuan_list as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($row['tanggal_pengajuan'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?> 
                                <?= ($row['tanggal_mulai'] !== $row['tanggal_selesai']) ? '<br> s/d <br>' . date('d/m/Y', strtotime($row['tanggal_selesai'])) : '' ?>
                            </td>
                            <td>
                                <?php if ($row['kategori'] === 'sakit'): ?>
                                    <span class="badge badge-sakit rounded-pill px-3 py-2">Sakit</span>
                                <?php else: ?>
                                    <span class="badge badge-izin rounded-pill px-3 py-2">Izin</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="max-width: 200px; white-space: normal;">
                                    <small><?= htmlspecialchars($row['keterangan']) ?></small>
                                </div>
                            </td>
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
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline-block m-0 p-0" onsubmit="return confirm('Setujui pengajuan ini?');">
                                        <input type="hidden" name="pengajuan_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="action" value="disetujui" class="btn btn-sm btn-success" title="Setujui">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline-block m-0 p-0" onsubmit="return confirm('Tolak pengajuan ini?');">
                                        <input type="hidden" name="pengajuan_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="action" value="ditolak" class="btn btn-sm btn-danger" title="Tolak">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Belum ada pengajuan izin/sakit.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
