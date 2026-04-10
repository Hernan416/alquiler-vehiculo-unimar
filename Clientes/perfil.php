<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = $_SESSION['cliente_id'];
$success = '';
$error = '';

$completar = $_GET['completar'] ?? 0;
$redirect_url = $_GET['redirect'] ?? '';

$metodos_pago = $pdo->query("SELECT * FROM metodos_pago")->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización completa del perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Datos Personales
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $identificacion = $_POST['identificacion'];
    // Datos de Contacto y Sensibles
    $telefono = trim($_POST['telefono']);
    $licencia = trim($_POST['licencia_conducir']);
    $direccion = trim($_POST['direccion']);
    // Pago
    $id_metodo_pago = $_POST['id_metodo_pago_preferido'];

    if (!empty($nombre) && !empty($apellido) && !empty($telefono) && !empty($licencia) && !empty($direccion) && !empty($id_metodo_pago)) {
        try {
            $stmt_update = $pdo->prepare("
                UPDATE clientes 
                SET nombre = ?, apellido = ?, identificacion = ?, telefono = ?, licencia_conducir = ?, direccion = ?, id_metodo_pago_preferido = ? 
                WHERE id_cliente = ?
            ");
            $stmt_update->execute([$nombre, $apellido, $identificacion, $telefono, $licencia, $direccion, $id_metodo_pago, $id_cliente]);
            
            // Actualizar nombre en sesión por si lo cambió
            $_SESSION['cliente_nombre'] = $nombre;
            
            $success = "Tus datos han sido actualizados exitosamente.";
            
            if (!empty($_POST['redirect_url'])) {
                header("Location: " . urldecode($_POST['redirect_url']));
                exit;
            }
        } catch (Exception $e) {
            $error = "Error al actualizar el perfil: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, no dejes campos obligatorios en blanco.";
    }
}

// Obtener datos actuales del cliente para pre-llenar los formularios
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->execute([$id_cliente]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Panel - LHFM Logistics</title>
    <style>@import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { brandMain: '#ABBBC0', brandDark: '#2F2A24', brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' } },
                fontFamily: { sans: ['"Google Sans"', 'sans-serif'], heading: ['Fredoka', 'sans-serif'], brand: ['Righteous', 'cursive'] } }
            }
        }
    </script>
