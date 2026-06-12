<?php
require_once 'header.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$success       = '';
$error         = '';
$preview_data  = [];
$import_report = [];
$step          = 'upload';

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Parse a time value from Excel cell.
 * Handles: null, '', 'None', Excel numeric fraction, and string times.
 */
function parseTime($val): string {
    if ($val === null || $val === '') return '';
    $v = trim((string)$val);
    if ($v === '' || strtolower($v) === 'none' || $v === '-' || $v === '00:00:00' || $v === '00:00') return '';
    // Excel stores times as fraction of a day (0.0 - 1.0)
    if (is_numeric($v) && (float)$v >= 0 && (float)$v < 1) {
        $seconds = (int)round((float)$v * 86400);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
    $ts = strtotime($v);
    return ($ts !== false) ? date('H:i:s', $ts) : '';
}

/**
 * Parse a date value from Excel cell.
 * Handles: Excel numeric serial, M/D/YYYY, D/M/YYYY, YYYY-MM-DD, etc.
 */
function parseDate($val): ?string {
    if ($val === null || $val === '') return null;
    $v = trim((string)$val);
    if ($v === '') return null;

    // Excel serial date (> 40000 = year 2009+)
    if (is_numeric($v) && (float)$v > 40000) {
        try {
            return date('Y-m-d', ExcelDate::excelToTimestamp((float)$v));
        } catch (Exception $e) {}
    }

    // Try explicit formats (Deli S151 uses M/D/YYYY from screenshot)
    $formats = ['n/j/Y', 'm/d/Y', 'd/m/Y', 'Y-m-d', 'd-m-Y', 'm-d-Y', 'j/n/Y'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $v);
        if ($d && $d->format($fmt) === $v) {
            return $d->format('Y-m-d');
        }
    }

    // Fallback: strtotime
    $ts = strtotime($v);
    return ($ts !== false) ? date('Y-m-d', $ts) : null;
}

// ═══════════════════════════════════════════════════════════════════════════
// STEP 2: CONFIRM & SAVE TO DATABASE
// ═══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $step      = 'done';
    $rows_json = $_POST['rows_json'] ?? '[]';
    $rows      = json_decode($rows_json, true);

    if (!empty($rows)) {
        $imported = 0;

        foreach ($rows as $r) {
            $user_id   = (int)$r['user_id'];
            $date_fmt  = $r['tanggal'];
            $am_in     = $r['am_in']  ?: null;
            $am_out    = $r['am_out'] ?: null;
            $pm_in     = $r['pm_in']  ?: null;
            $pm_out    = $r['pm_out'] ?: null;
            $late_min  = (int)($r['late_minute']  ?? 0);
            $early_min = (int)($r['early_minute'] ?? 0);
            $status    = $r['status_db'];
            $remark    = $r['remark'] ?? '';

            // Backward-compat columns
            $jam_masuk  = $am_in  ?: null;
            $jam_keluar = $pm_out ?: ($am_out ?: null);

            // Build keterangan
            $ket_parts = [];
            if ($late_min > 0)  $ket_parts[] = "Terlambat {$late_min} menit";
            if ($early_min > 0) $ket_parts[] = "Pulang cepat {$early_min} menit";
            if ($remark !== '' && strtolower($remark) !== 'absence' && strtolower($remark) !== 'none')
                $ket_parts[] = $remark;
            $keterangan = implode('. ', $ket_parts) ?: null;

            // UPSERT
            $stmt_check = $pdo->prepare("SELECT id FROM presensi WHERE user_id = ? AND tanggal = ?");
            $stmt_check->execute([$user_id, $date_fmt]);
            $existing = $stmt_check->fetch();

            if ($existing) {
                $stmt = $pdo->prepare(
                    "UPDATE presensi SET jam_masuk=?, jam_keluar=?, am_in=?, am_out=?, pm_in=?, pm_out=?,
                     late_minute=?, early_minute=?, status=?, keterangan=? WHERE id=?"
                );
                $stmt->execute([$jam_masuk, $jam_keluar, $am_in, $am_out, $pm_in, $pm_out,
                                $late_min, $early_min, $status, $keterangan, $existing['id']]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO presensi (user_id, tanggal, jam_masuk, jam_keluar, am_in, am_out, pm_in, pm_out,
                     late_minute, early_minute, status, keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$user_id, $date_fmt, $jam_masuk, $jam_keluar, $am_in, $am_out, $pm_in, $pm_out,
                                $late_min, $early_min, $status, $keterangan]);
            }

            $import_report[] = [
                'fp_id'     => $r['fp_id'],
                'nama'      => $r['nama'],
                'tanggal'   => $date_fmt,
                'kehadiran' => ucfirst($status),
                'status'    => 'Sukses',
                'pesan'     => "Tanggal $date_fmt (" . ucfirst($status) . ") berhasil disimpan.",
            ];
            $imported++;
        }

        $success = "Import selesai! Berhasil menyimpan <strong>{$imported}</strong> baris data ke database.";

    } else {
        $error = "Tidak ada data yang valid untuk disimpan.";
    }

