<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Fpdf\Fpdf as FPDF;

// Authentication Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'kepala_desa'])) {
    die("Akses ditolak.");
}

$filter_month = $_GET['month'] ?? date('m');
$filter_year = $_GET['year'] ?? date('Y');
$filter_user_id = $_GET['user_id'] ?? '';

$months = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];

$query = "SELECT p.*, u.nama_lengkap, u.nip, u.user_id, j.nama_jabatan 
          FROM presensi p 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN jabatan j ON u.jabatan_id = j.id
          WHERE MONTH(p.tanggal) = :month AND YEAR(p.tanggal) = :year";

$params = [':month' => $filter_month, ':year' => $filter_year];

if (!empty($filter_user_id)) {
    $query .= " AND p.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}
$query .= " ORDER BY p.tanggal ASC, u.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$presensi_list = $stmt->fetchAll();

// Summary Rekap
$rekap_hadir = 0;
$rekap_terlambat = 0;
$rekap_alpha = 0;
$rekap_sakit = 0;
$rekap_izin = 0;
$total_late_min = 0;
$total_early_min = 0;

foreach ($presensi_list as $p) {
    switch ($p['status']) {
        case 'hadir':
            $rekap_hadir++;
            break;
        case 'terlambat':
            $rekap_terlambat++;
            break;
        case 'alpha':
            $rekap_alpha++;
            break;
        case 'sakit':
            $rekap_sakit++;
            break;
        case 'izin':
            $rekap_izin++;
            break;
    }
    $total_late_min += (int) ($p['late_minute'] ?? 0);
    $total_early_min += (int) ($p['early_minute'] ?? 0);
}

// Kepala Desa for signing block
$stmt_kades = $pdo->query("SELECT nama_lengkap, nip FROM users WHERE role = 'kepala_desa' LIMIT 1");
$kades = $stmt_kades->fetch();
$nama_kades = $kades ? $kades['nama_lengkap'] : 'H. Ahmad Syarifuddin, S.E.';
$nip_kades = $kades ? $kades['nip'] : '-';

$periode_text = 'Periode: ' . $months[$filter_month] . ' ' . $filter_year;

// ── FPDF PDF CLASS ────────────────────────────────────────────────────────────
class AttendancePDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 5, 'PEMERINTAH KABUPATEN TANJUNG JABUNG TIMUR', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, 'KECAMATAN BERBAK', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, 'KANTOR DESA SUNGAI RAMBUT', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8.5);
        $this->Cell(0, 4, 'Alamat: Jalan Lintas Desa, Sungai Rambut, Kode Pos 36765', 0, 1, 'C');

        $this->SetLineWidth(0.8);
        $this->Line(10, 33, 287, 33);
        $this->SetLineWidth(0.2);
        $this->Line(10, 34.2, 287, 34.2);
        $this->Ln(8);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new AttendancePDF('L', 'mm', 'A4'); // Landscape for more columns
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);

// Title
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, 'LAPORAN PRESENSI STAF DESA SUNGAI RAMBUT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $periode_text, 0, 1, 'C');
$pdf->Ln(4);

