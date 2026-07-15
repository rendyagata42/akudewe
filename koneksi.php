<?php
$host = "mysql.railway.internal";
$user = "root"; 
$pass = "lomArDSmZldAgioqrGvzPbJdIdGVIFrg";     
$db   = "railway";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
?>