// ═══════════════════════════════════════════════════════════════════════════
// STEP 1: UPLOAD & PARSE EXCEL → PREVIEW
// ═══════════════════════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Gagal mengunggah file (kode error: {$file['error']}).";
    } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['xls', 'xlsx'])) {
        $error = "Format file tidak valid. Harap unggah file .xls atau .xlsx.";
    } else {
        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet       = $spreadsheet->getActiveSheet();

            // Read all cells as raw values (no formatting), keeping nulls
            $rows = $sheet->toArray(null, true, false, false);

            // ── 1. Locate header rows ────────────────────────────────────────
            // Deli S151 Abnormal Report structure:
            //   Row 0: "Abnormal Report"   (title)
            //   Row 1: "Date:XXXX~XXXX"   (date range)
            //   Row 2: User ID | Name | Department | Date | Before Noon | | After Noon | | Late(Minute) | Early(Minute) | Total(Minute) | Remark
            //   Row 3: (empty) | (empty) | (empty) | (empty) | In | Out | In | Out | ...
            //   Row 4+: Data

            $main_hdr_idx = -1;  // row with "User ID"
            $sub_hdr_idx  = -1;  // row with "In" / "Out"

            foreach ($rows as $idx => $row) {
                $c0 = strtolower(trim((string)($row[0] ?? '')));
                if (in_array($c0, ['user id', 'userid', 'id', 'no.', 'no'])) {
                    $main_hdr_idx = $idx;
                    // Check next row for In/Out sub-headers
                    if (isset($rows[$idx + 1])) {
                        $nxt = array_map(fn($v) => strtolower(trim((string)$v)), $rows[$idx + 1]);
                        if (in_array('in', $nxt) || in_array('out', $nxt)) {
                            $sub_hdr_idx = $idx + 1;
                        }
                    }
                    break;
                }
            }

            if ($main_hdr_idx === -1) {
                throw new RuntimeException(
                    "Baris header 'User ID' tidak ditemukan di file. " .
                    "Pastikan file adalah Abnormal Report dari mesin Deli S151."
                );
            }

            // ── 2. Map column indices ─────────────────────────────────────────
            $main = $rows[$main_hdr_idx];

            // Track column indices
            $c = [
                'user_id'    => -1,
                'name'       => -1,
                'department' => -1,
                'date'       => -1,
                'bn_in'      => -1,   // Before Noon In
                'bn_out'     => -1,   // Before Noon Out
                'an_in'      => -1,   // After Noon In
                'an_out'     => -1,   // After Noon Out
                'late'       => -1,
                'early'      => -1,
                'total'      => -1,
                'remark'     => -1,
            ];

            $bn_col = -1; // column where "Before Noon" starts
            $an_col = -1; // column where "After Noon" starts

            foreach ($main as $i => $h) {
                $hn = strtolower(trim((string)$h));
                if ($hn === '') continue;

                if (in_array($hn, ['user id', 'userid', 'id', 'pin']))
                    $c['user_id'] = $i;
                elseif (in_array($hn, ['name', 'nama', 'employee name', 'emp name']))
                    $c['name'] = $i;
                elseif (in_array($hn, ['department', 'dept', 'departemen', 'bagian']))
                    $c['department'] = $i;
                elseif (in_array($hn, ['date', 'tanggal', 'tgl', 'check date']))
                    $c['date'] = $i;
                elseif (strpos($hn, 'before noon') !== false || strpos($hn, 'before') !== false)
                    $bn_col = $i;
                elseif (strpos($hn, 'after noon') !== false || strpos($hn, 'after') !== false)
                    $an_col = $i;
                elseif (strpos($hn, 'late') !== false && strpos($hn, 'early') === false)
                    $c['late'] = $i;
                elseif (strpos($hn, 'early') !== false)
                    $c['early'] = $i;
                elseif (strpos($hn, 'total') !== false)
                    $c['total'] = $i;
                elseif (strpos($hn, 'remark') !== false || strpos($hn, 'keterangan') !== false)
                    $c['remark'] = $i;
            }

            // Resolve In/Out columns from sub-header row
            if ($sub_hdr_idx >= 0) {
                $sub = $rows[$sub_hdr_idx];

                // Before Noon: scan from $bn_col forward for 'in' then 'out'
                if ($bn_col >= 0) {
                    $found_in = false;
                    for ($j = $bn_col; $j <= min($bn_col + 4, count($sub) - 1); $j++) {
                        $sv = strtolower(trim((string)($sub[$j] ?? '')));
                        if ($sv === 'in' && !$found_in) { $c['bn_in'] = $j; $found_in = true; }
                        elseif ($sv === 'out' && $found_in) { $c['bn_out'] = $j; break; }
                    }
                }

                // After Noon: scan from $an_col forward for 'in' then 'out'
                if ($an_col >= 0) {
                    $found_in = false;
                    for ($j = $an_col; $j <= min($an_col + 4, count($sub) - 1); $j++) {
                        $sv = strtolower(trim((string)($sub[$j] ?? '')));
                        if ($sv === 'in' && !$found_in) { $c['an_in'] = $j; $found_in = true; }
                        elseif ($sv === 'out' && $found_in) { $c['an_out'] = $j; break; }
                    }
                }
            }

            // ── Fallback: Deli S151 fixed column layout ───────────────────────
            // A=0:UserID  B=1:Name  C=2:Department  D=3:Date
            // E=4:BN_In   F=5:BN_Out  G=6:AN_In  H=7:AN_Out
            // I=8:Late  J=9:Early  K=10:Total  L=11:Remark
            if ($c['user_id']    === -1) $c['user_id']    = 0;
            if ($c['name']       === -1) $c['name']       = 1;
            if ($c['department'] === -1) $c['department'] = 2;
            if ($c['date']       === -1) $c['date']       = 3;
            if ($c['bn_in']      === -1) $c['bn_in']      = 4;
            if ($c['bn_out']     === -1) $c['bn_out']     = 5;
            if ($c['an_in']      === -1) $c['an_in']      = 6;
            if ($c['an_out']     === -1) $c['an_out']     = 7;
            if ($c['late']       === -1) $c['late']       = 8;
            if ($c['early']      === -1) $c['early']      = 9;
            if ($c['total']      === -1) $c['total']      = 10;
            if ($c['remark']     === -1) $c['remark']     = 11;

            // ── 3. Data rows start after sub-header (or after main header) ────
            $data_start = ($sub_hdr_idx >= 0) ? $sub_hdr_idx + 1 : $main_hdr_idx + 1;

            // ── 4. Build User ID → user map ───────────────────────────────────
            $stmt_u = $pdo->query("SELECT u.id, u.nama_lengkap, u.user_id, j.nama_jabatan 
                                   FROM users u 
                                   LEFT JOIN jabatan j ON u.jabatan_id = j.id 
                                   WHERE u.user_id IS NOT NULL AND u.user_id != ''");
            $fp_map = [];
            foreach ($stmt_u->fetchAll() as $u) {
                $k = trim((string)($u['user_id'] ?? ''));
                if ($k !== '') $fp_map[$k] = $u;
            }

            // ── 5. Parse rows ─────────────────────────────────────────────────
            $valid_rows   = [];
            $invalid_rows = [];

            for ($i = $data_start; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Read User ID (col A)
                $fp_id = trim((string)($row[$c['user_id']] ?? ''));

                // Skip blanks and summary/footer rows
                if ($fp_id === '') continue;
                if (!is_numeric($fp_id)) continue;   // Footer like "Total Late/Early…"

                // Parse date
                $date_fmt = parseDate($row[$c['date']] ?? '');
                if ($date_fmt === null) continue;

                // Parse times
                $am_in  = parseTime($row[$c['bn_in']]  ?? null);
                $am_out = parseTime($row[$c['bn_out']] ?? null);
                $pm_in  = parseTime($row[$c['an_in']]  ?? null);
                $pm_out = parseTime($row[$c['an_out']] ?? null);

                // Late / Early
                $late_raw  = trim((string)($row[$c['late']]  ?? '0'));
                $early_raw = trim((string)($row[$c['early']] ?? '0'));
                $late_int  = (is_numeric($late_raw)  && strtolower($late_raw) !== 'none') ? (int)$late_raw  : 0;
                $early_int = (is_numeric($early_raw) && strtolower($early_raw) !== 'none') ? (int)$early_raw : 0;

                // Remark
                $remark = trim((string)($row[$c['remark']] ?? ''));

                // Name & Department from Excel (for display)
                $name_excel = trim((string)($row[$c['name']] ?? ''));
                $dept_excel = trim((string)($row[$c['department']] ?? ''));

                // ── Determine attendance status ────────────────────────────────
                $is_absence = (stripos($remark, 'absence') !== false);
                $has_scan   = ($am_in !== '' || $pm_in !== '' || $am_out !== '' || $pm_out !== '');

                if ($is_absence || !$has_scan) {
                    $status_preview = 'Alpha';
                    $status_db      = 'alpha';
                    $badge_cls      = 'badge-alpha';
                } elseif ($late_int > 0) {
                    $status_preview = 'Terlambat';
                    $status_db      = 'terlambat';
                    $badge_cls      = 'badge-terlambat';
                } else {
                    $status_preview = 'Hadir';
                    $status_db      = 'hadir';
                    $badge_cls      = 'badge-hadir';
                }

                // ── Validate User ID Fingerprint ──────────────────────────────
                if (!isset($fp_map[$fp_id])) {
                    $invalid_rows[] = [
                        'baris'   => $i + 1,
                        'fp_id'   => $fp_id,
                        'nama'    => $name_excel ?: '(tidak dikenal)',
                        'dept'    => $dept_excel,
                        'tanggal' => $date_fmt,
                        'remark'  => $remark,
                        'pesan'   => "User ID '{$fp_id}' belum terdaftar di Data Pegawai.",
                    ];
                    continue;
                }

                $user = $fp_map[$fp_id];

                $valid_rows[] = [
                    'fp_id'        => $fp_id,
                    'user_id'      => $user['id'],
                    'nama'         => $user['nama_lengkap'],
                    'nama_excel'   => $name_excel,
                    'department'   => $user['nama_jabatan'] ?: ($dept_excel ?: '-'),
                    'tanggal'      => $date_fmt,
                    'am_in'        => $am_in,
                    'am_out'       => $am_out,
                    'pm_in'        => $pm_in,
                    'pm_out'       => $pm_out,
                    'late_minute'  => $late_int,
                    'early_minute' => $early_int,
                    'remark'       => $remark,
                    'status'       => $status_preview,
                    'status_db'    => $status_db,
                    'badge_cls'    => $badge_cls,
                ];
            }

            $preview_data = ['valid' => $valid_rows, 'invalid' => $invalid_rows];
            $step = 'preview';

            if (empty($valid_rows) && empty($invalid_rows)) {
                $error = "Tidak ada baris data yang berhasil dibaca. Periksa kembali format file.";
                $step  = 'upload';
            }

        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = "Gagal memproses file Excel: " . $e->getMessage();
        }
    }
}
?>

