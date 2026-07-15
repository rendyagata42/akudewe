<?php
$host = "tokaido.proxy.rlwy.net";
$user = "root"; 
$pass = "lomArDSmZldAgioqrGvzPbJdIdGVIFrg";       
$db   = "railway";
$port = "25191"; // Port default MySQL di Railway

date_default_timezone_set('Asia/Jakarta');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");
?>
