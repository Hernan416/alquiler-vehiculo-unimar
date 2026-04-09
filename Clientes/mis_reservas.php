<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$success = ''; $error = '';

// Procesar Cancelación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancelar_reserva'])) {
    $id_cancelar = $_POST['id_alquiler'];
    
    // Validar que la reserva pertenezca a este cliente y sea cancelable
    $stmt_val = $pdo->prepare("SELECT id_vehiculo FROM alquileres WHERE id_alquiler = ? AND id_cliente = ? AND estado_alquiler = 'Reservado'");
    $stmt_val->execute([$id_cancelar, $id_cliente]);
    $reserva_cancelar = $stmt_val->fetch(PDO::FETCH_ASSOC);

    if ($reserva_cancelar) {
        try {
            $pdo->beginTransaction();
            // Cambiar estado a cancelado
            $pdo->prepare("UPDATE alquileres SET estado_alquiler = 'Cancelado' WHERE id_alquiler = ?")->execute([$id_cancelar]);
            // Liberar el vehículo
            $pdo->prepare("UPDATE vehiculos SET estado = 'Disponible' WHERE id_vehiculo = ?")->execute([$reserva_cancelar['id_vehiculo']]);
            $pdo->commit();
            $success = "La reserva #".str_pad($id_cancelar, 5, "0", STR_PAD_LEFT)." ha sido cancelada exitosamente. Contacte a soporte para procesar su reembolso.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al cancelar la reserva: " . $e->getMessage();
        }
    } else {
        $error = "No se puede cancelar esta reserva o ya no está disponible.";
    }
}

