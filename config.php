<?php
require __DIR__.'/vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Determine Environment (Local vs Online)
$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

$dbHost = $isLocal ? 'localhost' : 'sql309.byethost33.com';
$dbName = $isLocal ? 'erp_user' : 'b33_42408677_epr';
$dbUser = $isLocal ? 'root' : 'b33_42408677';
$dbPass = $isLocal ? '' : '123@Qwerty';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database. Error: " . $e->getMessage());
}

session_start();
?>
