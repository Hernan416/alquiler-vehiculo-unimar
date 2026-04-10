<?php
session_start();
// Validamos que exista sesión y que el rol sea exactamente 'admin'
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>