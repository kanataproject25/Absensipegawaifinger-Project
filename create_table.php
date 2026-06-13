<?php
require_once __DIR__ . '/config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS pengajuan_izin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    kategori ENUM('sakit', 'izin') NOT NULL,
    keterangan TEXT NOT NULL,
    bukti_file VARCHAR(255) NULL,
    status ENUM('pending', 'disetujui', 'ditolak') DEFAULT 'pending',
    tanggal_pengajuan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->exec($sql);
    echo "Table pengajuan_izin created successfully";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
