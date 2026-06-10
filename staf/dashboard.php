<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staf') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staf - Presensi Staf Desa Sungai Rambut</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #F5F7FA; }
        .hero { background: linear-gradient(135deg, #1E3A5F 0%, #112237 100%); color: white; padding: 4rem 2rem; border-radius: 20px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="hero text-center mb-5">
            <h1 class="fw-bold">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h1>
            <p class="lead">Dashboard Staf Kantor Desa Sungai Rambut</p>
            <div class="mt-4">
                <a href="../logout.php" class="btn btn-light"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a>
            </div>
        </div>
        <div class="card p-4 text-center">
            <h5>Halaman Dashboard Staf sedang dalam pengembangan.</h5>
            <p class="text-muted mb-0">Halaman ini nantinya akan berisi riwayat kehadiran Anda dan statistik kehadiran bulanan pribadi.</p>
        </div>
    </div>
</body>
</html>
