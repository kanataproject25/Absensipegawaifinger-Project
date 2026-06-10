<?php
require_once 'header.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$success = '';
$error = '';
$import_report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_path = $file['tmp_name'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), ['xls', 'xlsx'])) {
            try {
                // Load Spreadsheet
                $spreadsheet = IOFactory::load($file_path);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                if (count($rows) > 1) {
                    $headers = array_map('strtolower', array_map('trim', $rows[0]));
                    
                    // Automatic column mapping based on keywords
                    $col_nip = -1;
                    $col_tanggal = -1;
                    $col_masuk = -1;
                    $col_pulang = -1;

                    foreach ($headers as $index => $header) {
                        if (strpos($header, 'nip') !== false || strpos($header, 'pin') !== false || strpos($header, 'id') !== false || strpos($header, 'pegawai') !== false) {
                            $col_nip = $index;
                        } elseif (strpos($header, 'tanggal') !== false || strpos($header, 'date') !== false || strpos($header, 'tgl') !== false) {
                            $col_tanggal = $index;
                        } elseif (strpos($header, 'masuk') !== false || strpos($header, 'in') !== false || strpos($header, 'datang') !== false) {
                            $col_masuk = $index;
                        } elseif (strpos($header, 'pulang') !== false || strpos($header, 'out') !== false || strpos($header, 'keluar') !== false) {
                            $col_pulang = $index;
                        }
                    }

                    // Fallback to defaults if headers don't match
                    if ($col_nip === -1) $col_nip = 0;
                    if ($col_tanggal === -1) $col_tanggal = 1;
                    if ($col_masuk === -1) $col_masuk = 2;
                    if ($col_pulang === -1) $col_pulang = 3;

                    // Fetch shift configurations for late determination
                    $stmt_shifts = $pdo->query("SELECT * FROM jam_kerja");
                    $shifts = $stmt_shifts->fetchAll();
                    
                    $imported = 0;
                    $skipped = 0;

                    // Fetch all users NIP mapping to speed up DB queries
                    $stmt_users = $pdo->query("SELECT id, nama_lengkap, nip FROM users WHERE role = 'staf'");
                    $users_db = $stmt_users->fetchAll();
                    $user_map = [];
                    foreach ($users_db as $u) {
                        if ($u['nip']) {
                            $user_map[$u['nip']] = $u;
                        }
                    }

                    // Iterate starting from row index 1 (skip headers)
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        
                        // Skip completely empty rows
                        if (empty($row[$col_nip]) && empty($row[$col_tanggal])) {
                            continue;
                        }

                        $nip_val = trim($row[$col_nip]);
                        $tanggal_val = trim($row[$col_tanggal]);
                        $jam_masuk_val = isset($row[$col_masuk]) ? trim($row[$col_masuk]) : null;
                        $jam_keluar_val = isset($row[$col_pulang]) ? trim($row[$col_pulang]) : null;

                        // Check if NIP exists in our system
                        if (!isset($user_map[$nip_val])) {
                            $import_report[] = [
                                'row' => $i + 1,
                                'nip' => $nip_val,
                                'nama' => 'Tidak Dikenal',
                                'status' => 'Gagal',
                                'pesan' => "NIP '$nip_val' tidak terdaftar sebagai staf."
                            ];
                            $skipped++;
                            continue;
                        }

                        $user = $user_map[$nip_val];
                        $user_id = $user['id'];
                        $nama_staf = $user['nama_lengkap'];

                        // Format date to Y-m-d
                        $date_formatted = date('Y-m-d', strtotime($tanggal_val));
                        
                        // Parse status and lateness
                        $status = 'hadir';
                        $keterangan = null;
                        
                        if (empty($jam_masuk_val) || $jam_masuk_val === '-' || $jam_masuk_val === '00:00:00') {
                            $status = 'alpha';
                            $jam_masuk_db = null;
                            $jam_keluar_db = null;
                        } else {
                            $jam_masuk_db = date('H:i:s', strtotime($jam_masuk_val));
                            $jam_keluar_db = (!empty($jam_keluar_val) && $jam_keluar_val !== '-' && $jam_keluar_val !== '00:00:00') ? date('H:i:s', strtotime($jam_keluar_val)) : null;

                            // Determine lateness based on shift
                            $day_name = date('l', strtotime($date_formatted));
                            $indonesian_day = '';
                            switch($day_name) {
                                case 'Monday': $indonesian_day = 'Senin'; break;
                                case 'Tuesday': $indonesian_day = 'Selasa'; break;
                                case 'Wednesday': $indonesian_day = 'Rabu'; break;
                                case 'Thursday': $indonesian_day = 'Kamis'; break;
                                case 'Friday': $indonesian_day = 'Jumat'; break;
                                case 'Saturday': $indonesian_day = 'Sabtu'; break;
                                case 'Sunday': $indonesian_day = 'Minggu'; break;
                            }

                            // Match shift
                            $target_shift = null;
                            foreach ($shifts as $s) {
                                if (strpos($s['hari'], $indonesian_day) !== false) {
                                    $target_shift = $s;
                                    break;
                                }
                            }

                            if ($target_shift) {
                                $limit_masuk = $target_shift['jam_masuk'];
                                if (strtotime($jam_masuk_db) > strtotime($limit_masuk)) {
                                    $status = 'terlambat';
                                    $diff = strtotime($jam_masuk_db) - strtotime($limit_masuk);
                                    $minutes_late = floor($diff / 60);
                                    $keterangan = "Terlambat $minutes_late menit";
                                }
                            }
                        }

                        // Insert or Update DB
                        $stmt_check = $pdo->prepare("SELECT id FROM presensi WHERE user_id = ? AND tanggal = ?");
                        $stmt_check->execute([$user_id, $date_formatted]);
                        $existing = $stmt_check->fetch();

                        if ($existing) {
                            $stmt_update = $pdo->prepare("UPDATE presensi SET jam_masuk = ?, jam_keluar = ?, status = ?, keterangan = ? WHERE id = ?");
                            $stmt_update->execute([$jam_masuk_db, $jam_keluar_db, $status, $keterangan, $existing['id']]);
                        } else {
                            $stmt_insert = $pdo->prepare("INSERT INTO presensi (user_id, tanggal, jam_masuk, jam_keluar, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt_insert->execute([$user_id, $date_formatted, $jam_masuk_db, $jam_keluar_db, $status, $keterangan]);
                        }

                        $import_report[] = [
                            'row' => $i + 1,
                            'nip' => $nip_val,
                            'nama' => $nama_staf,
                            'status' => 'Sukses',
                            'pesan' => "Data presensi tanggal $date_formatted (" . ucfirst($status) . ") berhasil disimpan."
                        ];
                        $imported++;
                    }

                    $success = "Proses import selesai. Berhasil: $imported baris, Gagal/Lewat: $skipped baris.";
                } else {
                    $error = "File Excel kosong atau tidak memiliki data selain header.";
                }
            } catch (Exception $e) {
                $error = "Gagal memproses file Excel: " . $e->getMessage();
            }
        } else {
            $error = "Format file tidak valid. Harap unggah file .xls atau .xlsx.";
        }
    } else {
        $error = "Terjadi kesalahan saat mengunggah file.";
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <h4 class="fw-bold text-dark mb-1">Import Absensi Fingerprint</h4>
    <p class="text-muted mb-0">Unggah file Excel laporan presensi hasil ekspor mesin fingerprint.</p>
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
    <!-- Upload Panel -->
    <div class="col-lg-5">
        <div class="card card-custom">
            <h5 class="fw-bold text-dark mb-3">Upload File Laporan</h5>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="excel_file" class="form-label text-secondary fw-semibold">Pilih File Excel (.xls / .xlsx)</label>
                    <input class="form-control" type="file" id="excel_file" name="excel_file" accept=".xls,.xlsx" required>
                    <div class="form-text text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i> Sistem akan mencocokkan baris data berdasarkan NIP/PIN yang terdaftar di Data Pegawai.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2.5">
                    <i class="bi bi-cloud-arrow-up me-2"></i> Mulai Proses Import
                </button>
            </form>

            <div class="mt-4 pt-3 border-top">
                <h6 class="fw-bold text-dark mb-2">Format Header Excel yang Didukung:</h6>
                <ul class="text-muted small ps-3">
                    <li>Kolom NIP/PIN Pegawai: <code>NIP</code> / <code>PIN</code> / <code>ID</code></li>
                    <li>Kolom Tanggal: <code>Tanggal</code> / <code>Date</code></li>
                    <li>Kolom Scan Masuk: <code>Masuk</code> / <code>Check In</code></li>
                    <li>Kolom Scan Pulang: <code>Pulang</code> / <code>Check Out</code></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Import Report Panel -->
    <div class="col-lg-7">
        <div class="card card-custom" style="min-height: 400px;">
            <h5 class="fw-bold text-dark mb-3">Laporan Riwayat Import</h5>
            <?php if (empty($import_report)): ?>
                <div class="text-center text-muted py-5 mt-4">
                    <i class="bi bi-file-earmark-spreadsheet display-4 text-secondary mb-3"></i>
                    <p class="mb-0">Belum ada file yang diunggah dalam sesi ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 80px;">Baris</th>
                                <th>NIP</th>
                                <th>Nama Pegawai</th>
                                <th>Status</th>
                                <th>Pesan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_report as $r): ?>
                                <tr>
                                    <td><?= $r['row'] ?></td>
                                    <td><code><?= htmlspecialchars($r['nip']) ?></code></td>
                                    <td><?= htmlspecialchars($r['nama']) ?></td>
                                    <td>
                                        <span class="badge <?= $r['status'] === 'Sukses' ? 'bg-success' : 'bg-danger' ?> px-2 py-1">
                                            <?= $r['status'] ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($r['pesan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
