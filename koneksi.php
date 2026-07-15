<?php
$host = "tokaido.proxy.rlwy.net";
$user = "root"; 
$pass = "lomArDSmZldAgioqrGvzPbJdIdGVIFrg";       
$db   = "railway";
$port = "25191"; // Port default MySQL di Railway

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
?>
