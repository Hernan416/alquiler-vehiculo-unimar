<?php
session_start();
require_once 'conexion.php'; 

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (!empty($email) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT id_usuario, password, rol, activo FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    if ($user['activo'] == 1) {
                        $stmt_cli = $pdo->prepare("SELECT id_cliente, nombre FROM clientes WHERE id_usuario = ?");
                        $stmt_cli->execute([$user['id_usuario']]);
                        $cliente = $stmt_cli->fetch(PDO::FETCH_ASSOC);

                        $_SESSION['usuario_id'] = $user['id_usuario'];
                        $_SESSION['rol'] = $user['rol'];
                        if ($cliente) {
                            $_SESSION['cliente_id'] = $cliente['id_cliente'];
                            $_SESSION['cliente_nombre'] = $cliente['nombre'];
                        }
                        
                        header("Location: ./Clientes/index.php");
                        exit;
                    } else {
                        $error = "Tu cuenta está inactiva.";
                    }
                } else {
                    $error = "contraseña inválida";
                }
            } else {
                $error = "El correo electrónico no está registrado.";
            }
        } else {
            $error = "Por favor, completa todos los campos.";
        }
    } 
    
    elseif ($action === 'register') {
        // Capturamos TODOS los datos del perfil
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $identificacion = $_POST['identificacion'];
        $telefono = trim($_POST['telefono']);
        $licencia_conducir = trim($_POST['licencia_conducir']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (!empty($nombre) && !empty($apellido) && !empty($identificacion) && !empty($telefono) && !empty($licencia_conducir) && !empty($email) && !empty($password)) {
            try {
                $pdo->beginTransaction();
                
                // Verificar si el email ya existe
                $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception("El correo ya está registrado.");
                }

                // 1. Insertar en usuarios
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO usuarios (email, password, rol, activo) VALUES (?, ?, 'cliente', 1)");
                $stmt_user->execute([$email, $hashed_password]);
                $id_usuario = $pdo->lastInsertId();

                // 2. Insertar en clientes con TODOS los datos requeridos
                $stmt_cli = $pdo->prepare("INSERT INTO clientes (nombre, apellido, email, identificacion, id_usuario, telefono, licencia_conducir) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_cli->execute([$nombre, $apellido, $email, $identificacion, $id_usuario, $telefono, $licencia_conducir]);

                $pdo->commit();
                $success = "Perfil creado exitosamente. Ya puedes iniciar sesión.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        } else {
            $error = "Por favor, completa todos los campos del perfil.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - LHFM Logistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandMain: '#ABBBC0',
                        brandDark: '#2F2A24',
                        brandWhite: '#FFFFFF',
                        brandBlue: {
                            50: '#f0f4f8',
                            500: '#3b82f6',
                            900: '#1e3a8a',
                        }
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
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4 font-sans text-brandDark">

    <div class="bg-brandWhite rounded-lg shadow-2xl w-full max-w-md overflow-hidden border border-brandMain/20 transition-all">
        
        <div class="bg-brandDark p-8 text-center">
            <h1 class="font-brand text-4xl text-brandWhite tracking-wider">LHFM <span class="text-brandMain">LOGISTICS</span></h1>
            <p class="font-heading text-brandMain text-sm mt-2 uppercase tracking-widest">Margarita Island Rentals</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 text-sm font-bold uppercase tracking-tight text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 text-sm font-bold text-center">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div id="login-section" class="p-10 <?php echo ($action === 'register' && $error) ? 'hidden' : ''; ?>">
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                
                <div class="mb-6">
                    <label class="block text-xs font-bold uppercase text-brandDark/50 mb-2 font-heading">E-mail</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 rounded border border-brandMain/30 focus:outline-none focus:border-brandBlue-900 focus:ring-1 focus:ring-brandBlue-900 transition-all bg-brandWhite font-medium">
                </div>
                
                <div class="mb-8">
                    <label class="block text-xs font-bold uppercase text-brandDark/50 mb-2 font-heading">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded border border-brandMain/30 focus:outline-none focus:border-brandBlue-900 focus:ring-1 focus:ring-brandBlue-900 transition-all bg-brandWhite font-medium">
                </div>

                <button type="submit" class="w-full bg-brandBlue-900 hover:bg-brandDark text-brandWhite font-bold py-4 rounded transition-all shadow-lg hover:shadow-none font-heading tracking-widest">
                    ENTRAR
                </button>
            </form>

            <div class="text-center mt-10 border-t border-brandMain/10 pt-8">
                <p class="text-sm font-heading">¿No tienes cuenta? <button onclick="toggleForms()" class="text-brandBlue-900 font-bold hover:underline">CREAR PERFIL</button></p>
            </div>
        </div>

        <div id="register-section" class="p-10 <?php echo ($action === 'register' && $error) ? '' : 'hidden'; ?>">
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Nombre</label>
                        <input type="text" name="nombre" required class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm focus:border-brandBlue-900 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Apellido</label>
                        <input type="text" name="apellido" required class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm focus:border-brandBlue-900 outline-none">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Tipo de Documento</label>
                    <select name="identificacion" class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm bg-white outline-none">
                        <option value="CI">Cédula (CI)</option>
                        <option value="Pasaporte">Pasaporte</option>
                        <option value="RIF">RIF</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Teléfono</label>
                        <input type="text" name="telefono" placeholder="Ej. 0414-1234567" required class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm focus:border-brandBlue-900 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Nro. Licencia</label>
                        <input type="text" name="licencia_conducir" placeholder="Ej. V-12345678" required class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm focus:border-brandBlue-900 outline-none">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Correo Electrónico</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm focus:border-brandBlue-900 outline-none">
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-bold uppercase text-brandDark/50 mb-1 font-heading">Contraseña</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded border-brandMain/30 text-sm focus:border-brandBlue-900 outline-none">
                </div>

                <button type="submit" class="w-full bg-brandDark text-brandWhite font-bold py-3 rounded hover:bg-brandBlue-900 transition-all font-heading uppercase">
                    Completar Registro
                </button>
            </form>
            <div class="text-center mt-6">
                <button onclick="toggleForms()" class="text-xs font-bold text-brandBlue-900 underline uppercase hover:text-brandDark">Volver al login</button>
            </div>
        </div>
    </div>

    <script>
        function toggleForms() {
            document.getElementById('login-section').classList.toggle('hidden');
            document.getElementById('register-section').classList.toggle('hidden');
            
            // Limpiar alertas al cambiar de vista
            const alerts = document.querySelectorAll('.bg-red-50, .bg-green-50');
            alerts.forEach(alert => alert.style.display = 'none');
        }
    </script>
</body>
</html>