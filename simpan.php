<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_classroom";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (isset($_POST['count'])) {
    
    if (isset($_POST['room_id'])) {
        $roomId = (int)$_POST['room_id'];
    } else {
        $roomId = 1;
    }

    $count = (int)$_POST['count'];

    if (isset($_POST['ldr'])) {
        $ldr = (int)$_POST['ldr'];
    } else {
        $ldr = 0;
    }

    if (isset($_POST['relaylight'])) {
        $lightStatus = (int)$_POST['relaylight'];
    } else {
        $lightStatus = 0;
    }

    if (isset($_POST['relayfan'])) {
        $fanStatus = (int)$_POST['relayfan'];
    } else {
        $fanStatus = 0;
    }

    // Mengambil nilai PIR terus jika dihantar oleh ESP8266
    if (isset($_POST['pir'])) {
        $motionStatus = (int)$_POST['pir'];
    } else {
        if ($count > 0) {
            $motionStatus = 1;
        } else {
            $motionStatus = 0;
        }
    }

    $sql = "INSERT INTO room_log (room_id, user_count, motion_status, light_status, fan_status, ldr_status) 
            VALUES ($roomId, $count, $motionStatus, $lightStatus, $fanStatus, $ldr)";

    if (mysqli_query($conn, $sql)) {
        echo "Data saved successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }

} else {
    echo "Insufficient information";
}

mysqli_close($conn);
?>