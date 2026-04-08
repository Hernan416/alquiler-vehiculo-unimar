<?php
$host = '127.0.0.1';
$dbname = 'lhfm_logistics';
$username = 'root';
$password = ''; // Por defecto en XAMPP suele estar vacío

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Configurar PDO para que lance excepciones en caso de error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Evitar emulación de prepares para mayor seguridad
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>