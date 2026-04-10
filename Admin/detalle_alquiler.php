<?php
// Admin/detalle_alquiler.php
require_once 'auth_admin.php'; // Validación de sesión de admin
require_once '../conexion.php';

$id_alquiler = $_GET['id'] ?? ($_POST['id_alquiler'] ?? null);
$success = ''; $error = '';

if (!$id_alquiler) {
    header("Location: index.php");
    exit;
}

// --- PROCESAR ELIMINACIÓN (HARD DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_reserva'])) {
    try {
        $pdo->beginTransaction();
        
        // Obtener el ID del vehículo antes de borrar para liberarlo
        $stmt_veh = $pdo->prepare("SELECT id_vehiculo FROM alquileres WHERE id_alquiler = ?");
        $stmt_veh->execute([$id_alquiler]);
        $veh = $stmt_veh->fetchColumn();

        // Eliminar dependencias (Pagos, Extras, Historial) para no violar restricciones de clave foránea
        $pdo->prepare("DELETE FROM pagos WHERE id_alquiler = ?")->execute([$id_alquiler]);
        $pdo->prepare("DELETE FROM alquiler_extras WHERE id_alquiler = ?")->execute([$id_alquiler]);
        $pdo->prepare("DELETE FROM historial_alquileres WHERE id_alquiler = ?")->execute([$id_alquiler]);
        
        // Eliminar la reserva
        $pdo->prepare("DELETE FROM alquileres WHERE id_alquiler = ?")->execute([$id_alquiler]);
        
        // Liberar el vehículo
        if ($veh) {
            $pdo->prepare("UPDATE vehiculos SET estado = 'Disponible' WHERE id_vehiculo = ?")->execute([$veh]);
        }

        $pdo->commit();
        header("Location: index.php?msg=deleted"); // Redirigir al panel principal
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al eliminar la reserva: " . $e->getMessage();
    }
}

