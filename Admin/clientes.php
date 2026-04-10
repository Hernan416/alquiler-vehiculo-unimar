<?php
// Admin/clientes.php
require_once 'auth_admin.php';
require_once '../conexion.php';

date_default_timezone_set('America/Caracas');

$success = ''; $error = '';
$admin_en_sesion = $_SESSION['usuario_id']; // Para evitar que el admin se borre a sí mismo

// --- 1. AGREGAR NUEVO USUARIO/CLIENTE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_cliente'])) {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $identificacion = $_POST['identificacion'];
    $telefono = trim($_POST['telefono']);
    $licencia = trim($_POST['licencia_conducir']);
    $direccion = trim($_POST['direccion']);
    $id_metodo_pago = $_POST['id_metodo_pago_preferido'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $rol = $_POST['rol']; // NUEVO: Capturamos el rol (admin o cliente)

    if (!empty($nombre) && !empty($apellido) && !empty($identificacion) && !empty($email) && !empty($password)) {
        try {
            $pdo->beginTransaction();
            
            $stmt_check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetch()) throw new Exception("El correo electrónico ya está registrado.");

            // Insertar en usuarios con el ROL seleccionado
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_user = $pdo->prepare("INSERT INTO usuarios (email, password, rol, activo) VALUES (?, ?, ?, 1)");
            $stmt_user->execute([$email, $hashed_password, $rol]);
            $id_usuario = $pdo->lastInsertId();

            // Insertar perfil en clientes
            $stmt_cli = $pdo->prepare("INSERT INTO clientes (nombre, apellido, email, identificacion, telefono, licencia_conducir, direccion, id_metodo_pago_preferido, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_cli->execute([$nombre, $apellido, $email, $identificacion, $telefono, $licencia, $direccion, $id_metodo_pago, $id_usuario]);

            $pdo->commit();
            $tipo_texto = $rol == 'admin' ? "Administrador" : "Cliente";
            $success = "El $tipo_texto $nombre $apellido ha sido registrado exitosamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar: " . $e->getMessage();
        }
    } else {
        $error = "Faltan campos obligatorios.";
    }
}

// --- 2. EDITAR USUARIO/CLIENTE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_cliente'])) {
    $id_cliente = $_POST['id_cliente'];
    $id_usuario_editar = $_POST['id_usuario']; // Para actualizar el rol
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $identificacion = $_POST['identificacion'];
    $telefono = trim($_POST['telefono']);
    $licencia = trim($_POST['licencia_conducir']);
    $direccion = trim($_POST['direccion']);
    $id_metodo_pago = $_POST['id_metodo_pago_preferido'];
    $rol = $_POST['rol'];

    try {
        $pdo->beginTransaction();

        // Evitar que el admin se quite su propio rol de admin por accidente
        if ($id_usuario_editar == $admin_en_sesion && $rol != 'admin') {
            throw new Exception("Por seguridad, no puedes quitarte el rol de Administrador a ti mismo.");
        }

        // Actualizar tabla clientes
        $stmt_upd = $pdo->prepare("UPDATE clientes SET nombre = ?, apellido = ?, identificacion = ?, telefono = ?, licencia_conducir = ?, direccion = ?, id_metodo_pago_preferido = ? WHERE id_cliente = ?");
        $stmt_upd->execute([$nombre, $apellido, $identificacion, $telefono, $licencia, $direccion, $id_metodo_pago, $id_cliente]);
        
        // Actualizar tabla usuarios (Rol)
        if ($id_usuario_editar) {
            $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id_usuario = ?")->execute([$rol, $id_usuario_editar]);
        }

        $pdo->commit();
        $success = "Los datos del usuario han sido actualizados.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// --- 3. ELIMINAR USUARIO (HARD DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_cliente'])) {
    $id_cliente_eliminar = $_POST['id_cliente'];
    $id_usuario_eliminar = $_POST['id_usuario'];

    if ($id_usuario_eliminar == $admin_en_sesion) {
        $error = "Medida de Seguridad: No puedes eliminar tu propia cuenta mientras estás en sesión.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificación Crítica: ¿Tiene historial contable?
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM alquileres WHERE id_cliente = ?");
            $stmt_check->execute([$id_cliente_eliminar]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar esta cuenta porque tiene facturas y reservas asociadas. Para mantener la integridad contable de la base de datos, por favor <b>Suspenda</b> la cuenta en lugar de eliminarla.");
            }

            // Si está limpio, se borra en cascada manual
            $pdo->prepare("DELETE FROM clientes WHERE id_cliente = ?")->execute([$id_cliente_eliminar]);
            if ($id_usuario_eliminar) {
                $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$id_usuario_eliminar]);
            }
            
            $pdo->commit();
            $success = "El usuario y su perfil han sido borrados permanentemente del sistema.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// --- 4. SUSPENDER / ACTIVAR (BANEO) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_estado'])) {
    $id_usuario = $_POST['id_usuario'];
    $nuevo_estado = $_POST['nuevo_estado'];

    if ($id_usuario == $admin_en_sesion) {
        $error = "No puedes suspender tu propia cuenta.";
    } elseif ($id_usuario) {
        try {
            $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?")->execute([$nuevo_estado, $id_usuario]);
            $success = $nuevo_estado == 1 ? "La cuenta ha sido reactivada." : "La cuenta ha sido suspendida exitosamente.";
        } catch (Exception $e) { $error = "Error al cambiar estado: " . $e->getMessage(); }
    }
}

