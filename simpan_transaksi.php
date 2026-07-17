<?php
include 'koneksi.php';

function hitungTotalDetailMenu(mysqli $conn, string $detail_menu): int {
    $menuResult = $conn->query("SELECT nama_item, harga FROM menu");
    $hargaMenu = [];

    if ($menuResult && $menuResult->num_rows > 0) {
        while ($row = $menuResult->fetch_assoc()) {
            $hargaMenu[strtolower(trim($row['nama_item']))] = (int) $row['harga'];
        }
    }

    $total = 0;
    $segments = preg_split('/\s*,\s*/', trim($detail_menu));

    if (!$segments) {
        return 0;
    }

    foreach ($segments as $segment) {
        $cleanSegment = trim($segment);
        if ($cleanSegment === '') {
            continue;
        }

        $qty = 1;
        if (preg_match('/\((\d+)x\)\s*$/i', $cleanSegment, $matches)) {
            $qty = (int) $matches[1];
            $cleanSegment = preg_replace('/\s*\((\d+)x\)\s*$/i', '', $cleanSegment);
        }

        $price = 0;
        if (preg_match('/\[(.*?)\]\s*$/i', $cleanSegment, $matches)) {
            $manualPrice = (int) preg_replace('/[^0-9]/', '', $matches[1]);
            if ($manualPrice > 0) {
                $price = $manualPrice;
            }
            $cleanSegment = preg_replace('/\s*\[.*?\]\s*$/i', '', $cleanSegment);
        }

        $cleanSegment = trim($cleanSegment);
        $lookupKey = strtolower($cleanSegment);
        if ($price <= 0 && isset($hargaMenu[$lookupKey])) {
            $price = $hargaMenu[$lookupKey];
        }

        if ($price > 0) {
            $total += $price * $qty;
        }
    }

    return $total;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Data tidak valid."]);
    $conn->close();
    exit;
}

$action = isset($data['action']) ? strtolower(trim($data['action'])) : 'create';
$detail_menu = isset($data['detail_item']) ? trim($data['detail_item']) : (isset($data['detail_menu']) ? trim($data['detail_menu']) : '');

if ($action === 'update' || $action === 'delete') {
    $id = isset($data['id']) ? (int) $data['id'] : 0;

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID transaksi tidak valid."]);
        $conn->close();
        exit;
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Transaksi berhasil dihapus."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus transaksi."]);
        }

        $stmt->close();
        $conn->close();
        exit;
    }

    if ($detail_menu === '') {
        echo json_encode(["status" => "error", "message" => "Detail menu tidak boleh kosong."]);
        $conn->close();
        exit;
    }

    $total_penjualan = hitungTotalDetailMenu($conn, $detail_menu);
    if ($total_penjualan <= 0) {
        echo json_encode(["status" => "error", "message" => "Total hasil parsing detail menu tidak valid. Pastikan format item benar."]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("UPDATE transaksi SET detail_menu = ?, total_penjualan = ? WHERE id = ?");
    $stmt->bind_param("sii", $detail_menu, $total_penjualan, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Transaksi berhasil diperbarui."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui transaksi."]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

$total_penjualan = isset($data['total_penjualan']) ? (int) $data['total_penjualan'] : 0;
if ($total_penjualan <= 0) {
    $total_penjualan = hitungTotalDetailMenu($conn, $detail_menu);
}

if ($total_penjualan <= 0) {
    echo json_encode(["status" => "error", "message" => "Total penjualan tidak boleh kosong."]);
    $conn->close();
    exit;
}

$query = "INSERT INTO transaksi (detail_menu, total_penjualan) VALUES (?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $detail_menu, $total_penjualan);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Laporan penjualan berhasil disimpan!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan laporan ke database."]);
}

$stmt->close();
$conn->close();
?>
