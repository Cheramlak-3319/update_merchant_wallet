<?php
$host = "localhost";
$db   = "sugarcrm";      // your local DB
$user = "root";           // your MySQL username
$pass = "9Bcts_2015";               // your MySQL password

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully!";
?>