// --- PROCESAR ACTUALIZACIÓN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_reserva'])) {
    $estado_alquiler = $_POST['estado_alquiler'];
    $fecha_salida = str_replace('T', ' ', $_POST['fecha_salida']) . ':00';
    $fecha_retorno_prevista = str_replace('T', ' ', $_POST['fecha_retorno_prevista']) . ':00';
    $monto_total_alquiler = $_POST['monto_total_alquiler'];
    $estado_deposito = $_POST['estado_deposito'];
    $monto_deposito = $_POST['monto_deposito'];
    $notas_deposito = trim($_POST['notas_deposito']);
    $id_vehiculo = $_POST['id_vehiculo'];

    try {
        $pdo->beginTransaction();

        $stmt_upd = $pdo->prepare("
            UPDATE alquileres SET 
                estado_alquiler = ?, 
                fecha_salida = ?, 
                fecha_retorno_prevista = ?, 
                monto_total_alquiler = ?, 
                estado_deposito = ?, 
                monto_deposito = ?, 
                notas_deposito = ?
            WHERE id_alquiler = ?
        ");
        $stmt_upd->execute([
            $estado_alquiler, $fecha_salida, $fecha_retorno_prevista, 
            $monto_total_alquiler, $estado_deposito, $monto_deposito, 
            $notas_deposito, $id_alquiler
        ]);

        // Lógica automática para el estado del vehículo en la flota
        if (in_array($estado_alquiler, ['Finalizado', 'Cancelado'])) {
            $pdo->prepare("UPDATE vehiculos SET estado = 'Disponible' WHERE id_vehiculo = ?")->execute([$id_vehiculo]);
        } else {
            $pdo->prepare("UPDATE vehiculos SET estado = 'Alquilado' WHERE id_vehiculo = ?")->execute([$id_vehiculo]);
        }

        $pdo->commit();
        $success = "La reserva ha sido actualizada correctamente.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// --- OBTENER DATOS ACTUALES ---
$stmt = $pdo->prepare("
    SELECT a.*, v.marca, v.modelo, v.placa, c.nombre, c.apellido, c.identificacion, c.telefono, c.licencia_conducir 
    FROM alquileres a
    JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
    JOIN clientes c ON a.id_cliente = c.id_cliente
    WHERE a.id_alquiler = ?
");
$stmt->execute([$id_alquiler]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    die("Reserva no encontrada.");
}

$nombre_admin = $_SESSION['cliente_nombre'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reserva #<?php echo $id_alquiler; ?> - Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandMain: '#ABBBC0', brandDark: '#2F2A24', brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' }
                    },
                    fontFamily: { sans: ['"Google Sans"', 'sans-serif'], heading: ['Fredoka', 'sans-serif'], brand: ['Righteous', 'cursive'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-brandDark font-sans flex min-h-screen">

    <aside class="w-72 bg-brandDark text-white flex flex-col shadow-2xl z-50">
        <div class="p-8 border-b border-white/10">
            <span class="font-brand text-2xl tracking-wide">LHFM <span class="text-brandMain">LOGISTICS</span></span>
            <p class="text-[10px] font-heading tracking-[0.2em] text-brandMain/60 uppercase mt-2">Panel Administrativo</p>
        </div>
        
        <nav class="flex-1 p-6 space-y-3">
            <a href="index.php" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">Dashboard</a>
            <a href="#" class="flex items-center gap-3 py-3 px-4 bg-brandBlue-900/40 rounded-lg font-heading tracking-wider uppercase text-xs border border-brandBlue-900/50">Alquileres</a>
            <a href="#" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">Flota y Vehículos</a>
        </nav>
    </aside>

    <main class="flex-1 p-10 overflow-y-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <a href="index.php" class="text-brandBlue-900 font-bold text-sm hover:underline">&larr; Volver al Dashboard</a>
                </div>
                <h2 class="font-heading text-4xl text-brandDark uppercase tracking-tight">Reserva #<?php echo str_pad($id_alquiler, 5, "0", STR_PAD_LEFT); ?></h2>
                <div class="h-1 w-20 bg-brandBlue-900 mt-2"></div>
            </div>
        </header>

        <?php if($success): ?><div class="bg-green-100 border border-green-300 text-green-800 p-4 mb-6 rounded-lg font-bold shadow-sm"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 border border-red-300 text-red-800 p-4 mb-6 rounded-lg font-bold shadow-sm"><?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm flex items-start gap-4">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-2xl">👤</div>
                <div>
                    <h3 class="text-[10px] uppercase font-bold text-brandMain tracking-widest mb-1">Datos del Cliente</h3>
                    <p class="font-heading text-xl text-brandDark"><?php echo htmlspecialchars($reserva['nombre'] . ' ' . $reserva['apellido']); ?></p>
                    <p class="text-xs font-bold text-brandDark/70 mt-1">ID: <?php echo htmlspecialchars($reserva['identificacion']); ?> | Licencia: <?php echo htmlspecialchars($reserva['licencia_conducir']); ?></p>
                    <p class="text-xs font-bold text-brandBlue-900 mt-1">📞 <?php echo htmlspecialchars($reserva['telefono']); ?></p>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm flex items-start gap-4">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-2xl">🚗</div>
                <div>
                    <h3 class="text-[10px] uppercase font-bold text-brandMain tracking-widest mb-1">Vehículo Asignado</h3>
                    <p class="font-heading text-xl text-brandDark"><?php echo htmlspecialchars($reserva['marca'] . ' ' . $reserva['modelo']); ?></p>
                    <p class="text-sm font-bold text-brandDark/70 mt-1 bg-gray-100 px-2 py-1 rounded inline-block border border-gray-200">Placa: <?php echo htmlspecialchars($reserva['placa']); ?></p>
                </div>
            </div>
        </div>

        <form method="POST" action="" class="space-y-8">
            <input type="hidden" name="id_vehiculo" value="<?php echo $reserva['id_vehiculo']; ?>">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-brandMain/20 shadow-sm p-8">
                        <h3 class="font-heading tracking-widest uppercase text-sm text-brandBlue-900 font-extrabold mb-6 border-b border-brandMain/10 pb-2">Control de Tiempos y Estado</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Estado del Alquiler</label>
                                <select name="estado_alquiler" class="w-full border border-brandMain/30 py-3 px-4 rounded focus:outline-none focus:border-brandBlue-900 font-bold bg-gray-50">
                                    <option value="Reservado" <?php echo ($reserva['estado_alquiler'] == 'Reservado') ? 'selected' : ''; ?>>Reservado (Pendiente por retirar)</option>
                                    <option value="Activo" <?php echo ($reserva['estado_alquiler'] == 'Activo') ? 'selected' : ''; ?>>Activo (En la calle)</option>
                                    <option value="Finalizado" <?php echo ($reserva['estado_alquiler'] == 'Finalizado') ? 'selected' : ''; ?>>Finalizado (Vehículo entregado)</option>
                                    <option value="Cancelado" <?php echo ($reserva['estado_alquiler'] == 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Total Facturado ($)</label>
                                <input type="number" step="0.01" name="monto_total_alquiler" value="<?php echo $reserva['monto_total_alquiler']; ?>" class="w-full border border-brandMain/30 py-3 px-4 rounded focus:outline-none focus:border-brandBlue-900 font-bold text-brandBlue-900">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Fecha y Hora de Salida</label>
                                <input type="datetime-local" name="fecha_salida" value="<?php echo date('Y-m-d\TH:i', strtotime($reserva['fecha_salida'])); ?>" class="w-full border border-brandMain/30 py-3 px-4 rounded focus:outline-none focus:border-brandBlue-900 font-medium">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Fecha y Hora de Retorno Prevista</label>
                                <input type="datetime-local" name="fecha_retorno_prevista" value="<?php echo date('Y-m-d\TH:i', strtotime($reserva['fecha_retorno_prevista'])); ?>" class="w-full border border-brandMain/30 py-3 px-4 rounded focus:outline-none focus:border-brandBlue-900 font-medium">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-gray-50 rounded-2xl border border-brandMain/30 shadow-sm p-6 sticky top-10">
                        <h3 class="font-heading tracking-widest uppercase text-sm text-brandDark font-extrabold mb-6 border-b border-brandMain/20 pb-2">Gestión de Garantía</h3>
                        
                        <div class="mb-4">
                            <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Monto del Depósito ($)</label>
                            <input type="number" step="0.01" name="monto_deposito" value="<?php echo $reserva['monto_deposito']; ?>" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none font-bold bg-white">
                        </div>

                        <div class="mb-4">
                            <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Estado del Depósito</label>
                            <select name="estado_deposito" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none font-bold bg-white">
                                <option value="Retenido" <?php echo ($reserva['estado_deposito'] == 'Retenido') ? 'selected' : ''; ?>>Retenido (Activo)</option>
                                <option value="Devuelto" <?php echo ($reserva['estado_deposito'] == 'Devuelto') ? 'selected' : ''; ?>>Devuelto al Cliente</option>
                                <option value="Ejecutado Parcial" <?php echo ($reserva['estado_deposito'] == 'Ejecutado Parcial') ? 'selected' : ''; ?>>Ejecutado Parcialmente</option>
                                <option value="Ejecutado Total" <?php echo ($reserva['estado_deposito'] == 'Ejecutado Total') ? 'selected' : ''; ?>>Ejecutado Totalmente</option>
                            </select>
                        </div>

                        <div class="mb-8">
                            <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Notas del Depósito (Daños, retrasos, etc)</label>
                            <textarea name="notas_deposito" rows="3" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none text-sm bg-white"><?php echo htmlspecialchars($reserva['notas_deposito'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="actualizar_reserva" class="w-full bg-brandBlue-900 hover:bg-brandDark text-white font-bold py-4 rounded-lg font-heading tracking-widest uppercase text-sm transition-all shadow-md">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="mt-16 pt-8 border-t border-red-200">
            <div class="bg-red-50 border border-red-200 rounded-2xl p-8 flex justify-between items-center">
                <div>
                    <h3 class="font-heading text-2xl text-red-700">Zona de Peligro</h3>
                    <p class="text-sm text-red-600/80 mt-1">Eliminar permanentemente este alquiler. Esta acción borrará el historial y los pagos asociados. Es irreversible.</p>
                </div>
                <form method="POST" action="" onsubmit="return confirm('ATENCIÓN: ¿Está absolutamente seguro de querer eliminar TODO el registro de esta reserva? Los pagos y el historial también se perderán.');">
                    <button type="submit" name="eliminar_reserva" class="bg-red-600 hover:bg-red-800 text-white font-bold py-3 px-6 rounded-lg font-heading tracking-widest uppercase text-sm transition-all shadow-md flex items-center gap-2">
                        <span>🗑️</span> Eliminar Reserva
                    </button>
                </form>
            </div>
        </div>

    </main>
</body>
</html>