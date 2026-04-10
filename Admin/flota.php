<?php
// Admin/flota.php
require_once 'auth_admin.php';
require_once '../conexion.php';

date_default_timezone_set('America/Caracas');

$success = ''; $error = '';

// --- 1. AGREGAR NUEVO VEHÍCULO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_vehiculo'])) {
    $placa = strtoupper(trim($_POST['placa']));
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $anio = $_POST['anio'];
    $color = trim($_POST['color']);
    $pasajeros = $_POST['capacidad_pasajeros'];
    $id_categoria = $_POST['id_categoria'];
    $url_imagen = trim($_POST['url_imagen']);

    if (!empty($placa) && !empty($marca) && !empty($modelo) && !empty($anio) && !empty($color)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehiculos (placa, marca, modelo, anio, color, capacidad_pasajeros, id_categoria, url_imagen, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Disponible')");
            $stmt->execute([$placa, $marca, $modelo, $anio, $color, $pasajeros, $id_categoria, $url_imagen]);
            $success = "El vehículo $marca $modelo ha sido agregado exitosamente a la flota.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $error = "La placa '$placa' ya está registrada.";
            else $error = "Error al agregar vehículo: " . $e->getMessage();
        }
    }
}

// --- 2. ENVIAR A MANTENIMIENTO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_mantenimiento'])) {
    $id_vehiculo = $_POST['id_vehiculo'];
    $tipo = trim($_POST['tipo_mantenimiento']);
    $fecha_fin = str_replace('T', ' ', $_POST['fecha_fin_estimada']) . ':00';
    $costo = $_POST['costo_mantenimiento'];
    $obs = trim($_POST['observaciones']);
    $fecha_inicio = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE vehiculos SET estado = 'Mantenimiento' WHERE id_vehiculo = ?")->execute([$id_vehiculo]);
        $stmt_mant = $pdo->prepare("INSERT INTO mantenimientos (id_vehiculo, tipo_mantenimiento, fecha_inicio, fecha_fin_estimada, costo_mantenimiento, observaciones, estado_mantenimiento) VALUES (?, ?, ?, ?, ?, ?, 'En Proceso')");
        $stmt_mant->execute([$id_vehiculo, $tipo, $fecha_inicio, $fecha_fin, $costo, $obs]);
        $pdo->commit();
        $success = "Vehículo enviado al taller correctamente.";
    } catch (Exception $e) { $pdo->rollBack(); $error = "Error: " . $e->getMessage(); }
}

// --- 3. FINALIZAR MANTENIMIENTO (MEJORADO) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_mantenimiento'])) {
    try {
        $pdo->beginTransaction();
        
        // Si se envió el ID del mantenimiento (desde la caja naranja)
        if (!empty($_POST['id_mantenimiento'])) {
            $pdo->prepare("UPDATE mantenimientos SET estado_mantenimiento = 'Completado' WHERE id_mantenimiento = ?")->execute([$_POST['id_mantenimiento']]);
        } else {
            // Si se libera desde la tarjeta, busca cualquier mantenimiento activo y lo cierra
            $pdo->prepare("UPDATE mantenimientos SET estado_mantenimiento = 'Completado' WHERE id_vehiculo = ? AND estado_mantenimiento = 'En Proceso'")->execute([$_POST['id_vehiculo']]);
        }
        
        // Se actualiza el vehículo a Disponible
        $pdo->prepare("UPDATE vehiculos SET estado = 'Disponible' WHERE id_vehiculo = ?")->execute([$_POST['id_vehiculo']]);
        $pdo->commit();
        $success = "El vehículo ha vuelto a la flota Disponible.";
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        $error = "Error: " . $e->getMessage(); 
    }
}

// --- 4. RETIRAR VEHÍCULO (BORRADO LÓGICO) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['retirar_vehiculo'])) {
    try {
        $pdo->prepare("UPDATE vehiculos SET estado = 'Retirado' WHERE id_vehiculo = ?")->execute([$_POST['id_vehiculo']]);
        $success = "Vehículo retirado. Ya no aparecerá en el catálogo de clientes.";
    } catch (Exception $e) { $error = "Error al retirar vehículo: " . $e->getMessage(); }
}

// --- 5. ACTUALIZAR TARIFA (PRECIOS) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_tarifa'])) {
    try {
        $pdo->prepare("UPDATE tarifas SET precio_dia = ? WHERE id_tarifa = ?")->execute([$_POST['precio_dia'], $_POST['id_tarifa']]);
        $success = "Precio de tarifa actualizado correctamente.";
    } catch (Exception $e) { $error = "Error al actualizar precio: " . $e->getMessage(); }
}

