-- Update schema for db_absensi_pegawai
USE db_absensi_pegawai;

-- 1. Create table jabatan
CREATE TABLE IF NOT EXISTS jabatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_jabatan VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default jabatan
INSERT IGNORE INTO jabatan (nama_jabatan) VALUES
('Kepala Desa'),
('Sekretaris Desa'),
('Kaur Keuangan'),
('Kaur Pembangunan'),
('Kaur Pemerintahan'),
('Kaur Kesra'),
('Kaur Umum'),
('Operator IT');

-- 2. Create table jam_kerja
CREATE TABLE IF NOT EXISTS jam_kerja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_shift VARCHAR(50) NOT NULL,
    hari VARCHAR(100) NOT NULL,
    jam_masuk TIME NOT NULL,
    jam_pulang TIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default jam_kerja
INSERT IGNORE INTO jam_kerja (nama_shift, hari, jam_masuk, jam_pulang) VALUES
('Shift 1 (Senin-Kamis)', 'Senin,Selasa,Rabu,Kamis', '07:30:00', '16:00:00'),
('Shift 2 (Jumat)', 'Jumat', '07:30:00', '14:00:00');

-- 3. Modify users table to use jabatan_id
ALTER TABLE users ADD COLUMN IF NOT EXISTS jabatan_id INT NULL;
ALTER TABLE users ADD CONSTRAINT fk_user_jabatan FOREIGN KEY (jabatan_id) REFERENCES jabatan(id) ON DELETE SET NULL;

-- Map existing free text jabatan to jabatan_id
UPDATE users SET jabatan_id = (SELECT id FROM jabatan WHERE nama_jabatan = 'Operator IT') WHERE jabatan = 'Operator IT';
UPDATE users SET jabatan_id = (SELECT id FROM jabatan WHERE nama_jabatan = 'Kepala Desa') WHERE jabatan = 'Kepala Desa Sungai Rambut';
UPDATE users SET jabatan_id = (SELECT id FROM jabatan WHERE nama_jabatan = 'Sekretaris Desa') WHERE jabatan = 'Sekretaris Desa';
UPDATE users SET jabatan_id = (SELECT id FROM jabatan WHERE nama_jabatan = 'Kaur Keuangan') WHERE jabatan = 'Kaur Keuangan';
UPDATE users SET jabatan_id = (SELECT id FROM jabatan WHERE nama_jabatan = 'Kaur Pembangunan') WHERE jabatan = 'Kaur Pembangunan';

-- Drop old jabatan column
ALTER TABLE users DROP COLUMN jabatan;