// --- CONSULTAS PARA LA VISTA ---
$metodos_pago = $pdo->query("SELECT * FROM metodos_pago")->fetchAll(PDO::FETCH_ASSOC);

$query_clientes = "
    SELECT c.*, 
           u.id_usuario, u.activo, u.fecha_registro, u.rol,
           m.tipo_metodo,
           (SELECT COUNT(*) FROM alquileres a WHERE a.id_cliente = c.id_cliente) as total_viajes
    FROM clientes c
    LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
    LEFT JOIN metodos_pago m ON c.id_metodo_pago_preferido = m.id_metodo_pago
    ORDER BY u.rol ASC, c.id_cliente DESC
";
$clientes = $pdo->query($query_clientes)->fetchAll(PDO::FETCH_ASSOC);

// Métricas
$total_usuarios = count($clientes);
$admin_count = 0; $clientes_activos = 0; $clientes_suspendidos = 0;
foreach($clientes as $c) {
    if ($c['rol'] === 'admin') $admin_count++;
    if ($c['activo'] === 1 || $c['activo'] === null) $clientes_activos++;
    if ($c['activo'] === 0) $clientes_suspendidos++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios y Clientes - Admin</title>
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
            <a href="flota.php" class="flex items-center gap-3 py-3 px-4 hover:bg-white/5 rounded-lg font-heading tracking-wider uppercase text-xs transition-all text-white/70 hover:text-white">Flota y Vehículos</a>
            <a href="clientes.php" class="flex items-center gap-3 py-3 px-4 bg-brandBlue-900/40 rounded-lg font-heading tracking-wider uppercase text-xs border border-brandBlue-900/50 text-white">Usuarios y Clientes</a>
        </nav>
        <div class="p-6 border-t border-white/10 space-y-4">
            <a href="../Clientes/index.php" class="block text-center bg-brandMain/10 hover:bg-brandMain/20 text-white font-heading tracking-widest text-[10px] py-3 rounded uppercase border border-white/5">Ir a Vista Cliente</a>
            <a href="../logout.php" class="block text-center text-red-400 font-heading tracking-widest text-[10px] uppercase hover:underline">Cerrar Sesión Segura</a>
        </div>
    </aside>

    <main class="ml-72 flex-1 p-10">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h2 class="font-heading text-4xl text-brandDark uppercase tracking-tight">Directorio de Usuarios</h2>
                <div class="h-1 w-20 bg-brandBlue-900 mt-2"></div>
            </div>
            <button onclick="document.getElementById('modalAddClient').classList.remove('hidden')" class="bg-brandBlue-900 text-white px-6 py-3 rounded-lg font-bold shadow hover:bg-brandDark transition-colors flex items-center gap-2">
                <span>➕</span> Registrar Nuevo Usuario
            </button>
        </header>

        <?php if($success): ?><div class="bg-green-100 border border-green-300 text-green-800 p-4 mb-8 rounded-lg font-bold shadow-sm"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 border border-red-300 text-red-800 p-4 mb-8 rounded-lg font-bold shadow-sm"><?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-brandBlue-900 tracking-widest mb-2 font-heading">Total Registrados</p>
                <p class="text-3xl font-heading text-brandDark"><?php echo $total_usuarios; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm border-l-4 border-l-purple-500">
                <p class="text-[10px] uppercase font-bold text-purple-600 tracking-widest mb-2 font-heading">Administradores</p>
                <p class="text-3xl font-heading text-purple-700"><?php echo $admin_count; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm border-l-4 border-l-green-500">
                <p class="text-[10px] uppercase font-bold text-green-600 tracking-widest mb-2 font-heading">Cuentas Activas</p>
                <p class="text-3xl font-heading text-green-700"><?php echo $clientes_activos; ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-brandMain/20 shadow-sm border-l-4 border-l-red-500">
                <p class="text-[10px] uppercase font-bold text-red-600 tracking-widest mb-2 font-heading">Suspendidas</p>
                <p class="text-3xl font-heading text-red-700"><?php echo $clientes_suspendidos; ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-brandMain/20 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] uppercase tracking-widest font-extrabold text-brandBlue-900 bg-gray-50/50 border-b border-brandMain/10">
                        <th class="p-5">Perfil y Rol</th>
                        <th class="p-5">Documentación</th>
                        <th class="p-5 text-center">Viajes</th>
                        <th class="p-5 text-center">Estado Acceso</th>
                        <th class="p-5 text-right">Acciones Administrativas</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php foreach($clientes as $c): ?>
                    <tr class="border-b border-brandMain/5 hover:bg-brandBlue-50/30 transition-colors <?php echo ($c['activo'] === 0) ? 'bg-red-50/20' : ''; ?>">
                        
                        <td class="p-5">
                            <div class="flex items-center gap-3">
                                <div>
                                    <p class="font-extrabold text-brandDark uppercase tracking-tight flex items-center gap-2">
                                        <?php echo htmlspecialchars($c['nombre'] . ' ' . $c['apellido']); ?>
                                        <?php if($c['id_usuario'] == $admin_en_sesion): ?>
                                            <span class="text-[9px] bg-brandDark text-white px-1.5 py-0.5 rounded uppercase tracking-widest">(Tú)</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-brandBlue-900 font-bold"><?php echo htmlspecialchars($c['email']); ?></p>
                                    
                                    <div class="mt-2">
                                        <?php if($c['rol'] == 'admin'): ?>
                                            <span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest border border-purple-200">👑 Administrador</span>
                                        <?php else: ?>
                                            <span class="bg-blue-50 text-blue-800 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest border border-blue-200">👤 Cliente</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="p-5">
                            
                            <p class="text-[10px] text-gray-500 font-bold mt-1">📞 <?php echo htmlspecialchars($c['telefono'] ?? 'N/A'); ?></p>
                            <p class="text-[10px] text-gray-500 mt-1 line-clamp-1 max-w-[200px]" title="<?php echo htmlspecialchars($c['direccion']); ?>">
                                📍 <?php echo htmlspecialchars($c['direccion'] ?? 'Sin dirección'); ?>
                            </p>
                        </td>

                        <td class="p-5 text-center">
                            <span class="font-heading text-xl text-brandBlue-900"><?php echo $c['total_viajes']; ?></span>
                        </td>

                        <td class="p-5 text-center">
                            <?php if($c['id_usuario']): ?>
                                <?php if($c['activo'] == 1): ?>
                                    <span class="text-green-600 text-xs font-bold uppercase tracking-widest">✅ Activo</span>
                                <?php else: ?>
                                    <span class="text-red-600 text-xs font-bold uppercase tracking-widest">❌ Suspendido</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs font-bold uppercase tracking-widest">Sin Cuenta</span>
                            <?php endif; ?>
                        </td>

                        <td class="p-5 text-right">
                            <div class="flex flex-col gap-2 items-end">
                                <button onclick='abrirModalEditar(<?php echo json_encode($c); ?>)' class="w-32 bg-white border border-brandMain/30 text-brandDark px-2 py-1.5 rounded text-[10px] font-extrabold uppercase tracking-widest hover:bg-brandBlue-50 transition-all shadow-sm">
                                    Editar / Rol
                                </button>
                                
                                <div class="flex gap-2 w-32">
                                    <?php if($c['id_usuario'] && $c['id_usuario'] != $admin_en_sesion): ?>
                                        <form method="POST" action="" class="w-1/2">
                                            <input type="hidden" name="id_usuario" value="<?php echo $c['id_usuario']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $c['activo'] == 1 ? 0 : 1; ?>">
                                            <button type="submit" name="toggle_estado" onclick="return confirm('¿Seguro que deseas cambiar el acceso de este usuario?');" class="w-full <?php echo $c['activo'] == 1 ? 'bg-orange-50 border-orange-200 text-orange-600 hover:bg-orange-600' : 'bg-green-50 border-green-200 text-green-700 hover:bg-green-600'; ?> border px-1 py-1.5 rounded text-[9px] font-extrabold uppercase tracking-widest hover:text-white transition-all shadow-sm">
                                                <?php echo $c['activo'] == 1 ? 'Ban' : 'Unban'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="w-1/2"></div>
                                    <?php endif; ?>

                                    <?php if($c['id_usuario'] != $admin_en_sesion): ?>
                                        <form method="POST" action="" class="w-1/2" onsubmit="return confirm('ATENCIÓN: ¿Seguro que deseas eliminar a este usuario permanentemente? Esta acción es irreversible y fallará si el usuario tiene facturas a su nombre.');">
                                            <input type="hidden" name="id_cliente" value="<?php echo $c['id_cliente']; ?>">
                                            <input type="hidden" name="id_usuario" value="<?php echo $c['id_usuario']; ?>">
                                            <button type="submit" name="eliminar_cliente" class="w-full bg-red-50 border border-red-200 text-red-600 px-1 py-1.5 rounded text-[9px] font-extrabold uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all shadow-sm">
                                                Borrar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalAddClient" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-brandDark p-6 flex justify-between items-center">
                <h3 class="font-heading text-2xl text-white">Registrar Nuevo Usuario</h3>
                <button onclick="document.getElementById('modalAddClient').classList.add('hidden')" class="text-white hover:text-red-400 text-2xl font-bold">&times;</button>
            </div>
            
            <form method="POST" action="" class="p-8 max-h-[80vh] overflow-y-auto">
                <h4 class="font-bold text-brandBlue-900 uppercase tracking-widest text-xs mb-4 border-b border-brandMain/20 pb-2">1. Credenciales y Permisos</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Correo Electrónico</label><input type="email" name="email" required placeholder="correo@ejemplo.com" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:border-brandBlue-900 bg-gray-50 font-bold"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Contraseña Inicial</label><input type="password" name="password" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:border-brandBlue-900 bg-gray-50"></div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Rol del Sistema</label>
                        <select name="rol" required class="w-full border border-brandMain/30 py-2 px-3 rounded bg-white font-bold text-brandBlue-900">
                            <option value="cliente">👤 Cliente Estándar</option>
                            <option value="admin">👑 Administrador (Acceso Total)</option>
                        </select>
                    </div>
                </div>

                <h4 class="font-bold text-brandBlue-900 uppercase tracking-widest text-xs mb-4 border-b border-brandMain/20 pb-2">2. Datos Personales</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Nombres</label><input type="text" name="nombre" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Apellidos</label><input type="text" name="apellido" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Identidad</label>
                        <select name="identificacion" required class="w-full border border-brandMain/30 py-2 px-3 rounded bg-white">
                            <option value="CI">Cédula (CI)</option><option value="Pasaporte">Pasaporte</option><option value="RIF">RIF</option>
                        </select>
                    </div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Teléfono</label><input type="text" name="telefono" class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Licencia</label><input type="text" name="licencia_conducir" class="w-full border border-brandMain/30 py-2 px-3 rounded bg-blue-50"></div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Dirección de Residencia</label>
                    <textarea name="direccion" rows="2" class="w-full border border-brandMain/30 py-2 px-3 rounded text-sm"></textarea>
                </div>

                <div class="mb-8">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Método de Pago Preferido (Opcional para Admins)</label>
                    <select name="id_metodo_pago_preferido" class="w-full border border-brandMain/30 py-2 px-3 rounded bg-white font-bold">
                        <option value="">Seleccione...</option>
                        <?php foreach($metodos_pago as $mp): ?>
                            <option value="<?php echo $mp['id_metodo_pago']; ?>"><?php echo htmlspecialchars($mp['tipo_metodo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="agregar_cliente" class="w-full bg-brandBlue-900 hover:bg-brandDark text-white font-bold py-4 rounded-lg font-heading tracking-widest uppercase text-sm shadow-md transition-colors">
                    Crear Cuenta de Usuario
                </button>
            </form>
        </div>
    </div>

    <div id="modalEditClient" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden border-t-4 border-brandBlue-500">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="font-heading text-2xl text-brandDark">Editar Usuario</h3>
                    <p id="edit_correo_cliente" class="text-sm font-bold text-brandBlue-900 mt-1"></p>
                </div>
                <button onclick="document.getElementById('modalEditClient').classList.add('hidden')" class="text-gray-400 hover:text-brandDark text-3xl font-bold">&times;</button>
            </div>
            
            <form method="POST" action="" class="p-8 max-h-[70vh] overflow-y-auto">
                <input type="hidden" name="id_cliente" id="edit_id_cliente">
                <input type="hidden" name="id_usuario" id="edit_id_usuario">
                
                <div class="mb-6 bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <label class="block text-[10px] font-bold uppercase text-purple-900 mb-2">Nivel de Acceso (Rol)</label>
                    <select name="rol" id="edit_rol" required class="w-full border border-purple-300 py-2 px-3 rounded bg-white font-bold text-purple-900">
                        <option value="cliente">👤 Cliente Estándar</option>
                        <option value="admin">👑 Administrador (Acceso Total)</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Nombres</label><input type="text" name="nombre" id="edit_nombre" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Apellidos</label><input type="text" name="apellido" id="edit_apellido" required class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Identidad</label>
                        <select name="identificacion" id="edit_identificacion" required class="w-full border border-brandMain/30 py-2 px-3 rounded bg-white">
                            <option value="CI">Cédula (CI)</option><option value="Pasaporte">Pasaporte</option><option value="RIF">RIF</option>
                        </select>
                    </div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Teléfono</label><input type="text" name="telefono" id="edit_telefono" class="w-full border border-brandMain/30 py-2 px-3 rounded"></div>
                    <div><label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Licencia</label><input type="text" name="licencia_conducir" id="edit_licencia" class="w-full border border-brandMain/30 py-2 px-3 rounded bg-blue-50"></div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Dirección</label>
                    <textarea name="direccion" id="edit_direccion" rows="2" class="w-full border border-brandMain/30 py-2 px-3 rounded text-sm"></textarea>
                </div>

                <div class="mb-8">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/60 mb-2">Método de Pago Preferido</label>
                    <select name="id_metodo_pago_preferido" id="edit_metodo_pago" class="w-full border border-brandMain/30 py-2 px-3 rounded bg-white font-bold">
                        <option value="">Ninguno</option>
                        <?php foreach($metodos_pago as $mp): ?>
                            <option value="<?php echo $mp['id_metodo_pago']; ?>"><?php echo htmlspecialchars($mp['tipo_metodo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="editar_cliente" class="w-full bg-brandDark hover:bg-brandBlue-900 text-white font-bold py-4 rounded-lg font-heading tracking-widest uppercase text-sm shadow-md transition-colors">
                    Actualizar Usuario
                </button>
            </form>
        </div>
    </div>

    <script>
        function abrirModalEditar(cliente) {
            document.getElementById('edit_id_cliente').value = cliente.id_cliente;
            document.getElementById('edit_id_usuario').value = cliente.id_usuario || '';
            document.getElementById('edit_correo_cliente').innerText = cliente.email;
            document.getElementById('edit_rol').value = cliente.rol || 'cliente';
            
            document.getElementById('edit_nombre').value = cliente.nombre;
            document.getElementById('edit_apellido').value = cliente.apellido;
            document.getElementById('edit_identificacion').value = cliente.identificacion;
            document.getElementById('edit_telefono').value = cliente.telefono || '';
            document.getElementById('edit_licencia').value = cliente.licencia_conducir || '';
            document.getElementById('edit_direccion').value = cliente.direccion || '';
            
            if(cliente.id_metodo_pago_preferido) {
                document.getElementById('edit_metodo_pago').value = cliente.id_metodo_pago_preferido;
            } else {
                document.getElementById('edit_metodo_pago').value = "";
            }
            
            document.getElementById('modalEditClient').classList.remove('hidden');
        }
    </script>
</body>
</html>