<?php
session_start();
require_once '../conexion.php';

// Establecer zona horaria a Venezuela
date_default_timezone_set('America/Caracas');

// Inicializar fechas de búsqueda (por defecto: hoy hasta dentro de 3 días)
$fecha_inicio_busqueda = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin_busqueda = $_GET['fecha_fin'] ?? date('Y-m-d', strtotime('+3 days'));
$ubicacion = $_GET['ubicacion'] ?? 'aeropuerto';

try {
    // Consulta inteligente de disponibilidad:
    // Trae los vehículos que no están en mantenimiento y que NO tienen un alquiler activo/reservado 
    // cuyas fechas se solapen con el rango de búsqueda.
    $query = "
        SELECT v.*, c.nombre_categoria, 
               (SELECT MIN(precio_dia) FROM tarifas t WHERE t.id_categoria = v.id_categoria) as precio_base
        FROM vehiculos v
        LEFT JOIN categorias c ON v.id_categoria = c.id_categoria
        WHERE v.estado != 'Mantenimiento'
        AND v.id_vehiculo NOT IN (
            SELECT id_vehiculo 
            FROM alquileres 
            WHERE estado_alquiler IN ('Activo', 'Reservado')
            AND DATE(fecha_salida) <= :fecha_fin
            AND DATE(fecha_retorno_prevista) >= :fecha_inicio
        )
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':fecha_inicio' => $fecha_inicio_busqueda,
        ':fecha_fin' => $fecha_fin_busqueda
    ]);
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar el catálogo: " . $e->getMessage());
}

