<?php
// Admin/index.php
session_start();
require_once '../conexion.php';

// Protección de ruta
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

date_default_timezone_set('America/Caracas');

try {
    // 1. Métricas rápidas
    $total_vehiculos = $pdo->query("SELECT COUNT(*) FROM vehiculos")->fetchColumn();
    $alquilados = $pdo->query("SELECT COUNT(*) FROM vehiculos WHERE estado = 'Alquilado'")->fetchColumn();
    $pendientes = $pdo->query("SELECT COUNT(*) FROM alquileres WHERE estado_alquiler = 'Reservado'")->fetchColumn();
    $mantenimiento = $pdo->query("SELECT COUNT(*) FROM vehiculos WHERE estado = 'Mantenimiento'")->fetchColumn();

    // 2. Últimos movimientos
    $stmt = $pdo->query("
        SELECT a.id_alquiler, c.nombre, c.apellido, v.marca, v.modelo, a.fecha_retorno_prevista, a.estado_alquiler
        FROM alquileres a
        JOIN clientes c ON a.id_cliente = c.id_cliente
        JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
        ORDER BY a.id_alquiler DESC LIMIT 5
    ");
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en el dashboard: " . $e->getMessage());
}

$nombre_admin = $_SESSION['cliente_nombre'] ?? 'Administrador';

if ($_SESSION['rol'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - LHFM Logistics</title>
    <style>
        /* Importación de fuentes idéntica a tu index de clientes */
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandMain: '#ABBBC0',
                        brandDark: '#2F2A24',
                        brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' }
                    },
                    fontFamily: {
                        sans: ['"Google Sans"', 'sans-serif'],
                        heading: ['Fredoka', 'sans-serif'],
                        brand: ['Righteous', 'cursive']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-brandDark font-sans flex min-h-screen">

    <aside class="w-72 bg-brandDark text-white flex flex-col shadow-2xl z-50">
        <div class="p-8 border-b border-white/10">
            <span class="font-brand text-2xl tracking-wide">
                LHFM <span class="text-brandMain">LOGISTICS</span>
            </span>
            <p class="text-[10px] font-heading tracking-[0.2em] text-brandMain/60 uppercase mt-2">Panel Administrativo</p>
        </div>
        
        <nav class="flex-1 p-6 space-y-3">
            <a href="index.php" class="flex items-center gap-3 py-3 px-4 bg-brandBlue-900/40 rounded-lg font-heading tracking-wider uppercase text-xs border border-brandBlue-900/50">
                 Dashboard
            </a>
            
            <a href="flota.php" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">
                 Flota y Vehículos
            </a>
            <a href="clientes.php" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">
                 Clientes
            </a>
        </nav>

        <div class="p-6 border-t border-white/10 space-y-4">
            <a href="../Clientes/index.php" class="block text-center bg-brandMain/10 hover:bg-brandMain/20 text-white font-heading tracking-widest text-[10px] py-3 rounded uppercase border border-white/5 transition-all">
                Ir a inicio
            </a>
            <a href="../login.php" class="block text-center text-red-400 font-heading tracking-widest text-[10px] uppercase hover:underline">
                Cerrar Sesión Segura
            </a>
        </div>
    </aside>

    <main class="flex-1 p-10 overflow-y-auto">
        <header class="flex justify-between items-center mb-12">
           <div>
                <h2 class="font-heading text-4xl text-brandDark uppercase tracking-tight">Resumen Operativo</h2>
                <div class="h-1 w-20 bg-brandBlue-900 mt-2"></div>
            </div>
            
            <div class="flex items-center gap-4 bg-brandDark p-2 pr-6 rounded-full shadow-lg border border-brandMain/30">
                <div class="w-10 h-10 bg-brandBlue-900 text-white rounded-full flex items-center justify-center font-extrabold text-lg ">
                    <?php echo strtoupper(substr($nombre_admin, 0, 1)); ?>
                </div>
                <div class="flex flex-col">
                    <span class="text-[9px] font-extrabold uppercase text-brandMain tracking-widest leading-none">Administrador</span>
                    <span class="font-heading text-sm text-white uppercase tracking-wider"><?php echo $nombre_admin; ?></span>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm group hover:border-brandBlue-900 transition-colors">
                <p class="text-[10px] uppercase font-bold text-brandGreen-500 tracking-widest mb-2 font-heading">Vehículos Totales</p>
                <div class="flex items-baseline gap-2">
                    <p class="text-4xl font-heading text-brandDark"><?php echo $total_vehiculos; ?></p>
                    <span class="text-xs font-bold text-brandMain">
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-brandGreen-500 tracking-widest mb-2 font-heading">Activos en Calle</p>
                <p class="text-4xl font-heading text-brandDark"><?php echo $alquilados; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-brandGreen-500 tracking-widest mb-2 font-heading">Reservas Próximas</p>
                <p class="text-4xl font-heading text-brandDark"><?php echo $pendientes; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-brandGreen-500 tracking-widest mb-2 font-heading">Mantenimiento</p>
                <p class="text-4xl font-heading text-brandDark"><?php echo $mantenimiento; ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-brandMain/20 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-brandMain/10 bg-gray-50/50 flex justify-between items-center">
               <h3 class="font-heading tracking-widest uppercase text-sm text-brandBlue-900 font-extrabold">Últimos Movimientos</h3>
                <a href="gestion_alquileres.php" class="text-[10px] font-bold text-brandBlue-900 uppercase hover:underline">Ver todo</a>
            </div>
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[11px] uppercase tracking-widest font-extrabold text-brandBlue-900 bg-gray-50/50 border-b border-brandMain/10">
                        <th class="p-5">Cliente</th>
                        <th class="p-5">Vehículo</th>
                        <th class="p-5">Fecha Retorno</th>
                        <th class="p-5 text-center">Estado</th>
                        <th class="p-5 text-right">Detalle</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php foreach($movimientos as $m): ?>
                    <tr class="border-b border-brandMain/5 hover:bg-brandBlue-50/30 transition-colors">
                        <td class="p-5">
                            <p class="font-extrabold text-brandDark uppercase tracking-tight"><?php echo $m['nombre'] . " " . $m['apellido']; ?></p>
                        </td>
                        <td class="p-5 text-brandDark font-bold italic">
                            <?php echo $m['marca'] . " " . $m['modelo']; ?>
                        </td>
                        <td class="p-5 font-bold text-brandBlue-900">
                            <?php echo date('d/m/Y H:i', strtotime($m['fecha_retorno_prevista'])); ?>
                        </td>

                        <td class="p-6 text-center">
                            <?php 
                                // Lógica de colores por estado
                                $clase_estado = "";
                                switch($m['estado_alquiler']) {
                                    case 'Activo': $clase_estado = "bg-green-100 text-green-700"; break;
                                    case 'Reservado': $clase_estado = "bg-yellow-100 text-yellow-700"; break;
                                    case 'Cancelado': $clase_estado = "bg-red-100 text-red-700"; break;
                                    case 'Finalizado': $clase_estado = "bg-blue-100 text-blue-700"; break;
                                    default: $clase_estado = "bg-gray-100 text-gray-700";
                                }
                            ?>
                            <span class="px-5 py-2 rounded-full text-[10px] font-extrabold uppercase tracking-[0.15em] shadow-sm <?php echo $clase_estado; ?>">
                                <?php echo $m['estado_alquiler']; ?>
                            </span>
                        </td>

                        <td class="p-5 text-right">
                            <a href="detalle_alquiler.php?id=<?php echo $m['id_alquiler']; ?>" class="inline-block bg-brandDark text-white px-4 py-2 rounded-lg text-[10px] font-extrabold uppercase tracking-widest hover:bg-brandBlue-900 transition-all shadow-md">
                                Gestionar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>