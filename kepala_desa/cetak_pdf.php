<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kepala_desa') {
    die("Akses ditolak.");
}

$type = $_GET['type'] ?? 'harian';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$presensi_list = [];
$periode_text = '';

// Load data based on filters
if ($type === 'harian') {
    $query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
              FROM presensi p 
              JOIN users u ON p.user_id = u.id 
              LEFT JOIN jabatan j ON u.jabatan_id = j.id
              WHERE p.tanggal = :date 
              ORDER BY u.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':date' => $date]);
    $presensi_list = $stmt->fetchAll();
    $periode_text = 'Hari/Tanggal: ' . date('d-m-Y', strtotime($date));
} elseif ($type === 'bulanan') {
    $months = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
              FROM presensi p 
              JOIN users u ON p.user_id = u.id 
              LEFT JOIN jabatan j ON u.jabatan_id = j.id
              WHERE MONTH(p.tanggal) = :month AND YEAR(p.tanggal) = :year 
              ORDER BY p.tanggal ASC, u.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':month' => $month, ':year' => $year]);
    $presensi_list = $stmt->fetchAll();
    $periode_text = 'Bulan: ' . $months[$month] . ' ' . $year;
} else {
    $query = "SELECT p.*, u.nama_lengkap, u.nip, j.nama_jabatan 
              FROM presensi p 
              JOIN users u ON p.user_id = u.id 
              LEFT JOIN jabatan j ON u.jabatan_id = j.id
              WHERE YEAR(p.tanggal) = :year 
              ORDER BY p.tanggal ASC, u.nama_lengkap ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':year' => $year]);
    $presensi_list = $stmt->fetchAll();
    $periode_text = 'Tahun: ' . $year;
}

// Fetch current logged in Kepala Desa details for signing block
$stmt_kades = $pdo->prepare("SELECT nama_lengkap, nip FROM users WHERE id = ? LIMIT 1");
$stmt_kades->execute([$_SESSION['user_id']]);
$kades = $stmt_kades->fetch();
$nama_kades = $kades ? $kades['nama_lengkap'] : $_SESSION['nama_lengkap'];
$nip_kades = ($kades && $kades['nip']) ? $kades['nip'] : '-';

// Create PDF using FPDF
class AttendancePDF extends FPDF {
    function Header() {
        // Kop Surat (Government Letterhead)
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 5, 'PEMERINTAH KABUPATEN TANJUNG JABUNG TIMUR', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 5, 'KECAMATAN BERBAK', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, 'KANTOR DESA SUNGAI RAMBUT', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 4, 'Alamat: Jalan Lintas Desa, Sungai Rambut, Kode Pos 36765', 0, 1, 'C');
        
        // Double line divider
        $this->SetLineWidth(0.8);
        $this->Line(10, 33, 200, 33);
        $this->SetLineWidth(0.2);
        $this->Line(10, 34, 200, 34);
        $this->Ln(8);
    }

    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new AttendancePDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);

// Report Title
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, 'LAPORAN PRESENSI STAF DESA', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $periode_text, 0, 1, 'C');
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 235, 245);
$pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Nama Pegawai', 1, 0, 'L', true);
$pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Jam Masuk', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Jam Pulang', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Status', 1, 0, 'C', true);
$pdf->Cell(33, 8, 'Keterangan', 1, 1, 'L', true);

// Table Data
$pdf->SetFont('Arial', '', 9);
$no = 1;
if (empty($presensi_list)) {
    $pdf->Cell(190, 8, 'Tidak ada data presensi.', 1, 1, 'C');
} else {
    foreach ($presensi_list as $p) {
        $pdf->Cell(10, 8, $no++, 1, 0, 'C');
        $pdf->Cell(50, 8, substr($p['nama_lengkap'], 0, 25), 1, 0, 'L');
        $pdf->Cell(25, 8, date('d-m-Y', strtotime($p['tanggal'])), 1, 0, 'C');
        $pdf->Cell(25, 8, $p['jam_masuk'] ? date('H:i', strtotime($p['jam_masuk'])) : '-', 1, 0, 'C');
        $pdf->Cell(25, 8, $p['jam_keluar'] ? date('H:i', strtotime($p['jam_keluar'])) : '-', 1, 0, 'C');
        $pdf->Cell(22, 8, ucfirst($p['status']), 1, 0, 'C');
        $pdf->Cell(33, 8, $p['keterangan'] ? substr($p['keterangan'], 0, 18) : '-', 1, 1, 'L');
    }
}
$pdf->Ln(15);

// Signature area
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(120);
$pdf->Cell(60, 5, 'Sungai Rambut, ' . date('d F Y'), 0, 1, 'C');
$pdf->Cell(120);
$pdf->Cell(60, 5, 'Mengetahui,', 0, 1, 'C');
$pdf->Cell(120);
$pdf->Cell(60, 5, 'Kepala Desa Sungai Rambut', 0, 1, 'C');
$pdf->Ln(20); // space for actual signature

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(120);
$pdf->Cell(60, 5, $nama_kades, 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(120);
$pdf->Cell(60, 5, 'NIP: ' . $nip_kades, 0, 1, 'C');

// Output PDF to browser
$pdf->Output('I', 'Laporan_Presensi_Staf_Desa_' . date('Ymd_His') . '.pdf');
?>
