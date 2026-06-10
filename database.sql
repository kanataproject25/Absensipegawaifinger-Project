-- SQL Schema for Absensi Pegawai Fingerprint
CREATE DATABASE IF NOT EXISTS db_absensi_pegawai;
USE db_absensi_pegawai;

-- Table users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staf', 'kepala_desa') NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    nip VARCHAR(30) UNIQUE NULL,
    jabatan VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table presensi
CREATE TABLE IF NOT EXISTS presensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME NULL,
    jam_keluar TIME NULL,
    status ENUM('hadir', 'terlambat', 'alpha', 'sakit', 'izin') NOT NULL,
    keterangan VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seeder for initial users
-- Password for all default accounts is 'admin123' (hashed using PASSWORD_DEFAULT in PHP: $2y$10$wR8wR1vF1m.P4wVp3j.J5O2d/3o6z8XlP2N.c1wLh9lE9tX5G1bQ6 or similar, we will seed them with hashed password)
-- Hashed password for 'admin123': $2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi
INSERT INTO users (username, password, role, nama_lengkap, nip, jabatan) VALUES
('admin', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'admin', 'Petugas Admin Presensi', '199001012020011001', 'Operator IT'),
('kepaladesa', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'kepala_desa', 'H. Ahmad Syarifuddin, S.E.', '197505121998031002', 'Kepala Desa Sungai Rambut'),
('staf1', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf', 'Budi Setiawan', '199508202021021003', 'Sekretaris Desa'),
('staf2', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf', 'Siti Rahmawati', '199703152022012005', 'Kaur Keuangan'),
('staf3', '$2y$10$o9cT7Fl6lwoVd2WxGqRdOO4KOlTc8xG7/0R3sxBNfBJajhXiEncIi', 'staf', 'Joko Susilo', '199211302021021004', 'Kaur Pembangunan');

-- Seeder for dummy presensi for testing dashboard graphs
-- Let's put some records for the current date or recent days
INSERT INTO presensi (user_id, tanggal, jam_masuk, jam_keluar, status) VALUES
(3, CURDATE(), '07:25:00', '16:05:00', 'hadir'),
(4, CURDATE(), '08:15:00', NULL, 'terlambat'),
(5, CURDATE(), NULL, NULL, 'alpha');
