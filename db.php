<?php
$host = 'localhost';
$dbname = 'toodue_db';
$username = 'root';

// Check common passwords for different local environments
// 'root' (Mac/MAMP), '' (Windows/Laragon), '1234' (custom setup)
$possible_passwords = ['root', '', '1234']; 

$pdo = null;

// Try connecting with each password until one works
foreach ($possible_passwords as $password) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        break; 
    } catch (PDOException $e) {
        continue;
    }
}

// If no password worked, stop execution and show an error
if ($pdo === null) {
    die("Database Connection Error: Could not connect. Please check your local MySQL credentials in db.php.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
?>