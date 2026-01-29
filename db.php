<?php
$host = "localhost";
$db   = "sugarcrm";      // your local DB
$user = "*****";           // your MySQL username
$pass = "********";               // your MySQL password

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully!";
?>
