<?php

$hostName = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "smart_classroom"; // Replace this with your actual database name in phpMyAdmin

$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $dbName);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>