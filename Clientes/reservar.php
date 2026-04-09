<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$error = '';
$success = '';

// 1. VALIDACIÓN DE PERFIL COMPLETO
$stmt_cli = $pdo->prepare("SELECT telefono, licencia_conducir, direccion, id_metodo_pago_preferido FROM clientes WHERE id_cliente = ?");
$stmt_cli->execute([$id_cliente]);
$cliente_info = $stmt_cli->fetch(PDO::FETCH_ASSOC);

$perfil_incompleto = empty($cliente_info['telefono']) || empty($cliente_info['licencia_conducir']) || empty($cliente_info['direccion']) || empty($cliente_info['id_metodo_pago_preferido']);

// Construir URL actual para que perfil.php sepa a dónde devolver al usuario
$current_url = urlencode($_SERVER['REQUEST_URI']);

if ($perfil_incompleto) {
    header("Location: perfil.php?completar=1&redirect=" . $current_url);
    exit;
}

$id_vehiculo = $_GET['id'] ?? ($_POST['id_vehiculo'] ?? null);
$fecha_inicio = $_GET['fecha_inicio'] ?? ($_POST['fecha_inicio'] ?? date('Y-m-d'));
$fecha_fin = $_GET['fecha_fin'] ?? ($_POST['fecha_fin'] ?? date('Y-m-d', strtotime('+3 days')));

if (!$id_vehiculo) {
    header("Location: index.php");
    exit;
}

// Obtener vehículo, tarifa y métodos de pago
$stmt = $pdo->prepare("SELECT v.*, c.nombre_categoria, (SELECT precio_dia FROM tarifas t WHERE t.id_categoria = v.id_categoria AND ? BETWEEN fecha_inicio AND fecha_fin LIMIT 1) as precio_dia FROM vehiculos v JOIN categorias c ON v.id_categoria = c.id_categoria WHERE v.id_vehiculo = ?");
$stmt->execute([$fecha_inicio, $id_vehiculo]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) die("El vehículo no existe.");

$metodos_pago = $pdo->query("SELECT * FROM metodos_pago")->fetchAll(PDO::FETCH_ASSOC);
$lista_extras = $pdo->query("SELECT * FROM extras")->fetchAll(PDO::FETCH_ASSOC);

$datetime1 = new DateTime($fecha_inicio);
$datetime2 = new DateTime($fecha_fin);
$dias = max(1, $datetime1->diff($datetime2)->days);
$precio_dia = $vehiculo['precio_dia'] ?? 0;
$subtotal_vehiculo = $dias * $precio_dia;

// REGLA: Depósito equivale a 1 día de alquiler
$monto_deposito = $precio_dia;