<!-- ════════════════════════════════════════════════════════════════════ -->
<!-- PAGE HEADER -->
<!-- ════════════════════════════════════════════════════════════════════ -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Absensi Fingerprint
        </h4>
        <p class="text-muted mb-0">
            Import file <strong>Abnormal Report</strong> dari mesin fingerprint <strong>Deli S151</strong>.
        </p>
    </div>
    <?php if ($step === 'preview'): ?>
        <span class="badge bg-warning text-dark px-3 py-2 fs-6">
            <i class="bi bi-eye me-1"></i> Preview — Belum Disimpan
        </span>
    <?php elseif ($step === 'done'): ?>
        <span class="badge bg-success px-3 py-2 fs-6">
            <i class="bi bi-check-circle me-1"></i> Import Selesai
        </span>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>


<!-- ════════════════════════════════════════════════════════════════════ -->
<!-- STEP: UPLOAD -->
<!-- ════════════════════════════════════════════════════════════════════ -->
<?php if ($step === 'upload'): ?>
<div class="row g-4">

    <!-- Upload Card -->
    <div class="col-lg-5">
        <div class="card card-custom">
            <h5 class="fw-bold mb-4"><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>Upload File Laporan</h5>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="excel_file" class="form-label fw-semibold text-secondary">
                        Pilih File Abnormal Report (.xls / .xlsx)
                    </label>
                    <input class="form-control form-control-lg" type="file"
                           id="excel_file" name="excel_file" accept=".xls,.xlsx" required>
                    <div class="form-text mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Data akan di-<strong>preview</strong> terlebih dahulu sebelum disimpan ke database.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-search me-2"></i> Baca & Tampilkan Preview
                </button>
            </form>

            <hr class="my-4">

            <h6 class="fw-bold mb-3">Struktur File yang Didukung (Deli S151):</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kolom Excel</th>
                            <th>Nama Header</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>A</td><td><code>User ID</code></td><td>ID di mesin fingerprint</td></tr>
                        <tr><td>B</td><td><code>Name</code></td><td>Nama pegawai</td></tr>
                        <tr><td>C</td><td><code>Department</code></td><td>Bagian / bidang</td></tr>
                        <tr><td>D</td><td><code>Date</code></td><td>Tanggal presensi</td></tr>
                        <tr><td>E</td><td><code>Before Noon → In</code></td><td>Scan masuk pagi</td></tr>
                        <tr><td>F</td><td><code>Before Noon → Out</code></td><td>Scan keluar pagi</td></tr>
                        <tr><td>G</td><td><code>After Noon → In</code></td><td>Scan masuk siang</td></tr>
                        <tr><td>H</td><td><code>After Noon → Out</code></td><td>Scan keluar sore</td></tr>
                        <tr><td>I</td><td><code>Late (Minute)</code></td><td>Keterlambatan (menit)</td></tr>
                        <tr><td>J</td><td><code>Early (Minute)</code></td><td>Pulang cepat (menit)</td></tr>
                        <tr><td>K</td><td><code>Total (Minute)</code></td><td>Total menit</td></tr>
                        <tr><td>L</td><td><code>Remark</code></td><td>"Absence" = Alpha</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info mt-3 mb-0 py-2 px-3 small">
                <i class="bi bi-lightbulb-fill me-1"></i>
                Pastikan <strong>User ID Fingerprint</strong> setiap pegawai sudah diisi di menu
                <a href="pegawai.php" class="fw-semibold">Data Pegawai</a> agar pencocokan data berhasil.
            </div>
        </div>
    </div>

    <!-- Guide Card -->
    <div class="col-lg-7">
        <div class="card card-custom h-100">
            <h5 class="fw-bold mb-4"><i class="bi bi-diagram-3 me-2 text-info"></i>Alur Import Absensi</h5>
            <div class="d-flex flex-column gap-3">
                <?php
                $steps = [
                    ['bi-upload',          'primary', 'Upload File Excel',          'Pilih file Abnormal Report hasil export dari mesin Deli S151 (.xls/.xlsx).'],
                    ['bi-file-earmark-text','info',   'Baca File (PHPSpreadsheet)', 'Sistem membaca otomatis struktur header dan baris data, termasuk penanganan merged cell.'],
                    ['bi-eye',             'warning', 'Tampilkan Preview Data',      'Seluruh data ditampilkan dalam tabel sebelum disimpan. Baris bermasalah dipisahkan.'],
                    ['bi-fingerprint',     'secondary','Validasi User ID Pegawai',   'Sistem mencocokkan User ID dari file Excel dengan data pegawai yang terdaftar.'],
                    ['bi-check2-circle',   'success', 'Konfirmasi Import',           'Admin memeriksa preview dan mengklik konfirmasi untuk menyetujui penyimpanan.'],
                    ['bi-database-check',  'danger',  'Simpan ke Database',          'Data yang valid disimpan. Jika sudah ada (user+tanggal sama), data akan di-update otomatis.'],
                ];
                foreach ($steps as $idx => [$icon, $color, $title, $desc]): ?>
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0 me-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
                             style="width:38px;height:38px;background:var(--bs-<?= $color ?>);">
                            <?= $idx + 1 ?>
                        </div>
                    </div>
                    <div class="flex-grow-1 pb-3 <?= $idx < count($steps)-1 ? 'border-bottom' : '' ?>">
                        <div class="fw-semibold text-dark mb-1">
                            <i class="bi <?= $icon ?> me-1 text-<?= $color ?>"></i><?= $title ?>
                        </div>
                        <p class="text-muted small mb-0"><?= $desc ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════════ -->
