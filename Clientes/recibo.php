<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$id_alquiler = $_GET['id'] ?? null;

if (!$id_alquiler) { die("Recibo no válido."); }

// Obtener datos generales de la reserva y el cliente
$stmt = $pdo->prepare("
    SELECT a.*, v.marca, v.modelo, v.placa, c.nombre_categoria, cli.nombre, cli.apellido, cli.identificacion, cli.licencia_conducir, cli.direccion
    FROM alquileres a
    JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
    JOIN categorias c ON v.id_categoria = c.id_categoria
    JOIN clientes cli ON a.id_cliente = cli.id_cliente
    WHERE a.id_alquiler = ? AND a.id_cliente = ?
");
$stmt->execute([$id_alquiler, $id_cliente]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) { die("No se encontró el recibo o no tiene permisos."); }

// Obtener Extras
$stmt_extras = $pdo->prepare("
    SELECT e.nombre_extra, e.costo_fijo, ae.cantidad 
    FROM alquiler_extras ae
    JOIN extras e ON ae.id_extra = e.id_extra
    WHERE ae.id_alquiler = ?
");
$stmt_extras->execute([$id_alquiler]);
$extras = $stmt_extras->fetchAll(PDO::FETCH_ASSOC);

// Obtener Pagos Registrados
$stmt_pagos = $pdo->prepare("
    SELECT p.*, m.tipo_metodo 
    FROM pagos p
    JOIN metodos_pago m ON p.id_metodo_pago = m.id_metodo_pago
    WHERE p.id_alquiler = ?
    ORDER BY p.fecha_pago ASC
");
$stmt_pagos->execute([$id_alquiler]);
$pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

$subtotal_base = $reserva['monto_total_alquiler'] - $reserva['monto_deposito'];
$total_pagado_historial = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo #<?php echo str_pad($id_alquiler, 5, "0", STR_PAD_LEFT); ?> - LHFM Logistics</title>
    <style>@import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brandMain: '#ABBBC0', brandDark: '#2F2A24', brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' } }, fontFamily: { sans: ['"Google Sans"', 'sans-serif'], heading: ['Fredoka', 'sans-serif'], brand: ['Righteous', 'cursive'] } } } }
    </script>
    <style>
        /* Estilos para impresión */
        @media print {
            body { background-color: white !important; }
            .no-print { display: none !important; }
            .print-border { border: 1px solid #e5e7eb !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 text-brandDark font-sans py-10">

    <div class="max-w-3xl mx-auto px-4">
        
        <div class="mb-6 flex justify-between items-center no-print">
            <a href="mis_reservas.php" class="text-sm font-bold text-brandBlue-900 hover:underline">&larr; Volver</a>
            <button onclick="window.print()" class="bg-brandDark text-white px-4 py-2 rounded text-sm font-bold shadow hover:bg-brandBlue-900 transition-colors uppercase tracking-widest">
                Imprimir Recibo
            </button>
        </div>

        <div class="bg-white p-10 rounded-xl shadow-xl print-border border border-brandMain/20 relative overflow-hidden">
            
            <?php if($reserva['estado_alquiler'] == 'Cancelado'): ?>
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-red-500 opacity-10 font-bold text-9xl uppercase tracking-widest pointer-events-none transform -rotate-45">
                    CANCELADO
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-start border-b border-brandMain/20 pb-8 mb-8">
                <div>
                    <h1 class="font-brand text-3xl text-brandBlue-900">LHFM <span class="text-brandMain">LOGISTICS</span></h1>
                    <p class="text-xs text-brandDark/60 uppercase tracking-widest mt-1">Margarita Island Rentals</p>
                    <p class="text-sm mt-4 text-brandDark/70">Juangriego, Nueva Esparta<br>Venezuela</p>
                </div>
                <div class="text-right">
                    <h2 class="font-heading text-4xl text-brandMain uppercase tracking-wider">Recibo</h2>
                    <p class="font-bold text-lg mt-1">Nro. #<?php echo str_pad($id_alquiler, 5, "0", STR_PAD_LEFT); ?></p>
                    <p class="text-sm text-brandDark/60 mt-1">Fecha: <?php echo date('d/m/Y', strtotime($reserva['fecha_salida'])); ?></p>
                    <span class="inline-block mt-2 bg-brandBlue-50 text-brandBlue-900 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest border border-brandBlue-200">
                        Estado: <?php echo htmlspecialchars($reserva['estado_alquiler']); ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="text-[10px] uppercase font-bold text-brandMain mb-2 tracking-widest">Facturado a:</h3>
                    <p class="font-heading text-xl"><?php echo htmlspecialchars($reserva['nombre'] . ' ' . $reserva['apellido']); ?></p>
                    <p class="text-sm text-brandDark/80 mt-1">Documento: <?php echo htmlspecialchars($reserva['identificacion']); ?></p>
                    <p class="text-sm text-brandDark/80">Licencia: <?php echo htmlspecialchars($reserva['licencia_conducir']); ?></p>
                    <p class="text-sm text-brandDark/80 mt-2 line-clamp-2"><?php echo htmlspecialchars($reserva['direccion']); ?></p>
                </div>
                <div>
                    <h3 class="text-[10px] uppercase font-bold text-brandMain mb-2 tracking-widest">Detalles del Vehículo:</h3>
                    <p class="font-heading text-xl"><?php echo htmlspecialchars($reserva['marca'] . ' ' . $reserva['modelo']); ?></p>
                    <p class="text-sm text-brandDark/80 mt-1">Categoría: <?php echo htmlspecialchars($reserva['nombre_categoria']); ?></p>
                    <p class="text-sm text-brandDark/80 font-bold bg-gray-100 inline-block px-2 py-1 mt-2 rounded border border-gray-200">Placa: <?php echo htmlspecialchars($reserva['placa']); ?></p>
                </div>
            </div>

            <table class="w-full text-left border-collapse mb-8">
                <thead>
                    <tr class="border-b-2 border-brandDark">
                        <th class="py-3 text-xs uppercase text-brandDark/70">Concepto</th>
                        <th class="py-3 text-xs uppercase text-brandDark/70 text-center">Cant.</th>
                        <th class="py-3 text-xs uppercase text-brandDark/70 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <tr class="border-b border-brandMain/20">
                        <td class="py-4">
                            <strong class="text-brandDark">Alquiler de Vehículo</strong><br>
                            <span class="text-xs text-brandDark/60">Del <?php echo date('d/m/Y', strtotime($reserva['fecha_salida'])); ?> al <?php echo date('d/m/Y', strtotime($reserva['fecha_retorno_prevista'])); ?></span>
                        </td>
                        <td class="py-4 text-center font-bold"><?php echo $reserva['cantidad_dias']; ?> días</td>
                        <td class="py-4 text-right font-bold">$<?php echo number_format($subtotal_base, 2); ?></td>
                    </tr>
                    <?php foreach($extras as $ext): ?>
                        <tr class="border-b border-brandMain/20">
                            <td class="py-4"><span class="text-brandDark/80">Extra: <?php echo htmlspecialchars($ext['nombre_extra']); ?></span></td>
                            <td class="py-4 text-center text-brandDark/80">1</td>
                            <td class="py-4 text-right text-brandDark/80">$<?php echo number_format($ext['costo_fijo'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="border-b border-brandMain/20 bg-brandBlue-50/50">
                        <td class="py-4"><strong class="text-brandBlue-900">Depósito de Garantía</strong><br><span class="text-[10px] text-brandBlue-900/60 uppercase tracking-widest">No Reembolsable</span></td>
                        <td class="py-4 text-center">1</td>
                        <td class="py-4 text-right font-bold text-brandBlue-900">$<?php echo number_format($reserva['monto_deposito'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="flex justify-end mb-10">
                <div class="w-1/2">
                    <div class="flex justify-between items-center py-2 border-b border-brandMain/20 text-sm">
                        <span class="text-brandDark/70">Subtotal Operación</span>
                        <span class="font-bold">$<?php echo number_format($reserva['monto_total_alquiler'], 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-4 text-xl">
                        <span class="font-heading text-brandDark">Total Facturado</span>
                        <span class="font-bold text-brandBlue-500">$<?php echo number_format($reserva['monto_total_alquiler'], 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-6 border border-brandMain/20">
                <h3 class="text-xs uppercase font-bold text-brandMain tracking-widest mb-4">Pagos Recibidos y Procesados</h3>
                <?php if(empty($pagos)): ?>
                    <p class="text-sm text-red-500 italic">No hay pagos registrados para esta reserva.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach($pagos as $p): 
                            $total_pagado_historial += $p['monto_total'];
                        ?>
                            <div class="flex justify-between text-sm items-center border-b border-gray-200 pb-2 last:border-0 last:pb-0">
                                <div>
                                    <span class="font-bold block"><?php echo htmlspecialchars($p['tipo_metodo']); ?></span>
                                    <span class="text-xs text-brandDark/60">Ref: <?php echo htmlspecialchars($p['referencia']); ?> • <?php echo date('d/m/Y H:i', strtotime($p['fecha_pago'])); ?></span>
                                </div>
                                <div class="font-bold text-green-600">+$<?php echo number_format($p['monto_total'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-300 flex justify-between items-center font-bold">
                        <span class="text-brandDark uppercase tracking-widest text-xs">Total Abonado</span>
                        <span class="text-brandDark">$<?php echo number_format($total_pagado_historial, 2); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-10 text-center text-[10px] text-brandDark/50 uppercase tracking-widest border-t border-brandMain/20 pt-6">
                <p>Este documento es comprobante oficial de su alquiler. Válido sin firma ni sello.</p>
                <p class="mt-1">Gracias por viajar con nosotros.</p>
            </div>
        </div>
    </div>
</body>
</html>