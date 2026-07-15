<?php
include 'koneksi.php';

// Membaca data kiriman JSON dari JavaScript
$data = json_decode(file_get_contents("php://input"), true);

if ($data) {
    $total_penjualan = isset($data['total_penjualan']) ? $data['total_penjualan'] : 0;
    
    // Deteksi isi kiriman (bisa dari detail_item atau detail_menu)
    $detail_menu = '';
    if (isset($data['detail_item'])) {
        $detail_menu = $data['detail_item'];
    } elseif (isset($data['detail_menu'])) {
        $detail_menu = $data['detail_menu'];
    }

    if ($total_penjualan <= 0) {
        echo json_encode(["status" => "error", "message" => "Total penjualan tidak boleh kosong."]);
        exit;
    }

    // MEMAKAI KOLOM detail_menu SESUAI DATABASE KAMU
    $query = "INSERT INTO transaksi (detail_menu, total_penjualan) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $detail_menu, $total_penjualan);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Laporan penjualan berhasil disimpan!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan laporan ke database."]);
    }
    
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
}

$conn->close();
?>