-- =====================================================
-- SQL Schema & Initial Seed for db_absensi_pegawai
-- =====================================================

CREATE DATABASE IF NOT EXISTS db_absensi_pegawai;
USE db_absensi_pegawai;

-- 1. Table: jabatan
CREATE TABLE IF NOT EXISTS jabatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_jabatan VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default jabatan
INSERT IGNORE INTO jabatan (id, nama_jabatan) VALUES
(1, 'Kepala Desa'),
(2, 'Sekretaris Desa'),
(3, 'Kasi Pemerintahan'),
(4, 'Kasi Kesra'),
(5, 'Kaur Perencanaan'),
(6, 'Kaur Keuangan'),
(7, 'Kepala Dusun 01'),
(8, 'Kepala Dusun 02'),
(9, 'Staf Pemerintahan'),
(10, 'Staf Perencanaan'),
(11, 'Staf Kesra'),
(12, 'Staf Keuangan'),
(13, 'Petugas Administrasi'),
(14, 'Petugas Kebersihan'),
(15, 'Penjaga Kantor Desa'),
(16, 'Operator IT');

-- 2. Table: users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staf', 'kepala_desa') NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    nip VARCHAR(30) UNIQUE NULL,
    jabatan_id INT NULL,
    user_id VARCHAR(50) UNIQUE NULL COMMENT 'ID Pegawai / Barcode ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jabatan_id) REFERENCES jabatan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default users (password: 'admin123')
INSERT IGNORE INTO users (id, username, password, role, nama_lengkap, nip, jabatan_id, user_id) VALUES
(1,  'alamsyah',   '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'kepala_desa', 'ALAMSYAH',         '197505121998031001', 1,  '1'),
(2,  'hermansyah', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'HERMANSYAH',       '198004152005011002', 2,  '2'),
(3,  'ahmadyani',  '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'AHMAD YANI',       '198208102008041003', 3,  '3'),
(4,  'arapik',     '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'A. RAPIK',         '198511232010031004', 4,  '4'),
(5,  'aripin',     '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'ARIPIN',           '198703122012011005', 5,  '5'),
(6,  'fadil',      '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'FADIL',            '198906202014021006', 6,  '6'),
(7,  'musa',       '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'MUSA',             '198101152006031007', 7,  '7'),
(8,  'ismail',     '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'ISMAIL',           '198305182009021008', 8,  '8'),
(9,  'dendi',      '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'DENDI IRAWAN',     '199512052020121009', 9,  '9'),
(10, 'saman',      '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'MUHAMMAD SAMAN',   '199607142021011010', 10, '10'),
(11, 'ardiansyah', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'ARDIANSYAH',       '199709212022031011', 11, '11'),
(12, 'rafiah',     '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'RAFIAH',           '199803122022032012', 11, '12'),
(13, 'hudori',     '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'HUDORI AMRULLAH',  '199410112019101013', 12, '13'),
(14, 'marwan',     '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'MARWAN',           '199308052018091014', 12, '14'),
(15, 'winda',      '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'WINDA',            '199902182023012015', 13, '15'),
(16, 'wisnu',      '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'WISNU ARDIANSYAH', '199811302022101016', 13, '16'),
(17, 'leni',       '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'LENI',             '200004122024012017', 14, '17'),
(18, 'maysaroh',   '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'MAY SAROH',        '199905252023052018', 14, '18'),
(19, 'awaludin',   '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf',        'AWALUDIN',         '199102142017041019', 15, '19'),
(20, 'admin',      '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'admin',       'Administrator IT', '199001012020011001', 16, NULL);

-- 3. Table: jam_kerja
CREATE TABLE IF NOT EXISTS jam_kerja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_shift VARCHAR(50) NOT NULL,
    hari VARCHAR(100) NOT NULL,
    jam_masuk TIME NOT NULL,
    jam_pulang TIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default jam_kerja
INSERT IGNORE INTO jam_kerja (id, nama_shift, hari, jam_masuk, jam_pulang) VALUES
(1, 'Shift 1 (Senin-Kamis)', 'Senin,Selasa,Rabu,Kamis', '07:30:00', '16:00:00'),
(2, 'Shift 2 (Jumat)', 'Jumat', '07:30:00', '14:00:00');

-- 4. Table: presensi
CREATE TABLE IF NOT EXISTS presensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME NULL,
    jam_keluar TIME NULL,
    am_in TIME NULL COMMENT 'Scan masuk pagi (AM In)',
    am_out TIME NULL COMMENT 'Scan pulang pagi / istirahat (AM Out)',
    pm_in TIME NULL COMMENT 'Scan masuk siang / balik istirahat (PM In)',
    pm_out TIME NULL COMMENT 'Scan pulang sore (PM Out)',
    late_minute INT DEFAULT 0 COMMENT 'Keterlambatan dalam menit',
    early_minute INT DEFAULT 0 COMMENT 'Pulang cepat dalam menit',
    status ENUM('hadir', 'terlambat', 'alpha', 'sakit', 'izin') NOT NULL,
    keterangan VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
