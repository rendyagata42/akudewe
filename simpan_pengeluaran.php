<?php
include 'koneksi.php';

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Koneksi database tidak tersedia."]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS pengeluaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nominal INT NOT NULL,
    catatan TEXT NOT NULL,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
    exit;
}

$nominal = isset($data['nominal']) ? (int) $data['nominal'] : 0;
$catatan = isset($data['catatan']) ? trim($data['catatan']) : '';

if ($nominal <= 0 || $catatan === '') {
    echo json_encode(["status" => "error", "message" => "Nominal dan catatan pengeluaran wajib diisi."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO pengeluaran (nominal, catatan, tanggal) VALUES (?, ?, NOW())");
$stmt->bind_param("is", $nominal, $catatan);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Pengeluaran berhasil disimpan."]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan pengeluaran."]);
}

$stmt->close();
$conn->close();
