<?php
session_start();
require_once '../conexion.php';

// Obtener vehículos, categorías y el precio mínimo (desde la tabla tarifas) para mostrar "Desde $X"
try {
    $query = "
        SELECT v.*, c.nombre_categoria, 
               (SELECT MIN(precio_dia) FROM tarifas t WHERE t.id_categoria = v.id_categoria) as precio_base
        FROM vehiculos v
        LEFT JOIN categorias c ON v.id_categoria = c.id_categoria
    ";
    $stmt = $pdo->query($query);
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
                        brandBlue: {
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
                    <a href="#" class="text-brandDark/70 hover:text-brandDark font-semibold transition-colors">Requisitos</a>
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
            <p class="text-brandMain text-lg sm:text-xl max-w-2xl mx-auto mb-12">Alquila el vehículo perfecto para tu estadía con total seguridad y las mejores tarifas del mercado.</p>
            
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-4xl mx-auto text-left transform translate-y-8">
                <form action="reservar.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Recogida</label>
                        <select name="ubicacion" class="w-full border-b-2 border-brandMain/30 py-2 focus:outline-none focus:border-brandBlue-900 bg-transparent font-medium">
                            <option value="aeropuerto">Aeropuerto Santiago Mariño</option>
                            <option value="pampatar">Pampatar</option>
                            <option value="porlamar">Porlamar</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" required class="w-full border-b-2 border-brandMain/30 py-2 focus:outline-none focus:border-brandBlue-900 bg-transparent font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-brandDark/60 uppercase mb-2">Fecha Entrega</label>
                        <input type="date" name="fecha_fin" required class="w-full border-b-2 border-brandMain/30 py-2 focus:outline-none focus:border-brandBlue-900 bg-transparent font-medium">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-brandBlue-900 text-white font-bold py-3 rounded shadow hover:bg-brandDark transition-all">
                            BUSCAR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="flota" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 mt-10">
        <div class="text-center mb-16">
            <h2 class="font-heading text-3xl md:text-4xl text-brandDark mb-4">Vehículos Disponibles</h2>
            <p class="text-brandDark/70">Selecciona el auto que mejor se adapte a tus necesidades.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($vehiculos as $v): ?>
                <div class="bg-white rounded-xl shadow-sm border border-brandMain/20 overflow-hidden hover:shadow-lg transition-shadow duration-300 group flex flex-col">
                    
                    <div class="bg-brandMain/10 h-48 flex items-center justify-center p-4 relative">
                        <?php if($v['estado'] === 'Alquilado'): ?>
                            <span class="absolute top-4 right-4 bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded">Alquilado</span>
                        <?php elseif($v['estado'] === 'Mantenimiento'): ?>
                            <span class="absolute top-4 right-4 bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded">Taller</span>
                        <?php else: ?>
                            <span class="absolute top-4 right-4 bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded">Disponible</span>
                        <?php endif; ?>
                        
                        <?php if($v['url_imagen']): ?>
                            <img src="<?php echo htmlspecialchars($v['url_imagen']); ?>" alt="<?php echo htmlspecialchars($v['marca'] . ' ' . $v['modelo']); ?>" class="max-h-full object-contain group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="text-brandMain/50 font-bold text-xl">Sin Imagen</div>
                        <?php endif; ?>
                    </div>

                    <div class="p-6 flex-grow flex flex-col">
                        <div class="uppercase text-xs font-bold text-brandBlue-900 mb-1"><?php echo htmlspecialchars($v['nombre_categoria']); ?></div>
                        <h3 class="font-heading text-xl text-brandDark mb-4"><?php echo htmlspecialchars($v['marca'] . ' ' . $v['modelo']); ?> <span class="text-sm font-sans text-brandMain font-normal">(<?php echo htmlspecialchars($v['anio']); ?>)</span></h3>
                        
                        <div class="grid grid-cols-2 gap-4 mb-6 text-sm text-brandDark/70 flex-grow">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                <?php echo htmlspecialchars($v['capacidad_pasajeros']); ?> Pasajeros
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                A/C
                            </div>
                        </div>

                        <div class="flex items-end justify-between mt-auto border-t border-brandMain/20 pt-4">
                            <div>
                                <span class="block text-xs text-brandDark/60 uppercase">Desde</span>
                                <span class="text-2xl font-bold text-brandBlue-900">$<?php echo number_format($v['precio_base'], 2); ?></span><span class="text-sm text-brandDark/60">/día</span>
                            </div>
                            
                            <?php if($v['estado'] === 'Disponible'): ?>
                                <a href="detalle.php?id=<?php echo $v['id_vehiculo']; ?>" class="bg-brandDark text-white px-4 py-2 rounded text-sm font-semibold hover:bg-brandBlue-900 transition-colors">
                                    Reservar
                                </a>
                            <?php else: ?>
                                <button disabled class="bg-gray-200 text-gray-500 px-4 py-2 rounded text-sm font-semibold cursor-not-allowed">
                                    No Disponible
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>