// Procesar reserva y pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_reserva'])) {
    $extras_seleccionados = $_POST['extras'] ?? [];
    $id_metodo_pago = $_POST['id_metodo_pago'];
    $referencia_pago = trim($_POST['referencia_pago']);
    $total_extras = 0;

    $post_inicio = new DateTime($_POST['fecha_inicio']);
    $post_fin = new DateTime($_POST['fecha_fin']);
    $post_dias = max(1, $post_inicio->diff($post_fin)->days);
    $post_subtotal = $post_dias * $precio_dia;

    if (empty($referencia_pago)) {
        $error = "Debe ingresar el número de referencia de su pago.";
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($extras_seleccionados as $id_extra) {
                $stmt_calc = $pdo->prepare("SELECT costo_fijo FROM extras WHERE id_extra = ?");
                $stmt_calc->execute([$id_extra]);
                $total_extras += $stmt_calc->fetchColumn() ?: 0;
            }

            // Total final incluye subtotal + extras + depósito
            $monto_total_final = $post_subtotal + $total_extras + $monto_deposito;

            // Bloqueo de concurrencia
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM alquileres WHERE id_vehiculo = ? AND estado_alquiler IN ('Activo', 'Reservado') AND DATE(fecha_salida) <= ? AND DATE(fecha_retorno_prevista) >= ?");
            $stmt_check->execute([$id_vehiculo, $_POST['fecha_fin'], $_POST['fecha_inicio']]);
            if ($stmt_check->fetchColumn() > 0) throw new Exception("Alguien más acaba de reservar el vehículo.");

            $fecha_salida_dt = $_POST['fecha_inicio'] . " 09:00:00";
            $fecha_retorno_dt = $_POST['fecha_fin'] . " 09:00:00";

            // 1. Insertar Alquiler
            $stmt_reserva = $pdo->prepare("INSERT INTO alquileres (id_vehiculo, id_cliente, fecha_salida, fecha_retorno_prevista, cantidad_dias, monto_total_alquiler, estado_alquiler, monto_deposito, estado_deposito) VALUES (?, ?, ?, ?, ?, ?, 'Reservado', ?, 'Retenido')");
            $stmt_reserva->execute([$id_vehiculo, $id_cliente, $fecha_salida_dt, $fecha_retorno_dt, $post_dias, $monto_total_final, $monto_deposito]);
            $id_alquiler_generado = $pdo->lastInsertId();

            // 2. Insertar Extras
            if (!empty($extras_seleccionados)) {
                $stmt_ins_extra = $pdo->prepare("INSERT INTO alquiler_extras (id_alquiler, id_extra, cantidad) VALUES (?, ?, 1)");
                foreach ($extras_seleccionados as $id_extra) $stmt_ins_extra->execute([$id_alquiler_generado, $id_extra]);
            }

            // 3. Registrar el Pago en la tabla de pagos
            $stmt_pago = $pdo->prepare("INSERT INTO pagos (id_alquiler, id_metodo_pago, monto_total, referencia) VALUES (?, ?, ?, ?)");
            $stmt_pago->execute([$id_alquiler_generado, $id_metodo_pago, $monto_total_final, $referencia_pago]);

            $pdo->commit();
            $success = "¡Reserva y pago confirmados con éxito! Nro. Ref: #" . str_pad($id_alquiler_generado, 5, "0", STR_PAD_LEFT);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Reserva - LHFM Logistics</title>
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
            <span class="text-sm font-semibold text-brandDark/70">Check-out Seguro 🔒</span>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-10">
        <?php if($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-8 mb-8 rounded shadow text-center">
                <h2 class="font-heading text-3xl mb-2 text-green-900">¡Vehículo Reservado!</h2>
                <p class="font-bold text-lg"><?php echo $success; ?></p>
                <a href="index.php" class="inline-block mt-6 bg-brandDark text-white px-8 py-3 rounded font-bold uppercase hover:bg-brandBlue-900 transition-colors">Volver al inicio</a>
            </div>
        <?php else: ?>

            <?php if($error): ?><div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-8 font-bold text-sm">⚠️ <?php echo $error; ?></div><?php endif; ?>

            <form method="POST" action="" id="formReserva" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <input type="hidden" name="id_vehiculo" value="<?php echo $id_vehiculo; ?>">
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-brandMain/20">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Recogida</label>
                                <input type="date" id="input_fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required min="<?php echo date('Y-m-d'); ?>" class="w-full border border-brandMain/30 py-3 px-4 rounded focus:outline-none focus:border-brandBlue-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Entrega</label>
                                <input type="date" id="input_fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="w-full border border-brandMain/30 py-3 px-4 rounded focus:outline-none focus:border-brandBlue-900">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-brandMain/20 flex gap-6 items-center">
                        <div class="w-1/3 bg-brandMain/10 h-24 rounded-lg flex items-center justify-center">
                            <?php if($vehiculo['url_imagen']): ?><img src="<?php echo htmlspecialchars($vehiculo['url_imagen']); ?>" class="max-h-full object-contain"><?php else: ?><span class="text-xs font-bold">Auto</span><?php endif; ?>
                        </div>
                        <div class="w-2/3">
                            <span class="text-xs font-bold uppercase text-brandBlue-900 block"><?php echo htmlspecialchars($vehiculo['nombre_categoria']); ?></span>
                            <h2 class="font-heading text-2xl"><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></h2>
                            <p class="text-brandDark/60 text-sm">Precio base: $<?php echo number_format($precio_dia, 2); ?> / día</p>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-brandMain/20">
                        <h3 class="font-heading text-xl mb-4">Servicios Adicionales</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach($lista_extras as $extra): ?>
                                <label class="flex items-center p-4 border border-brandMain/30 rounded-lg cursor-pointer">
                                    <input type="checkbox" name="extras[]" value="<?php echo $extra['id_extra']; ?>" data-precio="<?php echo $extra['costo_fijo']; ?>" class="extra-checkbox w-5 h-5 text-brandBlue-900">
                                    <div class="ml-3 flex-grow flex justify-between">
                                        <span class="text-sm font-semibold"><?php echo htmlspecialchars($extra['nombre_extra']); ?></span>
                                        <span class="text-sm font-bold text-brandBlue-900">+$<?php echo number_format($extra['costo_fijo'], 2); ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-brandDark text-white p-6 rounded-xl shadow-xl sticky top-24">
                        <h3 class="font-heading text-2xl mb-6 text-brandMain">Resumen de Pago</h3>
                        
                        <div class="space-y-4 text-sm mb-6 border-b border-brandMain/20 pb-6">
                            <div class="flex justify-between"><span class="text-brandMain">Subtotal Vehículo</span><span class="font-bold" id="resumen-subtotal">$<?php echo number_format($subtotal_vehiculo, 2); ?></span></div>
                            <div class="flex justify-between text-brandMain"><span>Extras</span><span class="font-bold" id="resumen-extras">$0.00</span></div>
                            <div class="flex justify-between text-brandMain"><span>Depósito (No Reembolsable)</span><span class="font-bold">$<?php echo number_format($monto_deposito, 2); ?></span></div>
                        </div>

                        <div class="flex justify-between items-center mb-6 border-b border-brandMain/20 pb-6">
                            <span class="text-lg font-heading">Total a Transferir</span>
                            <span class="text-2xl font-bold text-brandBlue-500" id="total-final">$<?php echo number_format($subtotal_vehiculo + $monto_deposito, 2); ?></span>
                        </div>

                        <div class="mb-6">
                            <label class="block text-[10px] uppercase font-bold text-brandMain mb-2">Método de Pago</label>
                            <select name="id_metodo_pago" required class="w-full bg-white/10 border border-brandMain/30 py-2 px-3 rounded text-sm text-white focus:outline-none focus:border-brandBlue-500 mb-4">
                                <?php foreach($metodos_pago as $mp): ?>
                                    <option value="<?php echo $mp['id_metodo_pago']; ?>" <?php echo ($cliente_info['id_metodo_pago_preferido'] == $mp['id_metodo_pago']) ? 'selected' : ''; ?> class="text-brandDark">
                                        <?php echo htmlspecialchars($mp['tipo_metodo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label class="block text-[10px] uppercase font-bold text-brandMain mb-2">Referencia / Recibo</label>
                            <input type="text" name="referencia_pago" placeholder="Ej. 12345678" required class="w-full bg-white text-brandDark py-3 px-3 rounded focus:outline-none focus:ring-2 focus:ring-brandBlue-500 text-sm font-bold">
                        </div>

                        <button type="submit" name="confirmar_reserva" class="w-full bg-brandBlue-900 hover:bg-brandBlue-500 text-white font-bold py-4 rounded-lg transition-all font-heading tracking-widest uppercase text-sm shadow-lg">
                            Confirmar y Pagar
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputInicio = document.getElementById('input_fecha_inicio');
            const inputFin = document.getElementById('input_fecha_fin');
            const checkboxes = document.querySelectorAll('.extra-checkbox');
            
            const precioDia = <?php echo $precio_dia; ?>;
            const depositoFijo = <?php echo $monto_deposito; ?>; // Ahora es fijo por 1 día
            
            const uiSubtotal = document.getElementById('resumen-subtotal');
            const uiTotalExtras = document.getElementById('resumen-extras');
            const uiTotalFinal = document.getElementById('total-final');

            function calcularTotales() {
                const fecha1 = new Date(inputInicio.value);
                const fecha2 = new Date(inputFin.value);
                if(fecha2 <= fecha1) { fecha2.setDate(fecha1.getDate() + 1); inputFin.value = fecha2.toISOString().split('T')[0]; }

                const diffDays = Math.max(1, Math.ceil(Math.abs(fecha2 - fecha1) / (1000 * 60 * 60 * 24)));
                
                const subtotalVehiculo = diffDays * precioDia;
                let totalExtras = 0;
                checkboxes.forEach(chk => { if (chk.checked) totalExtras += parseFloat(chk.getAttribute('data-precio')); });

                uiSubtotal.innerText = '$' + subtotalVehiculo.toFixed(2);
                uiTotalExtras.innerText = '$' + totalExtras.toFixed(2);
                // El total a pagar ahora suma el subtotal + extras + depósito
                uiTotalFinal.innerText = '$' + (subtotalVehiculo + totalExtras + depositoFijo).toFixed(2);
            }

            inputInicio.addEventListener('change', function() { inputFin.min = inputInicio.value; calcularTotales(); });
            inputFin.addEventListener('change', calcularTotales);
            checkboxes.forEach(chk => chk.addEventListener('change', calcularTotales));
        });
    </script>
</body>
</html>