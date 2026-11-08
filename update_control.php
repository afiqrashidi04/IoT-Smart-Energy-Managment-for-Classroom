<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_classroom";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (isset($_POST['mode'])) {
    $mode = (int)$_POST['mode']; // 0 = AUTO, 1 = FORCE ON, 2 = FORCE OFF
    $roomId = 1; // Default Room ID

    // Semak samada rekod room_id sudah wujud
    $checkSql = "SELECT * FROM device_control WHERE room_id = $roomId";
    $result   = mysqli_query($conn, $checkSql);

    if (mysqli_num_rows($result) > 0) {
        // Kemaskini mod sedia ada
        $sql = "UPDATE device_control SET control_mode = $mode WHERE room_id = $roomId";
    } else {
        // Masukkan rekod baru jika tiada
        $sql = "INSERT INTO device_control (room_id, control_mode) VALUES ($roomId, $mode)";
    }

    mysqli_query($conn, $sql);
}

mysqli_close($conn);

// Hantar pengguna kembali ke Dashboard (index.php)
header("Location: index.php");
exit();
?>