$is_logged_in = isset($_SESSION['usuario_id']);
$nombre_usuario = $_SESSION['cliente_nombre'] ?? 'Invitado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - LHFM Logistics</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Righteous&display=swap');
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandMain: '#ABBBC0',
                        brandDark: '#2F2A24',
                        brandBlue: { 500: '#3b82f6', 900: '#1e3a8a' }
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
<body class="bg-gray-50 text-brandDark font-sans">

    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex-shrink-0 flex items-center">
                    <span class="font-brand text-3xl text-brandDark tracking-wide">LHFM <span class="text-brandMain">LOGISTICS</span></span>
                </div>
                
                <div class="hidden md:flex space-x-8">
                    <a href="#" class="text-brandBlue-900 font-semibold hover:text-brandDark transition-colors">Inicio</a>
                    <a href="#flota" class="text-brandDark/70 hover:text-brandDark font-semibold transition-colors">Nuestra Flota</a>
                </div>

                <div class="flex items-center">
                    <?php if($is_logged_in): ?>
                        <div class="flex items-center gap-4">
                            <span class="font-medium text-sm">Hola, <?php echo htmlspecialchars($nombre_usuario); ?></span>
                            <a href="logout.php" class="text-sm text-red-500 font-semibold hover:underline">Salir</a>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="bg-brandBlue-900 text-white px-5 py-2.5 rounded shadow hover:bg-brandDark transition-colors font-semibold text-sm">
                            Iniciar Sesión
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="relative bg-brandDark py-24 sm:py-32 overflow-hidden">
        <div class="absolute inset-0 bg-brandMain/10"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="font-heading text-4xl sm:text-5xl lg:text-6xl text-white mb-6">Tu viaje por Margarita empieza aquí</h1>
            <p class="text-brandMain text-lg sm:text-xl max-w-2xl mx-auto mb-12">Selecciona tus fechas para ver los vehículos disponibles.</p>
            
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-4xl mx-auto text-left transform translate-y-8 border-t-4 border-brandBlue-900">
                <form action="index.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Recogida</label>
                        <select name="ubicacion" class="w-full border-b-2 border-brandMain/30 py-2 focus:outline-none focus:border-brandBlue-900 bg-transparent font-medium">
                            <option value="aeropuerto" <?php echo $ubicacion == 'aeropuerto' ? 'selected' : ''; ?>>Aeropuerto Santiago Mariño</option>
                            <option value="pampatar" <?php echo $ubicacion == 'pampatar' ? 'selected' : ''; ?>>Pampatar</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio_busqueda; ?>" required min="<?php echo date('Y-m-d'); ?>" class="w-full border-b-2 border-brandMain/30 py-2 focus:outline-none focus:border-brandBlue-900 bg-transparent font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Fecha Entrega</label>
                        <input type="date" name="fecha_fin" value="<?php echo $fecha_fin_busqueda; ?>" required min="<?php echo date('Y-m-d'); ?>" class="w-full border-b-2 border-brandMain/30 py-2 focus:outline-none focus:border-brandBlue-900 bg-transparent font-medium">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-brandBlue-900 text-white font-bold py-3 rounded shadow hover:bg-brandDark transition-all">
                            BUSCAR DISPONIBILIDAD
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="flota" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 mt-10">
        <div class="mb-10 flex justify-between items-end border-b border-brandMain/30 pb-4">
            <div>
                <h2 class="font-heading text-3xl text-brandDark">Vehículos Disponibles</h2>
                <p class="text-brandDark/70 text-sm mt-1">Mostrando disponibilidad del <strong><?php echo date('d/m/Y', strtotime($fecha_inicio_busqueda)); ?></strong> al <strong><?php echo date('d/m/Y', strtotime($fecha_fin_busqueda)); ?></strong></p>
            </div>
            <span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full"><?php echo count($vehiculos); ?> encontrados</span>
        </div>

        <?php if(empty($vehiculos)): ?>
            <div class="bg-red-50 text-red-700 p-8 rounded-lg text-center font-bold">
                Lo sentimos, no hay vehículos disponibles para las fechas seleccionadas. Por favor, intenta con otro rango de fechas.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($vehiculos as $v): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-brandMain/20 overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col group">
                        
                        <div class="bg-brandMain/10 h-48 flex items-center justify-center p-4 relative">
                            <?php if($v['url_imagen']): ?>
                                <img src="<?php echo htmlspecialchars($v['url_imagen']); ?>" class="max-h-full object-contain group-hover:scale-105 transition-transform duration-300">
                            <?php else: ?>
                                <div class="text-brandMain/50 font-bold text-xl">Sin Imagen</div>
                            <?php endif; ?>
                        </div>

                        <div class="p-6 flex-grow flex flex-col">
                            <div class="uppercase text-xs font-bold text-brandBlue-900 mb-1"><?php echo htmlspecialchars($v['nombre_categoria']); ?></div>
                            <h3 class="font-heading text-xl text-brandDark mb-4"><?php echo htmlspecialchars($v['marca'] . ' ' . $v['modelo']); ?></h3>
                            
                            <div class="grid grid-cols-2 gap-4 mb-6 text-sm text-brandDark/70 flex-grow">
                                <div class="flex items-center gap-2">👨‍👩‍👧‍👦 <?php echo htmlspecialchars($v['capacidad_pasajeros']); ?> Pasajeros</div>
                                <div class="flex items-center gap-2">❄️ A/C Incluido</div>
                            </div>

                            <div class="flex items-end justify-between mt-auto border-t border-brandMain/20 pt-4">
                                <div>
                                    <span class="block text-xs text-brandDark/60 uppercase">Tarifa Diaria</span>
                                    <span class="text-2xl font-bold text-brandBlue-900">$<?php echo number_format($v['precio_base'], 2); ?></span>
                                </div>
                                
                                <a href="reservar.php?id=<?php echo $v['id_vehiculo']; ?>&fecha_inicio=<?php echo $fecha_inicio_busqueda; ?>&fecha_fin=<?php echo $fecha_fin_busqueda; ?>" 
                                   class="bg-brandDark text-white px-5 py-2.5 rounded font-heading tracking-wider text-sm hover:bg-brandBlue-900 transition-colors shadow">
                                    Reservar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>