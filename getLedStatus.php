<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_classroom";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("0");
}

$roomId = 1;
$sql    = "SELECT control_mode FROM device_control WHERE room_id = $roomId LIMIT 1";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo $row['control_mode'];
} else {
    echo "0";
}

mysqli_close($conn);
?>