<!-- STEP: PREVIEW -->
<!-- ════════════════════════════════════════════════════════════════════ -->
<?php elseif ($step === 'preview'):
    $valid   = $preview_data['valid'];
    $invalid = $preview_data['invalid'];
?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded-3 border"
             style="background:rgba(46,204,113,0.08);border-color:rgba(46,204,113,0.3)!important;">
            <div class="fw-bold fs-2" style="color:#27AE60;"><?= count($valid) ?></div>
            <div class="small text-muted fw-semibold">Siap Diimport</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded-3 border"
             style="background:rgba(231,76,60,0.08);border-color:rgba(231,76,60,0.3)!important;">
            <div class="fw-bold fs-2" style="color:#C0392B;"><?= count($invalid) ?></div>
            <div class="small text-muted fw-semibold">Bermasalah</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded-3 border"
             style="background:rgba(230,126,34,0.08);border-color:rgba(230,126,34,0.3)!important;">
            <div class="fw-bold fs-2" style="color:#D35400;">
                <?= count(array_filter($valid, fn($v) => $v['status_db'] === 'terlambat')) ?>
            </div>
            <div class="small text-muted fw-semibold">Terlambat</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded-3 border"
             style="background:rgba(30,58,95,0.06);border-color:rgba(30,58,95,0.12)!important;">
            <div class="fw-bold fs-2 text-primary"><?= count($valid) + count($invalid) ?></div>
            <div class="small text-muted fw-semibold">Total Dibaca</div>
        </div>
    </div>
