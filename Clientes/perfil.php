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

// Verificar si viene redireccionado desde reservar.php
$completar = $_GET['completar'] ?? 0;
$redirect_url = $_GET['redirect'] ?? '';

// Cargar métodos de pago para el select
$metodos_pago = $pdo->query("SELECT * FROM metodos_pago")->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $telefono = trim($_POST['telefono']);
    $licencia = trim($_POST['licencia_conducir']);
    $direccion = trim($_POST['direccion']);
    $id_metodo_pago = $_POST['id_metodo_pago_preferido'];

    if (!empty($telefono) && !empty($licencia) && !empty($direccion) && !empty($id_metodo_pago)) {
        try {
            $stmt_update = $pdo->prepare("
                UPDATE clientes 
                SET telefono = ?, licencia_conducir = ?, direccion = ?, id_metodo_pago_preferido = ? 
                WHERE id_cliente = ?
            ");
            $stmt_update->execute([$telefono, $licencia, $direccion, $id_metodo_pago, $id_cliente]);
            
            $success = "Perfil actualizado correctamente.";
            
            // Si venía de una reserva, devolverlo allá
            if (!empty($_POST['redirect_url'])) {
                header("Location: " . urldecode($_POST['redirect_url']));
                exit;
            }
        } catch (Exception $e) {
            $error = "Error al actualizar el perfil: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos requeridos.";
    }
}

// Obtener datos actuales del cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->execute([$id_cliente]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - LHFM Logistics</title>
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
<body class="bg-gray-50 text-brandDark font-sans min-h-screen">

    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 flex justify-between h-20 items-center">
            <a href="index.php" class="font-brand text-2xl text-brandDark">LHFM <span class="text-brandMain">LOGISTICS</span></a>
            <div class="flex items-center gap-4">
                <span class="font-medium text-sm">Mi Perfil</span>
                <a href="index.php" class="text-sm text-brandBlue-900 font-semibold hover:underline">Volver al inicio</a>
            </div>
        </div>
    </nav>

    <div class="max-w-3xl mx-auto px-4 py-10">
        <?php if($completar): ?>
            <div class="bg-orange-50 border-l-4 border-orange-500 text-orange-800 p-4 mb-6 rounded shadow-sm flex items-center gap-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <h3 class="font-bold font-heading">Acción Requerida</h3>
                    <p class="text-sm">Para poder reservar un vehículo, necesitamos que completes tu información de contacto y preferencias de pago.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if($success): ?><div class="bg-green-100 text-green-800 p-4 mb-6 rounded font-bold"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="bg-red-100 text-red-800 p-4 mb-6 rounded font-bold"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-brandMain/20 p-8">
            <h2 class="font-heading text-3xl mb-6 border-b border-brandMain/20 pb-4">Configuración de Cuenta</h2>
            
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirect_url); ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Licencia de Conducir</label>
                        <input type="text" name="licencia_conducir" value="<?php echo htmlspecialchars($cliente['licencia_conducir'] ?? ''); ?>" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Dirección de Residencia / Hotel en Margarita</label>
                    <textarea name="direccion" required rows="3" class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900"><?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?></textarea>
                </div>

                <div class="bg-gray-50 p-6 rounded-lg border border-brandMain/20">
                    <h3 class="font-heading text-xl mb-4">Información de Pago</h3>
                    <label class="block text-xs font-bold uppercase text-brandDark/60 mb-2">Método de Pago Preferido</label>
                    <select name="id_metodo_pago_preferido" required class="w-full border border-brandMain/30 py-2 px-3 rounded focus:outline-none focus:border-brandBlue-900 bg-white">
                        <option value="">Seleccione un método...</option>
                        <?php foreach($metodos_pago as $mp): ?>
                            <option value="<?php echo $mp['id_metodo_pago']; ?>" <?php echo ($cliente['id_metodo_pago_preferido'] == $mp['id_metodo_pago']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mp['tipo_metodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[10px] text-brandDark/50 mt-2">Seleccionar esto hará que tus futuras reservas sean mucho más rápidas.</p>
                </div>

                <button type="submit" class="w-full bg-brandDark text-white font-bold py-3 rounded-lg hover:bg-brandBlue-900 transition-all font-heading tracking-widest uppercase">
                    Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</body>
</html>