<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] === 'kepala_desa') {
                    header("Location: kepala_desa/dashboard.php");
                } else {
                    header("Location: staf/dashboard.php");
                }
                exit;
            } else {
                $error = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    } else {
        $error = 'Semua kolom wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Presensi Staf Desa Sungai Rambut</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #F5F7FA;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
            position: relative;
        }

        /* Decorative background shapes */
        .bg-shape-1 {
            position: absolute;
            top: -10%;
            left: -10%;
            width: 40%;
            height: 40%;
            background: radial-gradient(circle, rgba(30,58,95,0.08) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
            border-radius: 50%;
        }

        .bg-shape-2 {
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 50%;
            height: 50%;
            background: radial-gradient(circle, rgba(30,58,95,0.06) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
            border-radius: 50%;
        }

        .login-container {
            background-color: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(30, 58, 95, 0.08);
            overflow: hidden;
            max-width: 950px;
            width: 100%;
            min-height: 550px;
        }

        .brand-side {
            background: linear-gradient(135deg, #1E3A5F 0%, #112237 100%);
            color: #FFFFFF;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-side::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        .form-side {
            padding: 3.5rem 3rem;
        }

        .btn-primary {
            background-color: #1E3A5F;
            border-color: #1E3A5F;
            font-weight: 500;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #152943;
            border-color: #152943;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 95, 0.2);
        }

        .form-control:focus {
            border-color: #1E3A5F;
            box-shadow: 0 0 0 0.25rem rgba(30, 58, 95, 0.15);
        }

        .badge-gov {
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #FFFFFF;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="bg-shape-1"></div>
    <div class="bg-shape-2"></div>

    <div class="container d-flex justify-content-center align-items-center">
        <div class="login-container row g-0">
            <!-- Brand Side -->
            <div class="col-lg-5 brand-side d-none d-lg-flex">
                <div>
                    <span class="badge-gov mb-4">
                        <i class="bi bi-shield-check me-2"></i>Sistem Resmi Pemerintahan
                    </span>
                    <h2 class="fw-bold mb-3">Presensi Staf Desa</h2>
                    <p class="text-white-50 leading-relaxed">
                        Kantor Desa Sungai Rambut<br>
                        Kecamatan Berbak, Kabupaten Tanjung Jabung Timur, Provinsi Jambi.
                    </p>
                </div>
                <div class="mt-auto">
                    <small class="text-white-50">© 2026 Pemerintah Desa Sungai Rambut. All rights reserved.</small>
                </div>
            </div>

            <!-- Form Side -->
            <div class="col-lg-7 form-side d-flex flex-column justify-content-center">
                <div class="mb-4 text-center text-lg-start">
                    <!-- Mobile Logo Indicator -->
                    <div class="d-lg-none mb-3">
                        <span class="badge bg-primary px-3 py-2" style="background-color: #1E3A5F !important;">
                            Presensi Staf Desa
                        </span>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Selamat Datang</h3>
                    <p class="text-muted">Silakan masukkan username dan password Anda untuk masuk ke sistem.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label text-secondary fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control py-2" id="username" name="username" placeholder="Masukkan username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label text-secondary fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control py-2" id="password" name="password" placeholder="Masukkan password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Dashboard
                    </button>
                </form>

                <div class="mt-4 text-center d-lg-none">
                    <small class="text-muted">© 2026 Pemerintah Desa Sungai Rambut.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