</div>

<!-- Valid Rows -->
<?php if (!empty($valid)): ?>
<div class="card card-custom mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-success">
            <i class="bi bi-check-circle-fill me-2"></i>Data Valid – Siap Diimport
        </h5>
        <span class="badge bg-success px-3 py-2"><?= count($valid) ?> baris</span>
    </div>
    <div class="table-responsive" style="max-height:450px;overflow-y:auto;">
        <table class="table table-hover table-sm align-middle mb-0" style="font-size:.82rem;">
            <thead class="table-light sticky-top">
                <tr>
                    <th>No</th>
                    <th>User ID</th>
                    <th>Nama (Database)</th>
                    <th>Nama (Excel)</th>
                    <th>Department</th>
                    <th>Tanggal</th>
                    <th class="text-center" style="background:rgba(46,204,113,0.08)">BN In</th>
                    <th class="text-center" style="background:rgba(46,204,113,0.08)">BN Out</th>
                    <th class="text-center" style="background:rgba(52,152,219,0.08)">AN In</th>
                    <th class="text-center" style="background:rgba(52,152,219,0.08)">AN Out</th>
                    <th class="text-center"><span class="text-danger">Late</span></th>
                    <th class="text-center"><span class="text-warning">Early</span></th>
                    <th>Remark</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($valid as $v): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-2">
                            <?= htmlspecialchars($v['fp_id']) ?>
                        </span>
                    </td>
                    <td class="fw-semibold"><?= htmlspecialchars($v['nama']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($v['nama_excel']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($v['department']) ?></td>
                    <td><?= date('d/m/Y', strtotime($v['tanggal'])) ?></td>
                    <td class="text-center" style="background:rgba(46,204,113,0.04)">
                        <?= $v['am_in']  ? '<span class="text-success fw-semibold">'.substr($v['am_in'],0,5).'</span>' : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td class="text-center" style="background:rgba(46,204,113,0.04)">
                        <?= $v['am_out'] ? substr($v['am_out'],0,5) : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td class="text-center" style="background:rgba(52,152,219,0.04)">
                        <?= $v['pm_in']  ? substr($v['pm_in'],0,5)  : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td class="text-center" style="background:rgba(52,152,219,0.04)">
                        <?= $v['pm_out'] ? '<span class="text-primary fw-semibold">'.substr($v['pm_out'],0,5).'</span>' : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td class="text-center">
                        <?= $v['late_minute']  > 0
                            ? '<span class="badge bg-danger bg-opacity-15 text-danger">'.$v['late_minute'].'</span>'
                            : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-center">
                        <?= $v['early_minute'] > 0
                            ? '<span class="badge bg-warning bg-opacity-15 text-warning">'.$v['early_minute'].'</span>'
                            : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td>
                        <small class="<?= strtolower($v['remark']) === 'absence' ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?= htmlspecialchars($v['remark']) ?: '-' ?>
                        </small>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $v['badge_cls'] ?> rounded-pill px-2 py-1">
                            <?= $v['status'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Invalid / Unmatched Rows -->
<?php if (!empty($invalid)): ?>
<div class="card card-custom mb-4" style="border-left:4px solid #E74C3C;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Data Bermasalah – Akan Dilewati
        </h5>
        <span class="badge bg-danger px-3 py-2"><?= count($invalid) ?> baris</span>
    </div>
    <p class="text-muted small mb-3">
        Baris-baris berikut tidak dapat diimport karena User ID tidak ditemukan di database.
        Silakan tambahkan pegawai tersebut di menu <a href="pegawai.php">Data Pegawai</a> terlebih dahulu,
        lalu ulangi import.
    </p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Baris</th><th>User ID</th><th>Nama (Excel)</th>
                    <th>Department</th><th>Tanggal</th><th>Remark</th><th>Penyebab</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invalid as $inv): ?>
                <tr>
                    <td><?= $inv['baris'] ?></td>
                    <td><code class="text-danger"><?= htmlspecialchars($inv['fp_id']) ?></code></td>
                    <td><?= htmlspecialchars($inv['nama']) ?></td>
                    <td><?= htmlspecialchars($inv['dept'] ?? '-') ?></td>
                    <td><?= $inv['tanggal'] ? date('d/m/Y', strtotime($inv['tanggal'])) : '-' ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($inv['remark'] ?? '-') ?></td>
                    <td class="text-danger small"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($inv['pesan']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="card card-custom">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
            <h6 class="fw-bold mb-1">Konfirmasi Import</h6>
            <p class="text-muted small mb-0">
                <?php if (!empty($valid)): ?>
                    <span class="text-success fw-semibold"><?= count($valid) ?> baris</span> siap disimpan ke database.
                    <?php if (!empty($invalid)): ?>
                        <span class="text-danger fw-semibold"><?= count($invalid) ?> baris</span> akan dilewati.
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-danger">Tidak ada data valid. Periksa kembali User ID di Data Pegawai.</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="import.php" class="btn btn-outline-secondary px-4">
                <i class="bi bi-arrow-left me-2"></i>Upload Ulang
            </a>
            <?php if (!empty($valid)): ?>
            <form method="POST" action="">
                <input type="hidden" name="confirm_import" value="1">
                <input type="hidden" name="rows_json"
                       value="<?= htmlspecialchars(json_encode($valid), ENT_QUOTES) ?>">
                <button type="submit" class="btn btn-success btn-lg px-4">
                    <i class="bi bi-cloud-check me-2"></i>
                    Konfirmasi &amp; Simpan <?= count($valid) ?> Data
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════════ -->
<!-- STEP: DONE -->
<!-- ════════════════════════════════════════════════════════════════════ -->
<?php elseif ($step === 'done'): ?>

<div class="card card-custom">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-journal-check me-2 text-success"></i>Hasil Import
        </h5>
        <a href="import.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-upload me-1"></i> Import File Baru
        </a>
    </div>

    <?php if (!empty($import_report)): ?>
    <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>User ID</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal</th>
                    <th class="text-center">Status Kehadiran</th>
                    <th>Pesan</th>
                    <th class="text-center">Hasil</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($import_report as $r): ?>
                <tr>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">
                            <?= htmlspecialchars($r['fp_id']) ?>
                        </span>
                    </td>
                    <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></td>
                    <td><?= $r['tanggal'] ? date('d/m/Y', strtotime($r['tanggal'])) : '-' ?></td>
                    <td class="text-center">
                        <?php
                        $bm = ['hadir'=>'badge-hadir','terlambat'=>'badge-terlambat','alpha'=>'badge-alpha'];
                        $bc2 = $bm[strtolower($r['kehadiran'])] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $bc2 ?> px-2 py-1 rounded-pill"><?= $r['kehadiran'] ?></span>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($r['pesan']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-success px-2"><?= $r['status'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="mt-4 d-flex gap-2">
        <a href="presensi.php" class="btn btn-primary">
            <i class="bi bi-calendar-check me-1"></i> Lihat Data Presensi
        </a>
        <a href="laporan.php" class="btn btn-outline-secondary">
            <i class="bi bi-journal-text me-1"></i> Lihat Laporan
        </a>
        <a href="import.php" class="btn btn-outline-primary">
            <i class="bi bi-upload me-1"></i> Import File Baru
        </a>
    </div>
</div>

<?php endif; ?>

<?php require_once 'footer.php'; ?>