// Obtener todas las reservas
$stmt = $pdo->prepare("
    SELECT a.*, v.marca, v.modelo, v.url_imagen, c.nombre_categoria 
    FROM alquileres a
    JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
    JOIN categorias c ON v.id_categoria = c.id_categoria
    WHERE a.id_cliente = ?
    ORDER BY a.fecha_salida DESC
");
$stmt->execute([$id_cliente]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Reservas - LHFM Logistics</title>
    <style>@import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brandMain: '#ABBBC0', brandDark: '#2F2A24', brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' } }, fontFamily: { sans: ['"Google Sans"', 'sans-serif'], heading: ['Fredoka', 'sans-serif'], brand: ['Righteous', 'cursive'] } } } }
    </script>
</head>
<body class="bg-gray-50 text-brandDark font-sans min-h-screen">

    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 flex justify-between h-20 items-center">
            <a href="index.php" class="font-brand text-2xl text-brandDark">LHFM <span class="text-brandMain">LOGISTICS</span></a>
            <div class="flex items-center gap-6">
                <a href="perfil.php" class="text-sm font-semibold text-brandDark/70 hover:text-brandDark">Mi Perfil</a>
                <span class="font-bold text-brandBlue-900 border-b-2 border-brandBlue-900 pb-1">Mis Reservas</span>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-10">
        <div class="mb-8 border-b border-brandMain/20 pb-4">
            <h1 class="font-heading text-4xl text-brandDark">Historial de Reservas</h1>
            <p class="text-brandDark/70 mt-1">Administra tus viajes, agrega días o extras a tus alquileres activos.</p>
        </div>

        <?php if($success): ?><div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 font-bold text-sm"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 font-bold text-sm"><?php echo $error; ?></div><?php endif; ?>

        <?php if(empty($reservas)): ?>
            <div class="bg-white p-10 rounded-xl shadow-sm text-center border border-brandMain/20">
                <span class="text-6xl block mb-4">🏜️</span>
                <h3 class="font-heading text-2xl mb-2">Aún no tienes viajes</h3>
                <p class="text-brandDark/60 mb-6">Explora nuestra flota y comienza tu aventura en Margarita.</p>
                <a href="index.php#flota" class="bg-brandBlue-900 text-white px-6 py-3 rounded font-bold hover:bg-brandDark transition-colors">Ver Vehículos</a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach($reservas as $r): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-brandMain/20 p-6 flex flex-col md:flex-row gap-6 items-center hover:shadow-md transition-shadow relative overflow-hidden">
                        
                        <?php if($r['estado_alquiler'] == 'Cancelado'): ?>
                            <div class="absolute inset-0 bg-white/60 z-10 pointer-events-none"></div>
                        <?php endif; ?>

                        <div class="w-full md:w-1/4 bg-brandMain/10 h-32 rounded-lg flex items-center justify-center relative">
                            <?php 
                                $bg_badge = 'bg-blue-100 text-blue-800';
                                if($r['estado_alquiler'] == 'Finalizado') $bg_badge = 'bg-gray-200 text-gray-700';
                                if($r['estado_alquiler'] == 'Activo') $bg_badge = 'bg-green-100 text-green-800';
                                if($r['estado_alquiler'] == 'Cancelado') $bg_badge = 'bg-red-100 text-red-800';
                            ?>
                            <span class="absolute top-2 right-2 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest <?php echo $bg_badge; ?> z-20">
                                <?php echo htmlspecialchars($r['estado_alquiler']); ?>
                            </span>
                            
                            <?php if($r['url_imagen']): ?><img src="<?php echo htmlspecialchars($r['url_imagen']); ?>" class="max-h-full object-contain z-0"><?php else: ?><span class="text-xs font-bold">Auto</span><?php endif; ?>
                        </div>
                        
                        <div class="w-full md:w-2/4 z-20">
                            <span class="text-xs font-bold uppercase text-brandMain block"><?php echo htmlspecialchars($r['nombre_categoria']); ?></span>
                            <h2 class="font-heading text-2xl text-brandDark mb-2 <?php echo $r['estado_alquiler'] == 'Cancelado' ? 'line-through text-gray-400' : ''; ?>"><?php echo htmlspecialchars($r['marca'] . ' ' . $r['modelo']); ?></h2>
                            <div class="text-sm text-brandDark/70 grid grid-cols-2 gap-2">
                                <div><strong>Salida:</strong> <?php echo date('d/m/Y', strtotime($r['fecha_salida'])); ?></div>
                                <div><strong>Retorno:</strong> <?php echo date('d/m/Y', strtotime($r['fecha_retorno_prevista'])); ?></div>
                                <div><strong>Ref:</strong> #<?php echo str_pad($r['id_alquiler'], 5, "0", STR_PAD_LEFT); ?></div>
                                <div><strong>Total:</strong> $<?php echo number_format($r['monto_total_alquiler'], 2); ?></div>
                            </div>
                        </div>

                        <div class="w-full md:w-1/4 flex flex-col gap-3 justify-center border-t md:border-t-0 md:border-l border-brandMain/20 pt-4 md:pt-0 md:pl-6 z-20">
                            <?php if($r['estado_alquiler'] == 'Reservado'): ?>
                                <a href="editar_reserva.php?id=<?php echo $r['id_alquiler']; ?>" class="bg-brandDark text-white text-center font-bold py-2 px-4 rounded hover:bg-brandBlue-900 transition-colors font-heading text-sm uppercase tracking-widest">
                                    Modificar
                                </a>
                                <form method="POST" action="" onsubmit="return confirm('¿Está seguro de que desea cancelar esta reserva? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="id_alquiler" value="<?php echo $r['id_alquiler']; ?>">
                                    <button type="submit" name="cancelar_reserva" class="w-full bg-red-50 text-red-600 border border-red-200 text-center font-bold py-2 px-4 rounded hover:bg-red-600 hover:text-white transition-colors text-sm uppercase tracking-widest">
                                        Cancelar
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="recibo.php?id=<?php echo $r['id_alquiler']; ?>" target="_blank" class="border border-brandMain/50 text-center text-brandDark font-bold py-2 px-4 rounded hover:bg-gray-50 transition-colors text-sm uppercase tracking-widest">
                                Ver Recibo
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>