</head>
<body class="bg-gray-100 text-brandDark font-sans min-h-screen">

    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 flex justify-between h-20 items-center">
            <a href="index.php" class="font-brand text-2xl text-brandDark">LHFM <span class="text-brandMain">LOGISTICS</span></a>
            <div class="flex items-center gap-6">
                <span class="font-bold text-brandBlue-900 border-b-2 border-brandBlue-900 pb-1">Mi Panel</span>
                <a href="index.php" class="text-sm font-semibold text-brandDark/70 hover:text-brandDark transition-colors">Volver al Inicio</a>
                <a href="login.php" class="text-xs text-red-500 font-bold uppercase tracking-widest hover:underline bg-red-50 px-3 py-1.5 rounded">Salir</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-10">
        
        <?php if($completar): ?>
            <div class="bg-orange-50 border-l-4 border-orange-500 text-orange-800 p-4 mb-8 rounded shadow-sm flex items-start gap-4 animate-pulse">
                <span class="text-3xl">⚠️</span>
                <div>
                    <h3 class="font-bold font-heading text-lg">Información Requerida</h3>
                    <p class="text-sm">Para confirmar tu reserva de forma segura, necesitamos que verifiques y completes tus datos sensibles (Licencia, Teléfono, Dirección) y método de pago.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if($success): ?><div class="bg-green-100 border border-green-300 text-green-800 p-4 mb-6 rounded-lg font-bold shadow-sm"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 border border-red-300 text-red-800 p-4 mb-6 rounded-lg font-bold shadow-sm"><?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="space-y-6">
                
                <div class="bg-white rounded-xl shadow-sm border border-brandMain/20 p-6 text-center">
                    <div class="w-20 h-20 bg-brandBlue-900 text-white rounded-full flex items-center justify-center text-4xl mx-auto mb-4 font-heading shadow-lg">
                        <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                    </div>
                    <h2 class="font-heading text-2xl text-brandDark"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></h2>
                    <p class="text-brandDark/60 text-sm"><?php echo htmlspecialchars($cliente['email']); ?></p>
                    <span class="inline-block mt-3 bg-green-100 text-green-800 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest">Cliente Activo</span>
                </div>

                <div class="bg-brandDark rounded-xl shadow-lg border border-brandMain/20 overflow-hidden relative group">
                    <div class="absolute inset-0 bg-brandMain/5 opacity-50"></div>
                    <div class="p-6 relative z-10 text-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-heading text-2xl text-brandMain">Mis Reservas</h3>
                            <span class="text-3xl">🚗</span>
                        </div>
                        <p class="text-sm text-brandMain/80 mb-6">Gestiona tus alquileres actuales, visualiza el historial de tus viajes y descarga tus facturas.</p>
                        
                        <a href="mis_reservas.php" class="block w-full bg-brandBlue-500 hover:bg-brandBlue-900 text-white text-center font-bold py-3 rounded transition-all font-heading tracking-widest uppercase text-sm shadow">
                            ENTRAR A MIS RESERVAS
                        </a>
                    </div>
                </div>

                <div class="bg-brandBlue-50 rounded-xl p-6 border border-brandBlue-100">
                    <h4 class="font-bold text-brandBlue-900 text-sm uppercase tracking-widest mb-2">¿Necesitas ayuda?</h4>
                    <p class="text-xs text-brandDark/70 mb-3">Si deseas actualizar tu correo electrónico o tienes problemas con tus reservas, contacta a soporte.</p>
                    <a href="#" class="text-sm font-bold text-brandBlue-900 hover:underline">Soporte Técnico &rarr;</a>
                </div>

            </div>

            <div class="lg:col-span-2">
                <form method="POST" action="" class="bg-white rounded-xl shadow-sm border border-brandMain/20 p-8">
                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirect_url); ?>">
                    
                    <div class="mb-8 border-b border-brandMain/20 pb-4">
                        <h2 class="font-heading text-3xl text-brandDark">Configuración de Perfil</h2>
                        <p class="text-sm text-brandDark/60">Asegúrate de que tus datos coincidan con tus documentos de identidad.</p>
                    </div>

                    <h3 class="font-bold text-brandBlue-900 uppercase tracking-widest text-xs mb-4">1. Identidad</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Nombres</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900 bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Apellidos</label>
                            <input type="text" name="apellido" value="<?php echo htmlspecialchars($cliente['apellido']); ?>" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900 bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Tipo de Documento</label>
                            <select name="identificacion" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900 bg-white">
                                <option value="CI" <?php echo ($cliente['identificacion'] == 'CI') ? 'selected' : ''; ?>>Cédula (CI)</option>
                                <option value="Pasaporte" <?php echo ($cliente['identificacion'] == 'Pasaporte') ? 'selected' : ''; ?>>Pasaporte</option>
                                <option value="RIF" <?php echo ($cliente['identificacion'] == 'RIF') ? 'selected' : ''; ?>>RIF</option>
                            </select>
                        </div>
                    </div>

                    <h3 class="font-bold text-brandBlue-900 uppercase tracking-widest text-xs mb-4">2. Contacto y Conducción</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Teléfono Móvil <span class="text-red-500">*</span></label>
                            <input type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>" placeholder="Ej. 0414-1234567" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900 font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Nro. Licencia de Conducir <span class="text-red-500">*</span></label>
                            <input type="text" name="licencia_conducir" value="<?php echo htmlspecialchars($cliente['licencia_conducir'] ?? ''); ?>" placeholder="Ej. V-12345678" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:ring-2 focus:ring-brandBlue-500 bg-blue-50 font-bold tracking-widest">
                        </div>
                    </div>
                    <div class="mb-8">
                        <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Dirección de Residencia / Hotel en Margarita <span class="text-red-500">*</span></label>
                        <textarea name="direccion" required rows="2" placeholder="Indica exactamente dónde te alojarás..." class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900"><?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?></textarea>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-lg border border-brandMain/20 mb-8">
                        <h3 class="font-bold text-brandBlue-900 uppercase tracking-widest text-xs mb-4 flex items-center gap-2">
                            <span>💳</span> 3. Billetera y Pagos
                        </h3>
                        <p class="text-sm text-brandDark/70 mb-4">Selecciona cómo prefieres pagar tus alquileres. Esto agilizará tus futuras reservas.</p>
                        
                        <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Método de Pago Predeterminado <span class="text-red-500">*</span></label>
                        <select name="id_metodo_pago_preferido" required class="w-full border border-brandMain/30 py-3 px-3 rounded focus:outline-none focus:border-brandBlue-900 bg-white font-bold">
                            <option value="">Seleccione un método...</option>
                            <?php foreach($metodos_pago as $mp): ?>
                                <option value="<?php echo $mp['id_metodo_pago']; ?>" <?php echo ($cliente['id_metodo_pago_preferido'] == $mp['id_metodo_pago']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mp['tipo_metodo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-brandMain/20">
                        <button type="submit" class="bg-brandDark text-white font-bold py-3 px-8 rounded-lg hover:bg-brandBlue-900 transition-all font-heading tracking-widest uppercase shadow-lg">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>          
        </div>
    </div>

<?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
    <div class="mt-8"> <a href="../admin/index.php" 
           class="ml-6 mb-8 inline-flex items-center justify-center px-6 py-3 bg-brandDark text-white font-bold rounded-lg shadow-lg hover:bg-brandBlue-900 transition-all gap-2 group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brandMain group-hover:rotate-90 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            PANEL ADMINISTRATIVO
        </a>
    </div>
<?php endif; ?>


</body>
</html>