// --- 6. ACTUALIZAR EXTRAS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_extra'])) {
    try {
        $pdo->prepare("UPDATE extras SET costo_fijo = ? WHERE id_extra = ?")->execute([$_POST['costo_fijo'], $_POST['id_extra']]);
        $success = "Costo del extra actualizado correctamente.";
    } catch (Exception $e) { $error = "Error al actualizar extra: " . $e->getMessage(); }
}

// --- CONSULTAS PARA LA VISTA ---
$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll(PDO::FETCH_ASSOC);
$tarifas = $pdo->query("SELECT t.*, c.nombre_categoria FROM tarifas t JOIN categorias c ON t.id_categoria = c.id_categoria ORDER BY c.nombre_categoria, t.fecha_inicio")->fetchAll(PDO::FETCH_ASSOC);
$lista_extras = $pdo->query("SELECT * FROM extras ORDER BY tipo, nombre_extra")->fetchAll(PDO::FETCH_ASSOC);

$vehiculos = $pdo->query("
    SELECT v.*, c.nombre_categoria 
    FROM vehiculos v 
    JOIN categorias c ON v.id_categoria = c.id_categoria 
    ORDER BY FIELD(v.estado, 'Disponible', 'Alquilado', 'Mantenimiento', 'Retirado'), v.marca ASC
")->fetchAll(PDO::FETCH_ASSOC);

$mantenimientos_activos = $pdo->query("
    SELECT m.*, v.marca, v.modelo, v.placa 
    FROM mantenimientos m 
    JOIN vehiculos v ON m.id_vehiculo = v.id_vehiculo 
    WHERE m.estado_mantenimiento = 'En Proceso'
")->fetchAll(PDO::FETCH_ASSOC);

$nombre_admin = $_SESSION['cliente_nombre'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Flota de Vehículos - Admin</title>
    <style>@import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brandMain: '#ABBBC0', brandDark: '#2F2A24', brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' } }, fontFamily: { sans: ['"Google Sans"', 'sans-serif'], heading: ['Fredoka', 'sans-serif'], brand: ['Righteous', 'cursive'] } } } }
    </script>
</head>
<body class="bg-gray-50 text-brandDark font-sans flex min-h-screen">

    <aside class="w-72 bg-brandDark text-white flex flex-col shadow-2xl z-50 fixed h-full">
        <div class="p-8 border-b border-white/10">
            <span class="font-brand text-2xl tracking-wide">LHFM <span class="text-brandMain">LOGISTICS</span></span>
            <p class="text-[10px] font-heading tracking-[0.2em] text-brandMain/60 uppercase mt-2">Panel Administrativo</p>
        </div>
        <nav class="flex-1 p-6 space-y-3">
            <a href="index.php" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">Dashboard</a>
            <a href="flota.php" class="flex items-center gap-3 py-3 px-4 bg-brandBlue-900/40 rounded-lg font-heading tracking-wider uppercase text-xs border border-brandBlue-900/50 text-white">Flota y Vehículos</a>
            <a href="clientes.php" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">Clientes</a>
        </nav>
        <div class="p-6 border-t border-white/10 space-y-4">
            <a href="../Clientes/index.php" class="block text-center bg-brandMain/10 hover:bg-brandMain/20 text-white font-heading tracking-widest text-[10px] py-3 rounded uppercase border border-white/5">Ir a Vista Cliente</a>
            <a href="../logout.php" class="block text-center text-red-400 font-heading tracking-widest text-[10px] uppercase hover:underline">Cerrar Sesión Segura</a>
        </div>
    </aside>

    <main class="ml-72 flex-1 p-10">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h2 class="font-heading text-4xl text-brandDark uppercase tracking-tight">Gestión de Flota</h2>
                <div class="h-1 w-20 bg-brandBlue-900 mt-2"></div>
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('modalTarifas').classList.remove('hidden')" class="bg-white border border-brandMain/30 text-brandBlue-900 px-5 py-3 rounded-lg font-bold shadow-sm hover:bg-gray-50 transition-colors flex items-center gap-2">
                    💰 Precios y Tarifas
                </button>
                <button onclick="document.getElementById('modalExtras').classList.remove('hidden')" class="bg-white border border-brandMain/30 text-brandBlue-900 px-5 py-3 rounded-lg font-bold shadow-sm hover:bg-gray-50 transition-colors flex items-center gap-2">
                    ✨ Ajustar Extras
                </button>
                <button onclick="document.getElementById('modalAddVehicle').classList.remove('hidden')" class="bg-brandBlue-900 text-white px-5 py-3 rounded-lg font-bold shadow-sm hover:bg-brandDark transition-colors flex items-center gap-2">
                    ➕ Nuevo Vehículo
                </button>
            </div>
        </header>

        <?php if($success): ?><div class="bg-green-100 border border-green-300 text-green-800 p-4 mb-8 rounded-lg font-bold shadow-sm"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 border border-red-300 text-red-800 p-4 mb-8 rounded-lg font-bold shadow-sm"><?php echo $error; ?></div><?php endif; ?>

        <?php if(!empty($mantenimientos_activos)): ?>
            <div class="mb-10 bg-orange-50 border border-orange-200 rounded-2xl p-6 shadow-sm">
                <h3 class="font-heading text-xl text-orange-800 mb-4 flex items-center gap-2"><span>🔧</span> Vehículos en Taller / Reparación</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach($mantenimientos_activos as $m): ?>
                        <div class="bg-white p-4 rounded-xl border border-orange-200 flex justify-between items-center shadow-sm">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-orange-600"><?php echo htmlspecialchars($m['tipo_mantenimiento']); ?></span>
                                <h4 class="font-heading text-lg"><?php echo htmlspecialchars($m['marca'].' '.$m['modelo']); ?> <span class="text-sm font-sans text-gray-500">(<?php echo htmlspecialchars($m['placa']); ?>)</span></h4>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-1"><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($m['observaciones']); ?></p>
                            </div>
                            <form method="POST" action="" onsubmit="return confirm('¿Confirma que el vehículo está reparado?');">
                                <input type="hidden" name="id_mantenimiento" value="<?php echo $m['id_mantenimiento']; ?>">
                                <input type="hidden" name="id_vehiculo" value="<?php echo $m['id_vehiculo']; ?>">
                                <button type="submit" name="finalizar_mantenimiento" class="bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold uppercase px-4 py-3 rounded shadow transition-colors tracking-widest">
                                    Liberar Vehículo
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-brandMain/20 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-brandMain/10 bg-gray-50/50">
                <h3 class="font-heading tracking-widest uppercase text-sm text-brandBlue-900 font-extrabold">Inventario Total de Flota</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 p-6">
                <?php foreach($vehiculos as $v): ?>
                    <div class="border border-brandMain/20 rounded-xl overflow-hidden hover:shadow-lg transition-shadow bg-white flex flex-col relative <?php echo ($v['estado'] == 'Retirado') ? 'opacity-60 grayscale' : ''; ?>">
                        
                        <?php 
                            $badge_color = 'bg-green-100 text-green-800'; 
                            if($v['estado'] == 'Alquilado') $badge_color = 'bg-blue-100 text-blue-800';
                            if($v['estado'] == 'Mantenimiento') $badge_color = 'bg-orange-100 text-orange-800';
                            if($v['estado'] == 'Retirado') $badge_color = 'bg-red-100 text-red-800 line-through';
                        ?>
                        <div class="absolute top-3 right-3 z-10">
                            <span class="text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest <?php echo $badge_color; ?> shadow-sm">
                                <?php echo htmlspecialchars($v['estado']); ?>
                            </span>
                        </div>

                        <div class="h-32 bg-gray-100 flex items-center justify-center p-4">
                            <?php if($v['url_imagen']): ?><img src="<?php echo htmlspecialchars($v['url_imagen']); ?>" class="max-h-full object-contain"><?php else: ?><span class="text-xs uppercase font-bold text-gray-400">Sin Foto</span><?php endif; ?>
                        </div>

                        <div class="p-4 flex-grow flex flex-col">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-brandMain"><?php echo htmlspecialchars($v['nombre_categoria']); ?></span>
                                <span class="text-[10px] font-bold bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($v['placa']); ?></span>
                            </div>
                            
                            <h3 class="font-heading text-xl text-brandDark mb-2 leading-tight"><?php echo htmlspecialchars($v['marca'].' '.$v['modelo']); ?></h3>
                            
                            <div class="grid grid-cols-2 gap-2 text-xs text-brandDark/70 mb-4 flex-grow">
                                <div>🎨 Color: <?php echo htmlspecialchars($v['color']); ?></div>
                                <div>👥 Pasaj: <?php echo htmlspecialchars($v['capacidad_pasajeros']); ?></div>
                            </div>

                            <div class="mt-auto grid grid-cols-2 gap-2 pt-4">
                                <?php if($v['estado'] == 'Disponible'): ?>
                                    <button onclick="abrirTaller(<?php echo $v['id_vehiculo']; ?>, '<?php echo htmlspecialchars($v['placa'].' - '.$v['marca']); ?>')" class="border border-orange-300 text-orange-600 hover:bg-orange-50 font-bold py-2 rounded text-[10px] uppercase tracking-widest transition-colors">Taller</button>
                                    
                                    <form method="POST" action="" onsubmit="return confirm('¿Seguro que deseas retirar este vehículo? No se podrá volver a alquilar.');">
                                        <input type="hidden" name="id_vehiculo" value="<?php echo $v['id_vehiculo']; ?>">
                                        <button type="submit" name="retirar_vehiculo" class="w-full bg-red-50 border border-red-200 text-red-600 hover:bg-red-600 hover:text-white font-bold py-2 rounded text-[10px] uppercase tracking-widest transition-colors">Retirar</button>
                                    </form>
                                <?php elseif($v['estado'] == 'Retirado'): ?>
                                    <div class="col-span-2 text-center text-[10px] font-bold text-red-400 uppercase tracking-widest">Fuera de Servicio Permanente</div>
                                <?php elseif($v['estado'] == 'Alquilado'): ?>
                                    <div class="col-span-2 text-center text-[10px] font-bold text-brandBlue-600 bg-brandBlue-50 py-2 rounded uppercase tracking-widest">En Uso Por Cliente</div>
                                <?php elseif($v['estado'] == 'Mantenimiento'): ?>
                                    <form method="POST" action="" class="col-span-2" onsubmit="return confirm('¿Seguro que deseas liberar este vehículo y marcarlo como Disponible?');">
                                        <input type="hidden" name="id_vehiculo" value="<?php echo $v['id_vehiculo']; ?>">
                                        <button type="submit" name="finalizar_mantenimiento" class="w-full bg-green-50 border border-green-200 text-green-700 hover:bg-green-600 hover:text-white font-bold py-2 rounded text-[10px] uppercase tracking-widest transition-colors">Liberar de Taller</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <div id="modalTarifas" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-brandDark p-6 flex justify-between items-center">
                <h3 class="font-heading text-2xl text-white">💰 Ajuste de Tarifas por Categoría</h3>
                <button onclick="document.getElementById('modalTarifas').classList.add('hidden')" class="text-white hover:text-red-400 text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 max-h-[70vh] overflow-y-auto bg-gray-50">
                <p class="text-xs text-brandDark/70 mb-4">Actualiza el precio diario de los vehículos. Se aplicará a las nuevas reservas automáticamente.</p>
                <div class="space-y-3">
                    <?php foreach($tarifas as $t): ?>
                        <form method="POST" action="" class="bg-white p-4 rounded-lg border border-brandMain/20 flex items-center justify-between shadow-sm">
                            <input type="hidden" name="id_tarifa" value="<?php echo $t['id_tarifa']; ?>">
                            <div class="w-2/3">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-brandBlue-900"><?php echo htmlspecialchars($t['nombre_categoria']); ?></span>
                                <h4 class="font-heading text-lg"><?php echo htmlspecialchars($t['descripcion']); ?></h4>
                                <p class="text-xs text-brandDark/60">Vigencia: <?php echo date('d/m/Y', strtotime($t['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($t['fecha_fin'])); ?></p>
                            </div>
                            <div class="w-1/3 flex items-center gap-2">
                                <span class="text-gray-400 font-bold">$</span>
                                <input type="number" step="0.01" name="precio_dia" value="<?php echo $t['precio_dia']; ?>" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:border-brandBlue-900 font-bold text-lg text-brandDark">
                                <button type="submit" name="actualizar_tarifa" class="bg-brandBlue-900 hover:bg-brandDark text-white px-4 py-2 rounded font-bold shadow text-xs">Guardar</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="modalExtras" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-brandDark p-6 flex justify-between items-center">
                <h3 class="font-heading text-2xl text-white">✨ Ajuste de Extras y Servicios</h3>
                <button onclick="document.getElementById('modalExtras').classList.add('hidden')" class="text-white hover:text-red-400 text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 max-h-[70vh] overflow-y-auto bg-gray-50">
                <div class="space-y-3">
                    <?php foreach($lista_extras as $e): ?>
                        <form method="POST" action="" class="bg-white p-4 rounded-lg border border-brandMain/20 flex items-center justify-between shadow-sm">
                            <input type="hidden" name="id_extra" value="<?php echo $e['id_extra']; ?>">
                            <div class="w-1/2">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-brandMain"><?php echo htmlspecialchars($e['tipo']); ?></span>
                                <h4 class="font-heading text-lg"><?php echo htmlspecialchars($e['nombre_extra']); ?></h4>
                            </div>
                            <div class="w-1/2 flex items-center gap-2 justify-end">
                                <span class="text-gray-400 font-bold">$</span>
                                <input type="number" step="0.01" name="costo_fijo" value="<?php echo $e['costo_fijo']; ?>" class="w-24 border border-brandMain/30 py-2 px-3 rounded focus:border-brandBlue-900 font-bold text-center text-brandDark">
                                <button type="submit" name="actualizar_extra" class="bg-brandBlue-900 hover:bg-brandDark text-white px-4 py-2 rounded font-bold shadow text-xs">Guardar</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="modalAddVehicle" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-brandDark p-6 flex justify-between items-center">
                <h3 class="font-heading text-2xl text-white">Registrar Nuevo Vehículo</h3>
                <button onclick="document.getElementById('modalAddVehicle').classList.add('hidden')" class="text-white hover:text-red-400 text-2xl font-bold">&times;</button>
            </div>
            <form method="POST" action="" class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Placa Única</label>
                        <input type="text" name="placa" required placeholder="Ej. AM-123-NE" class="w-full border border-brandMain/30 py-2 px-3 rounded bg-gray-50 font-bold uppercase">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Categoría</label>
                        <select name="id_categoria" required class="w-full border border-brandMain/30 py-2 px-3 rounded bg-white font-bold">
                            <?php foreach($categorias as $c): ?><option value="<?php echo $c['id_categoria']; ?>"><?php echo htmlspecialchars($c['nombre_categoria']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Marca</label><input type="text" name="marca" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Modelo</label><input type="text" name="modelo" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Año</label><input type="number" name="anio" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Color</label><input type="text" name="color" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Pasajeros</label><input type="number" name="capacidad_pasajeros" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                </div>
                <div class="mb-8"><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">URL Imagen</label><input type="url" name="url_imagen" class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                <button type="submit" name="agregar_vehiculo" class="w-full bg-brandBlue-900 text-white font-bold py-4 rounded-lg shadow-md uppercase text-sm">Guardar Vehículo</button>
            </form>
        </div>
    </div>

    <div id="modalTaller" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-xl rounded-2xl shadow-2xl overflow-hidden border-t-4 border-orange-500">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="font-heading text-2xl text-brandDark">Reporte de Taller</h3>
                    <p id="taller_nombre_vehiculo" class="text-sm font-bold text-orange-600 mt-1"></p>
                </div>
                <button onclick="document.getElementById('modalTaller').classList.add('hidden')" class="text-gray-400 text-3xl font-bold">&times;</button>
            </div>
            <form method="POST" action="" class="p-8">
                <input type="hidden" name="id_vehiculo" id="taller_id_vehiculo">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Intervención</label>
                        <select name="tipo_mantenimiento" required class="w-full border border-brandMain/30 py-2 px-3 rounded font-bold">
                            <option value="Preventivo">Preventivo</option><option value="Correctivo">Correctivo</option><option value="Siniestro">Siniestro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Costo ($)</label>
                        <input type="number" step="0.01" name="costo_mantenimiento" required class="w-full border border-brandMain/30 py-2 px-3 rounded font-bold text-orange-700 bg-orange-50">
                    </div>
                </div>
                <div class="mb-6"><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Fecha Estimada Salida</label><input type="datetime-local" name="fecha_fin_estimada" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                <div class="mb-8"><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Diagnóstico</label><textarea name="observaciones" required rows="3" class="w-full border border-brandMain/30 py-2 px-3 rounded"></textarea></div>
                <button type="submit" name="enviar_mantenimiento" class="w-full bg-orange-600 text-white font-bold py-4 rounded-lg uppercase text-sm shadow-md">Enviar a Taller</button>
            </form>
        </div>
    </div>

    <script>
        function abrirTaller(id_vehiculo, nombre_completo) {
            document.getElementById('taller_id_vehiculo').value = id_vehiculo;
            document.getElementById('taller_nombre_vehiculo').innerText = nombre_completo;
            document.getElementById('modalTaller').classList.remove('hidden');
        }
    </script>
</body>
</html>