<?php
$host = "mysql.railway.internal";
$user = "root"; 
$pass = "lomArDSmZldAgioqrGvzPbJdIdGVIFrg";       
$db   = "railway";
$port = 3306; // Port default MySQL di Railway

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
?>

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
?>
