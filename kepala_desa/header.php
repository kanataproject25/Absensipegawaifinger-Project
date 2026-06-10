<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kepala_desa') {
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
    <title>Dashboard Kepala Desa - Presensi Staf Desa Sungai Rambut</title>
    <meta name="description" content="Dashboard Kepala Desa untuk memantau presensi staf Desa Sungai Rambut secara real-time.">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary:       #1B4F72;
            --primary-dark:  #0E2F45;
            --primary-light: #2E86C1;
            --accent:        #F39C12;
            --secondary-bg:  #F0F4F8;
            --text-dark:     #1A2A3A;
            --text-muted:    #6C7F8E;
            --color-hadir:   #27AE60;
            --color-terlambat: #E67E22;
            --color-alpha:   #E74C3C;
            --sidebar-w:     265px;
            --card-radius:   16px;
            --shadow-soft:   0 4px 24px rgba(27, 79, 114, 0.08);
            --shadow-hover:  0 8px 32px rgba(27, 79, 114, 0.15);
            --transition:    all 0.25s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-dark);
            margin: 0;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(27,79,114,.25); border-radius: 99px; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(175deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            min-height: 100vh;
            position: fixed;
            left: 0; top: 0;
            padding: 1.75rem 1.25rem;
            z-index: 200;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,.06);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding-bottom: 1.25rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .sidebar-brand .brand-icon {
            width: 42px; height: 42px;
            background: rgba(255,255,255,.15);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .sidebar-brand h5 { font-size: .95rem; font-weight: 700; margin: 0; line-height: 1.3; }
        .sidebar-brand small { font-size: .72rem; color: rgba(255,255,255,.55); }

        .sidebar .section-label {
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .08em;
            color: rgba(255,255,255,.4);
            text-transform: uppercase;
            padding: .5rem .75rem .25rem;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,.65);
            border-radius: 10px;
            padding: .65rem .9rem;
            margin-bottom: .2rem;
            transition: var(--transition);
            font-weight: 500;
            font-size: .88rem;
            display: flex;
            align-items: center;
            gap: .65rem;
        }
        .sidebar .nav-link i { font-size: 1.05rem; flex-shrink: 0; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,.13);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,.18);
            box-shadow: 0 2px 10px rgba(0,0,0,.15);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,.12);
        }

        .sidebar-user {
            display: flex; align-items: center; gap: .65rem;
            margin-bottom: .85rem;
        }
        .sidebar-user .avatar {
            width: 38px; height: 38px;
            background: rgba(255,255,255,.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .sidebar-user h6 { font-size: .82rem; font-weight: 600; margin: 0; color: #fff; }
        .sidebar-user small { font-size: .7rem; color: rgba(255,255,255,.5); }

        .btn-logout {
            display: flex; align-items: center; justify-content: center; gap: .45rem;
            padding: .55rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.2);
            color: rgba(255,255,255,.8);
            font-size: .82rem; font-weight: 500;
            background: transparent;
            width: 100%;
            transition: var(--transition);
            text-decoration: none;
        }
        .btn-logout:hover {
            background: rgba(231,76,60,.3);
            border-color: rgba(231,76,60,.5);
            color: #fff;
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-w);
            padding: 2rem 2.25rem;
            min-height: 100vh;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 1.35rem 1.75rem;
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h4 { font-size: 1.15rem; font-weight: 700; margin: 0; }
        .page-header .date-badge {
            display: flex; align-items: center; gap: .4rem;
            background: var(--secondary-bg);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: .4rem .85rem;
            font-size: .8rem; font-weight: 500; color: var(--text-muted);
        }

        /* ── PANEL CARD (generic white card) ── */
        .panel-card {
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-soft);
            border: none;
            transition: var(--transition);
        }
        .panel-card:hover { box-shadow: var(--shadow-hover); }

        /* ── METRIC CARDS ── */
        .metric-card {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 1.5rem 1.6rem;
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        .metric-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
        .metric-card.total    { border-left-color: var(--primary-light); }
        .metric-card.hadir    { border-left-color: var(--color-hadir); }
        .metric-card.terlambat{ border-left-color: var(--color-terlambat); }
        .metric-card.alpha    { border-left-color: var(--color-alpha); }

        .metric-card .metric-icon {
            position: absolute; right: 1.2rem; top: 50%;
            transform: translateY(-50%);
            font-size: 2.8rem;
            opacity: .1;
        }
        .metric-card h6 { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-bottom: .5rem; }
        .metric-card h2 { font-size: 2rem; font-weight: 800; margin: 0; line-height: 1; }
        .metric-card .metric-label { font-size: .72rem; color: var(--text-muted); margin-top: .35rem; }

        /* ── BADGES ── */
        .badge-hadir     { background: rgba(39,174,96,.12);  color: #1e8449; padding: .3rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-terlambat { background: rgba(230,126,34,.12); color: #b9770e; padding: .3rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-alpha     { background: rgba(231,76,60,.12);  color: #c0392b; padding: .3rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-sakit     { background: rgba(52,152,219,.12); color: #1a5276; padding: .3rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-izin      { background: rgba(155,89,182,.12); color: #6c3483; padding: .3rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 600; }

        /* ── TABLE ── */
        .table-custom thead th {
            background: var(--secondary-bg);
            font-size: .73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--text-muted);
            border: none; padding: .75rem 1rem;
        }
        .table-custom tbody td { padding: .75rem 1rem; vertical-align: middle; font-size: .87rem; border-color: #f0f4f8; }
        .table-custom tbody tr:hover td { background: #fafbfd; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="bi bi-building-check"></i></div>
            <div>
                <h5>Presensi Staf</h5>
                <small>Desa Sungai Rambut</small>
            </div>
        </div>

        <div class="section-label">Menu</div>
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'monitoring.php') ? 'active' : '' ?>" href="monitoring.php">
                    <i class="bi bi-calendar-check"></i> Monitoring Presensi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'laporan.php') ? 'active' : '' ?>" href="laporan.php">
                    <i class="bi bi-journal-text"></i> Laporan Presensi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'profil.php') ? 'active' : '' ?>" href="profil.php">
                    <i class="bi bi-person-bounding-box"></i> Profil Saya
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="avatar"><i class="bi bi-person-circle"></i></div>
                <div>
                    <h6><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h6>
                    <small>Kepala Desa</small>
                </div>
            </div>
            <a href="../logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-left"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="main-content">
