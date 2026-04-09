<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$id_alquiler = $_GET['id'] ?? ($_POST['id_alquiler'] ?? null);
$success = ''; $error = '';

if (!$id_alquiler) { header("Location: mis_reservas.php"); exit; }

// 1. Obtener la Reserva Actual y Validar que pertenezca al cliente
$stmt = $pdo->prepare("
    SELECT a.*, v.marca, v.modelo, v.id_categoria, c.nombre_categoria,
           (SELECT precio_dia FROM tarifas t WHERE t.id_categoria = v.id_categoria AND a.fecha_salida BETWEEN t.fecha_inicio AND t.fecha_fin LIMIT 1) as precio_dia
    FROM alquileres a
    JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
    JOIN categorias c ON v.id_categoria = c.id_categoria
    WHERE a.id_alquiler = ? AND a.id_cliente = ? AND a.estado_alquiler != 'Finalizado'
");
$stmt->execute([$id_alquiler, $id_cliente]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) { die("Reserva no encontrada o ya está finalizada."); }

$precio_dia = $reserva['precio_dia'] ?? 0;
$monto_original_pagado = $reserva['monto_total_alquiler']; // Lo que ya pagó antes

// Obtener extras actuales de esta reserva
$stmt_ext = $pdo->prepare("SELECT id_extra FROM alquiler_extras WHERE id_alquiler = ?");
$stmt_ext->execute([$id_alquiler]);
$extras_actuales = $stmt_ext->fetchAll(PDO::FETCH_COLUMN);

$lista_extras = $pdo->query("SELECT * FROM extras")->fetchAll(PDO::FETCH_ASSOC);
$metodos_pago = $pdo->query("SELECT * FROM metodos_pago")->fetchAll(PDO::FETCH_ASSOC);

// PROCESAR MODIFICACIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_cambios'])) {
    $nueva_fecha_fin = $_POST['fecha_fin'];
    $nuevos_extras = $_POST['extras'] ?? [];
    
    // Validar concurrencia (Que nadie haya reservado el auto en esos nuevos días extra)
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM alquileres WHERE id_vehiculo = ? AND id_alquiler != ? AND estado_alquiler IN ('Activo', 'Reservado') AND DATE(fecha_salida) <= ? AND DATE(fecha_retorno_prevista) > ?");
    $stmt_check->execute([$reserva['id_vehiculo'], $id_alquiler, $nueva_fecha_fin, $reserva['fecha_retorno_prevista']]);
    
    if ($stmt_check->fetchColumn() > 0) {
        $error = "El vehículo ya tiene otra reserva en los días adicionales solicitados.";
    } else {
        try {
            $pdo->beginTransaction();

            // Recalcular Total Nuevo
            $fecha1 = new DateTime(date('Y-m-d', strtotime($reserva['fecha_salida'])));
            $fecha2 = new DateTime($nueva_fecha_fin);
            $nuevos_dias = max(1, $fecha1->diff($fecha2)->days);
            
            $subtotal_nuevo = $nuevos_dias * $precio_dia;
            $total_extras_nuevo = 0;
            
            foreach ($nuevos_extras as $id_extra) {
                $stmt_calc = $pdo->prepare("SELECT costo_fijo FROM extras WHERE id_extra = ?");
                $stmt_calc->execute([$id_extra]);
                $total_extras_nuevo += $stmt_calc->fetchColumn() ?: 0;
            }

            $nuevo_total_alquiler = $subtotal_nuevo + $total_extras_nuevo + $reserva['monto_deposito'];
            $diferencia_a_pagar = $nuevo_total_alquiler - $monto_original_pagado;

            // Si hay diferencia positiva, registrar el pago adicional
            if ($diferencia_a_pagar > 0) {
                $ref_pago = trim($_POST['referencia_pago']);
                $id_metodo = $_POST['id_metodo_pago'];
                
                if (empty($ref_pago)) throw new Exception("Debe ingresar la referencia de pago por el monto adicional.");
                
                $stmt_pago = $pdo->prepare("INSERT INTO pagos (id_alquiler, id_metodo_pago, monto_total, referencia) VALUES (?, ?, ?, ?)");
                $stmt_pago->execute([$id_alquiler, $id_metodo, $diferencia_a_pagar, $ref_pago]);
            }

            // Actualizar Reserva
            $fecha_retorno_dt = $nueva_fecha_fin . " 09:00:00";
            $stmt_upd = $pdo->prepare("UPDATE alquileres SET fecha_retorno_prevista = ?, cantidad_dias = ?, monto_total_alquiler = ? WHERE id_alquiler = ?");
            $stmt_upd->execute([$fecha_retorno_dt, $nuevos_dias, $nuevo_total_alquiler, $id_alquiler]);

            // Actualizar Extras (Borrar viejos, insertar nuevos)
            $pdo->prepare("DELETE FROM alquiler_extras WHERE id_alquiler = ?")->execute([$id_alquiler]);
            if (!empty($nuevos_extras)) {
                $stmt_ins_extra = $pdo->prepare("INSERT INTO alquiler_extras (id_alquiler, id_extra, cantidad) VALUES (?, ?, 1)");
                foreach ($nuevos_extras as $id_extra) $stmt_ins_extra->execute([$id_alquiler, $id_extra]);
            }

            $pdo->commit();
            header("Location: mis_reservas.php?updated=1");
            exit;
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
    <title>Modificar Reserva - LHFM Logistics</title>
    <style>@import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brandMain: '#ABBBC0', brandDark: '#2F2A24', brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' } }, fontFamily: { sans: ['"Google Sans"', 'sans-serif'], heading: ['Fredoka', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-50 text-brandDark font-sans min-h-screen pb-10">

    <nav class="bg-white shadow-sm sticky top-0 z-50 mb-10">
        <div class="max-w-7xl mx-auto px-4 flex items-center h-16">
            <a href="mis_reservas.php" class="text-sm font-bold text-brandBlue-900 hover:underline flex items-center gap-2">
                &larr; Volver a Mis Reservas
            </a>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4">
        <h1 class="font-heading text-3xl mb-2 text-brandDark">Ajustar Detalles de Reserva</h1>
        <p class="text-brandDark/70 mb-8">Ref. #<?php echo str_pad($id_alquiler, 5, "0", STR_PAD_LEFT); ?> • <?php echo htmlspecialchars($reserva['marca'].' '.$reserva['modelo']); ?></p>

        <?php if($error): ?><div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-8 font-bold text-sm"><?php echo $error; ?></div><?php endif; ?>

        <form method="POST" action="" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <input type="hidden" name="id_alquiler" value="<?php echo $id_alquiler; ?>">
            
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-brandMain/20">
                    <h3 class="font-heading text-xl mb-4 text-brandBlue-900">1. Fechas del Viaje</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/50 mb-1">Fecha Salida (No modificable)</label>
                            <input type="text" value="<?php echo date('d/m/Y', strtotime($reserva['fecha_salida'])); ?>" disabled class="w-full bg-gray-100 border border-gray-200 py-3 px-4 rounded text-gray-500 font-bold cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/80 mb-1">Nueva Fecha Entrega</label>
                            <input type="date" id="input_fecha_fin" name="fecha_fin" value="<?php echo date('Y-m-d', strtotime($reserva['fecha_retorno_prevista'])); ?>" min="<?php echo date('Y-m-d', strtotime($reserva['fecha_retorno_prevista'])); ?>" required class="w-full border border-brandMain/50 py-3 px-4 rounded focus:border-brandBlue-900 bg-blue-50 font-bold">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-brandMain/20">
                    <h3 class="font-heading text-xl mb-4 text-brandBlue-900">2. Servicios Adicionales</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($lista_extras as $extra): 
                            $checked = in_array($extra['id_extra'], $extras_actuales) ? 'checked' : '';
                        ?>
                            <label class="flex items-center p-4 border border-brandMain/30 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="extras[]" value="<?php echo $extra['id_extra']; ?>" data-precio="<?php echo $extra['costo_fijo']; ?>" class="extra-checkbox w-5 h-5 text-brandBlue-900" <?php echo $checked; ?>>
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
                    <h3 class="font-heading text-xl mb-4 text-brandMain border-b border-brandMain/20 pb-2">Estado de Cuenta</h3>
                    
                    <div class="space-y-3 text-sm mb-6 border-b border-brandMain/20 pb-4">
                        <div class="flex justify-between text-brandMain/70"><span>Total Original Pagado</span><span>$<?php echo number_format($monto_original_pagado, 2); ?></span></div>
                        <div class="flex justify-between text-brandMain"><span>Nuevo Total Calculado</span><span id="ui-nuevo-total">$<?php echo number_format($monto_original_pagado, 2); ?></span></div>
                    </div>

                    <div class="mb-6 p-4 rounded-lg bg-white/10" id="caja-diferencia">
                        <span class="block text-[10px] uppercase tracking-widest text-brandMain mb-1">Saldo a Pagar Ahora</span>
                        <span class="text-3xl font-bold text-brandBlue-500" id="ui-diferencia">$0.00</span>
                    </div>

                    <div id="seccion-pago" class="hidden mb-6">
                        <p class="text-xs text-orange-300 mb-3 border-l-2 border-orange-400 pl-2">Al agregar días o servicios, se generó un cargo adicional. Por favor registre el pago.</p>
                        
                        <label class="block text-[10px] uppercase font-bold text-brandMain mb-1">Método de Pago</label>
                        <select name="id_metodo_pago" class="w-full bg-white/10 border border-brandMain/30 py-2 px-3 rounded text-sm text-white mb-3">
                            <?php foreach($metodos_pago as $mp): ?>
                                <option value="<?php echo $mp['id_metodo_pago']; ?>" class="text-brandDark"><?php echo htmlspecialchars($mp['tipo_metodo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="referencia_pago" id="input_referencia" placeholder="Nro. Referencia" class="w-full bg-white text-brandDark py-2 px-3 rounded text-sm font-bold">
                    </div>

                    <button type="submit" name="guardar_cambios" class="w-full bg-brandBlue-900 hover:bg-brandBlue-500 text-white font-bold py-4 rounded-lg font-heading tracking-widest uppercase text-sm transition-colors">
                        Actualizar Reserva
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fechaSalida = new Date('<?php echo date('Y-m-d', strtotime($reserva['fecha_salida'])); ?>');
            const inputFin = document.getElementById('input_fecha_fin');
            const checkboxes = document.querySelectorAll('.extra-checkbox');
            
            const precioDia = <?php echo $precio_dia; ?>;
            const depositoFijo = <?php echo $reserva['monto_deposito']; ?>;
            const totalPagado = <?php echo $monto_original_pagado; ?>;
            
            const uiNuevoTotal = document.getElementById('ui-nuevo-total');
            const uiDiferencia = document.getElementById('ui-diferencia');
            const seccionPago = document.getElementById('seccion-pago');
            const inputReferencia = document.getElementById('input_referencia');

            function calcularDiferencias() {
                const fechaFinSeleccionada = new Date(inputFin.value);
                const diffDays = Math.max(1, Math.ceil(Math.abs(fechaFinSeleccionada - fechaSalida) / (1000 * 60 * 60 * 24)));
                
                const subtotalNuevo = diffDays * precioDia;
                let extrasNuevo = 0;
                checkboxes.forEach(chk => { if (chk.checked) extrasNuevo += parseFloat(chk.getAttribute('data-precio')); });

                const nuevoTotal = subtotalNuevo + extrasNuevo + depositoFijo;
                const diferencia = nuevoTotal - totalPagado;

                uiNuevoTotal.innerText = '$' + nuevoTotal.toFixed(2);
                uiDiferencia.innerText = '$' + Math.max(0, diferencia).toFixed(2);

                if (diferencia > 0) {
                    seccionPago.classList.remove('hidden');
                    inputReferencia.required = true;
                    uiDiferencia.classList.replace('text-green-400', 'text-brandBlue-500');
                } else {
                    seccionPago.classList.add('hidden');
                    inputReferencia.required = false;
                    uiDiferencia.classList.replace('text-brandBlue-500', 'text-green-400');
                }
            }

            inputFin.addEventListener('change', calcularDiferencias);
            checkboxes.forEach(chk => chk.addEventListener('change', calcularDiferencias));
            
            calcularDiferencias(); // Cálculo inicial
        });
    </script>
</body>
</html>