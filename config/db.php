<?php
$host = "localhost";
$user = "root";
$pass = "Thanhan@04";
$db   = "quiz_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>
