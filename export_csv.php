<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_classroom";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$fname = 'Smart_Classroom_Audit_Log_' . date('Y-m-d_H-i-s') . '.csv';

$header = ["Log ID", "Room ID", "User Count", "Light Status", "Fan Status", "Motion Status", "Timestamp"];

$qry = mysqli_query($conn, "SELECT * FROM `room_log` WHERE `room_id` = 1 ORDER BY `log_id` DESC");

if (mysqli_num_rows($qry) <= 0) {
    echo "<script> alert('Tiada data log dijumpai.'); location.href = './'; </script>";
    exit;
}

$file = fopen("php://memory", "w");

fputcsv($file, $header, ',');

while ($row = mysqli_fetch_assoc($qry)) {
    $lightStatusText  = ($row['light_status'] == 1) ? "ON" : "OFF";
    $fanStatusText    = ($row['fan_status'] == 1) ? "ON" : "OFF";
    $motionStatusText = ($row['motion_status'] == 1) ? "DETECTED" : "CLEAR";

    fputcsv($file, [
        $row['log_id'],
        $row['room_id'],
        $row['user_count'],
        $lightStatusText,
        $fanStatusText,
        $motionStatusText,
        $row['timestamp']
    ], ',');
}

fseek($file, 0);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $fname . '";');

fpassthru($file);

mysqli_close($conn);
exit;
?>