// ── REKAP SUMMARY ─────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 235, 245);
$pdf->Cell(267, 6, 'REKAP KEHADIRAN', 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(38.14, 7, 'Total Record', 1, 0, 'C', true);
$pdf->Cell(38.14, 7, 'Hadir', 1, 0, 'C', true);
$pdf->Cell(38.14, 7, 'Terlambat', 1, 0, 'C', true);
$pdf->Cell(38.14, 7, 'Alpha', 1, 0, 'C', true);
$pdf->Cell(38.14, 7, 'Sakit', 1, 0, 'C', true);
$pdf->Cell(38.14, 7, 'Izin', 1, 0, 'C', true);
$pdf->Cell(38.14, 7, 'Total Terlambat', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(38.14, 8, count($presensi_list), 1, 0, 'C');
$pdf->Cell(38.14, 8, $rekap_hadir, 1, 0, 'C');
$pdf->Cell(38.14, 8, $rekap_terlambat, 1, 0, 'C');
$pdf->Cell(38.14, 8, $rekap_alpha, 1, 0, 'C');
$pdf->Cell(38.14, 8, $rekap_sakit, 1, 0, 'C');
$pdf->Cell(38.14, 8, $rekap_izin, 1, 0, 'C');
$pdf->Cell(38.14, 8, $total_late_min . ' mnt', 1, 1, 'C');
$pdf->Ln(6);

// ── GROUP BY EMPLOYEE ─────────────────────────────────────────────────────────
$employees = [];
foreach ($presensi_list as $p) {
    $uid = $p['user_id'];
    if (!isset($employees[$uid])) {
        $employees[$uid] = [
            'nama_lengkap' => $p['nama_lengkap'],
            'nip' => $p['nip'] ?? '-',
            'nama_jabatan' => $p['nama_jabatan'] ?? 'Staf',
            'hadir' => 0,
            'terlambat' => 0,
            'alpha' => 0,
            'sakit' => 0,
            'izin' => 0,
            'late_minute' => 0,
            'early_minute' => 0
        ];
    }
    switch ($p['status']) {
        case 'hadir':
            $employees[$uid]['hadir']++;
            break;
        case 'terlambat':
            $employees[$uid]['terlambat']++;
            break;
        case 'alpha':
            $employees[$uid]['alpha']++;
            break;
        case 'sakit':
            $employees[$uid]['sakit']++;
            break;
        case 'izin':
            $employees[$uid]['izin']++;
            break;
    }
    $employees[$uid]['late_minute'] += (int) ($p['late_minute'] ?? 0);
    $employees[$uid]['early_minute'] += (int) ($p['early_minute'] ?? 0);
}

// ── TABLE HEADER ──────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(230, 235, 245);

// Col widths: No | Nama | Jabatan | Hadir | Terlambat | Alpha | Sakit | Izin | Late | Early
$cw = [10, 70, 55, 20, 22, 20, 20, 20, 15, 15];

$pdf->Cell($cw[0], 8, 'No', 1, 0, 'C', true);
$pdf->Cell($cw[1], 8, 'Nama Pegawai', 1, 0, 'L', true);
$pdf->Cell($cw[2], 8, 'Jabatan', 1, 0, 'L', true);
$pdf->Cell($cw[3], 8, 'Hadir', 1, 0, 'C', true);
$pdf->Cell($cw[4], 8, 'Terlambat', 1, 0, 'C', true);
$pdf->Cell($cw[5], 8, 'Alpha', 1, 0, 'C', true);
$pdf->Cell($cw[6], 8, 'Sakit', 1, 0, 'C', true);
$pdf->Cell($cw[7], 8, 'Izin', 1, 0, 'C', true);
$pdf->Cell($cw[8], 8, 'Late(m)', 1, 0, 'C', true);
$pdf->Cell($cw[9], 8, 'Early(m)', 1, 1, 'C', true);

// ── TABLE DATA ────────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', '', 8.5);
$no = 1;
if (empty($employees)) {
    $pdf->Cell(array_sum($cw), 8, 'Tidak ada data presensi.', 1, 1, 'C');
} else {
    $row_count = 0;
    foreach ($employees as $emp) {
        if ($row_count >= 20) {
            $pdf->AddPage();
            // Reprint table header
            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetFillColor(230, 235, 245);
            $pdf->Cell($cw[0], 8, 'No', 1, 0, 'C', true);
            $pdf->Cell($cw[1], 8, 'Nama Pegawai', 1, 0, 'L', true);
            $pdf->Cell($cw[2], 8, 'Jabatan', 1, 0, 'L', true);
            $pdf->Cell($cw[3], 8, 'Hadir', 1, 0, 'C', true);
            $pdf->Cell($cw[4], 8, 'Terlambat', 1, 0, 'C', true);
            $pdf->Cell($cw[5], 8, 'Alpha', 1, 0, 'C', true);
            $pdf->Cell($cw[6], 8, 'Sakit', 1, 0, 'C', true);
            $pdf->Cell($cw[7], 8, 'Izin', 1, 0, 'C', true);
            $pdf->Cell($cw[8], 8, 'Late(m)', 1, 0, 'C', true);
            $pdf->Cell($cw[9], 8, 'Early(m)', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 8.5);
            $row_count = 0;
        }
        $row_count++;

        $nama = mb_substr($emp['nama_lengkap'], 0, 30);
        $jabatan = mb_substr($emp['nama_jabatan'], 0, 25);
        $late = $emp['late_minute'];
        $early = $emp['early_minute'];

        $pdf->Cell($cw[0], 7.5, $no++, 1, 0, 'C');
        $pdf->Cell($cw[1], 7.5, $nama, 1, 0, 'L');
        $pdf->Cell($cw[2], 7.5, $jabatan, 1, 0, 'L');
        $pdf->Cell($cw[3], 7.5, $emp['hadir'], 1, 0, 'C');
        $pdf->Cell($cw[4], 7.5, $emp['terlambat'], 1, 0, 'C');
        $pdf->Cell($cw[5], 7.5, $emp['alpha'], 1, 0, 'C');
        $pdf->Cell($cw[6], 7.5, $emp['sakit'], 1, 0, 'C');
        $pdf->Cell($cw[7], 7.5, $emp['izin'], 1, 0, 'C');
        $pdf->Cell($cw[8], 7.5, $late > 0 ? $late : '-', 1, 0, 'C');
        $pdf->Cell($cw[9], 7.5, $early > 0 ? $early : '-', 1, 1, 'C');
    }
}

// Check if there is enough space on the current page for the signature block
if ($pdf->GetY() + 40 > $pdf->GetPageHeight() - 15) {
    $pdf->AddPage();
} else {
    $pdf->Ln(8);
}

// ── SIGNATURE AREA ────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(180);
$pdf->Cell(80, 5, 'Sungai Rambut, ' . date('d F Y'), 0, 1, 'C');
$pdf->Cell(180);
$pdf->Cell(80, 5, 'Mengetahui,', 0, 1, 'C');
$pdf->Cell(180);
$pdf->Cell(80, 5, 'Kepala Desa Sungai Rambut', 0, 1, 'C');
$pdf->Ln(20);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(180);
$pdf->Cell(80, 5, $nama_kades, 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(180);
$pdf->Cell(80, 5, 'NIP: ' . $nip_kades, 0, 1, 'C');

$pdf->Output('I', 'Laporan_Presensi_' . $months[$filter_month] . '_' . $filter_year . '.pdf');
?>