<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staf') {
    header("Location: ../login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staf - Presensi Staf Desa Sungai Rambut</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1E3A5F;
            --secondary-bg: #F5F7FA;
            --text-dark: #2A3B50;
            --color-hadir: #2ECC71;
            --color-terlambat: #E67E22;
            --color-alpha: #E74C3C;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-dark);
            margin: 0;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1E3A5F 0%, #112237 100%);
            color: #FFFFFF;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 2rem 1.5rem;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.35rem;
            transition: all 0.2s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #FFFFFF;
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Main Content Container */
        .main-content {
            margin-left: 260px;
            padding: 2.5rem;
            min-height: 100vh;
        }

        /* Header Card */
        .page-header {
            background-color: #FFFFFF;
            border-radius: 15px;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px rgba(30, 58, 95, 0.03);
            margin-bottom: 2rem;
        }

        /* Standard Cards */
        .card-custom {
            background-color: #FFFFFF;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(30, 58, 95, 0.03);
            border: none;
            margin-bottom: 1.5rem;
        }

        /* Metric Cards */
        .metric-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(30, 58, 95, 0.05);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(30, 58, 95, 0.12);
        }

        .metric-card .metric-icon {
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2.5rem;
            opacity: 0.15;
        }

        .metric-card.total { border-left: 4px solid var(--primary-color); }
        .metric-card.hadir { border-left: 4px solid var(--color-hadir); }
        .metric-card.terlambat { border-left: 4px solid var(--color-terlambat); }
        .metric-card.persen { border-left: 4px solid #3498DB; }

        /* Panel Cards */
        .panel-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(30, 58, 95, 0.05);
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        /* Custom Badges */
        .badge-hadir { background-color: rgba(46, 204, 113, 0.15); color: #27AE60; }
        .badge-terlambat { background-color: rgba(230, 126, 34, 0.15); color: #D35400; }
        .badge-alpha { background-color: rgba(231, 76, 60, 0.15); color: #C0392B; }
        .badge-sakit { background-color: rgba(52, 152, 219, 0.15); color: #2980B9; }
        .badge-izin { background-color: rgba(155, 89, 182, 0.15); color: #8E44AD; }

        /* Progress bar custom */
        .progress-custom {
            height: 10px;
            border-radius: 50px;
            background-color: #e9ecef;
        }
        .progress-custom .progress-bar {
            border-radius: 50px;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <i class="bi bi-person-badge text-white fs-3 me-2"></i>
            <div>
                <h5 class="fw-bold mb-0 text-white">Presensi Staf</h5>
                <small class="text-white-50 fs-7">Sungai Rambut</small>
            </div>
        </div>

        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'riwayat.php') ? 'active' : '' ?>" href="riwayat.php">
                    <i class="bi bi-calendar-check"></i> Riwayat Presensi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'profil.php') ? 'active' : '' ?>" href="profil.php">
                    <i class="bi bi-person-circle"></i> Profil Saya
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'pengajuan.php') ? 'active' : '' ?>" href="pengajuan.php">
                    <i class="bi bi-envelope-paper"></i> Pengajuan Izin/Sakit
                </a>
            </li>
        </ul>

        <div class="mt-auto pt-3 border-top border-secondary border-opacity-25">
            <div class="d-flex align-items-center mb-3">
                <div class="avatar-circle bg-secondary bg-opacity-50 text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                    <i class="bi bi-person-circle fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-0 text-white"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h6>
                    <small class="text-white-50">Staf</small>
                </div>
            </div>
            <a href="../logout.php" class="btn btn-outline-light btn-sm w-100 py-2">
                <i class="bi bi-box-arrow-left me-2"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content wrapper starts -->
    <div class="main-content">
