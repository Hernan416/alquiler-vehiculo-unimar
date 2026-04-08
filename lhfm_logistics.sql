-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-04-2026 a las 04:30:15
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `lhfm_logistics`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_finalizar_y_calcular_alquiler` (IN `p_id_alquiler` INT, IN `p_fecha_retorno_real` DATETIME)   BEGIN
    DECLARE v_id_vehiculo INT;
    DECLARE v_id_categoria INT;
    DECLARE v_fecha_salida DATETIME;
    DECLARE v_precio_dia DECIMAL(10,2);
    DECLARE v_horas_totales INT;
    DECLARE v_dias_a_cobrar INT;
    DECLARE v_monto_base DECIMAL(10,2);
    DECLARE v_monto_extras DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_final DECIMAL(10,2);

    -- 1. Obtener datos básicos del alquiler y vehículo
    SELECT a.id_vehiculo, a.fecha_salida, v.id_categoria 
    INTO v_id_vehiculo, v_fecha_salida, v_id_categoria
    FROM alquileres a
    JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
    WHERE a.id_alquiler = p_id_alquiler;

    -- 2. Calcular diferencia en horas
    SET v_horas_totales = TIMESTAMPDIFF(HOUR, v_fecha_salida, p_fecha_retorno_real);

    -- 3. Lógica de Tiempo de Gracia (2 horas)
    -- Si el residuo de horas / 24 es mayor a 2, se cobra un día completo extra
    SET v_dias_a_cobrar = FLOOR(v_horas_totales / 24);
    IF (v_horas_totales % 24) > 2 THEN
        SET v_dias_a_cobrar = v_dias_a_cobrar + 1;
    END IF;

    -- Asegurar al menos 1 día de cobro
    IF v_dias_a_cobrar = 0 THEN SET v_dias_a_cobrar = 1; END IF;

    -- 4. Buscar la tarifa vigente para la categoría en la fecha de salida
    SELECT precio_dia INTO v_precio_dia 
    FROM tarifas 
    WHERE id_categoria = v_id_categoria 
      AND v_fecha_salida BETWEEN fecha_inicio AND fecha_fin
    LIMIT 1;

    -- 5. Calcular monto de extras vinculados
    SELECT IFNULL(SUM(e.costo_fijo * ae.cantidad), 0) INTO v_monto_extras
    FROM alquiler_extras ae
    JOIN extras e ON ae.id_extra = e.id_extra
    WHERE ae.id_alquiler = p_id_alquiler;

    -- 6. Cálculo Final
    SET v_monto_base = v_dias_a_cobrar * v_precio_dia;
    SET v_total_final = v_monto_base + v_monto_extras;

    -- 7. Actualizar la tabla de alquileres
    UPDATE alquileres SET 
        fecha_retorno_real = p_fecha_retorno_real,
        cantidad_dias = v_dias_a_cobrar,
        monto_total_alquiler = v_total_final,
        estado_alquiler = 'Finalizado'
    WHERE id_alquiler = p_id_alquiler;
    
    -- 8. Liberar el vehículo
    UPDATE vehiculos SET estado = 'Disponible' WHERE id_vehiculo = v_id_vehiculo;

    -- Devolver el resultado para la interfaz web
    SELECT v_dias_a_cobrar AS dias_calculados, 
           v_monto_base AS subtotal_vehiculo, 
           v_monto_extras AS total_extras, 
           v_total_final AS total_a_pagar;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_gestionar_deposito` (IN `p_id_alquiler` INT, IN `p_nuevo_estado` ENUM('Devuelto','Ejecutado Parcial','Ejecutado Total'), IN `p_monto_final_retornado` DECIMAL(10,2), IN `p_observaciones` TEXT)   BEGIN
    -- 1. Actualizar el estado del depósito en el alquiler
    UPDATE alquileres SET 
        estado_deposito = p_nuevo_estado,
        notas_deposito = p_observaciones
    WHERE id_alquiler = p_id_alquiler;

    -- 2. Registrar el evento en el historial para auditoría
    INSERT INTO `historial_alquileres` (
        `id_alquiler`, 
        `id_cliente`, 
        `id_vehiculo`, 
        `estado_alquiler`, 
        `monto_registrado`, 
        `observaciones`
    )
    SELECT 
        id_alquiler, id_cliente, id_vehiculo, estado_alquiler, p_monto_final_retornado,
        CONCAT('Gestión de Depósito: ', p_nuevo_estado, '. Notas: ', p_observaciones)
    FROM alquileres 
    WHERE id_alquiler = p_id_alquiler;

    -- 3. Confirmación para la interfaz
    SELECT 'Depósito actualizado correctamente' AS mensaje, p_nuevo_estado AS estado;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alquileres`
--

CREATE TABLE `alquileres` (
  `id_alquiler` int(11) NOT NULL,
  `id_vehiculo` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `fecha_salida` datetime NOT NULL,
  `fecha_retorno_prevista` datetime NOT NULL,
  `fecha_retorno_real` datetime DEFAULT NULL,
  `cantidad_dias` int(11) DEFAULT 0,
  `monto_total_alquiler` decimal(10,2) DEFAULT 0.00,
  `estado_alquiler` enum('Activo','Finalizado','Reservado') DEFAULT NULL,
  `monto_deposito` decimal(10,2) DEFAULT 0.00,
  `estado_deposito` enum('Retenido','Devuelto','Ejecutado Parcial','Ejecutado Total') DEFAULT 'Retenido',
  `notas_deposito` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alquileres`
--

INSERT INTO `alquileres` (`id_alquiler`, `id_vehiculo`, `id_cliente`, `fecha_salida`, `fecha_retorno_prevista`, `fecha_retorno_real`, `cantidad_dias`, `monto_total_alquiler`, `estado_alquiler`, `monto_deposito`, `estado_deposito`, `notas_deposito`) VALUES
(1, 1, 1, '2026-03-10 09:00:00', '2026-03-15 09:00:00', '2026-03-15 10:30:00', 5, 375.00, 'Finalizado', 0.00, 'Retenido', NULL),
(2, 2, 2, '2026-03-12 10:00:00', '2026-03-14 10:00:00', '2026-03-14 09:00:00', 2, 220.00, 'Finalizado', 0.00, 'Retenido', NULL),
(3, 6, 4, '2026-03-29 09:00:00', '2026-04-03 09:00:00', NULL, 5, 800.00, 'Activo', 0.00, 'Retenido', NULL);

--
-- Disparadores `alquileres`
--
DELIMITER $$
CREATE TRIGGER `after_alquiler_update` AFTER UPDATE ON `alquileres` FOR EACH ROW BEGIN
    -- Solo inserta si el estado o el monto cambiaron
    IF (OLD.estado_alquiler <> NEW.estado_alquiler OR OLD.monto_total_alquiler <> NEW.monto_total_alquiler) THEN
        INSERT INTO `historial_alquileres` (
            `id_alquiler`, 
            `id_cliente`, 
            `id_vehiculo`, 
            `estado_alquiler`, 
            `monto_registrado`, 
            `observaciones`
        )
        VALUES (
            NEW.id_alquiler, 
            NEW.id_cliente, 
            NEW.id_vehiculo, 
            NEW.estado_alquiler, 
            NEW.monto_total_alquiler, 
            CONCAT('Cambio automático: De ', OLD.estado_alquiler, ' a ', NEW.estado_alquiler)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alquiler_extras`
--

CREATE TABLE `alquiler_extras` (
  `id_alquiler` int(11) NOT NULL,
  `id_extra` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alquiler_extras`
--

INSERT INTO `alquiler_extras` (`id_alquiler`, `id_extra`, `cantidad`) VALUES
(1, 2, 1),
(2, 3, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre_categoria`, `descripcion`) VALUES
(1, 'Económico', 'Vehículos compactos de bajo consumo'),
(2, 'Sedán', 'Vehículos familiares con maletero amplio'),
(3, 'SUV / 4x4', 'Vehículos rústicos de alta gama');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `licencia_conducir` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `identificacion` enum('CI','Pasaporte','RIF') NOT NULL,
  `id_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nombre`, `apellido`, `telefono`, `licencia_conducir`, `email`, `identificacion`, `id_usuario`) VALUES
(1, 'Hernán', 'Narváez', '0412-5556677', 'V-25111222', 'hernan.dev@email.com', 'CI', NULL),
(2, 'Miranda', 'Brito', '0424-8889900', 'V-26333444', 'Miranda@email.com', 'CI', NULL),
(3, 'Carlos', 'Rodríguez', '0414-7771122', 'V-15888999', 'carlos.rod@gmail.com', 'CI', NULL),
(4, 'Sarah', 'Smith', '+1-202-555-0101', 'USA-D09922', 'ssmith.travel@yahoo.com', 'Pasaporte', NULL),
(5, 'Miguel', 'Ángel', '0295-4443322', 'V-12000555', 'm.angel@hotmail.com', 'CI', NULL),
(6, 'Inversiones', 'Gómez C.A.', '0295-1110022', 'J-30555666-0', 'admin@gomezca.ve', 'RIF', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `extras`
--

CREATE TABLE `extras` (
  `id_extra` int(11) NOT NULL,
  `nombre_extra` varchar(50) DEFAULT NULL,
  `costo_fijo` decimal(10,2) DEFAULT NULL,
  `tipo` enum('Servicio','Accesorio') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `extras`
--

INSERT INTO `extras` (`id_extra`, `nombre_extra`, `costo_fijo`, `tipo`) VALUES
(1, 'Silla de Bebé', 12.00, 'Accesorio'),
(2, 'Seguro Premium Full', 35.00, 'Servicio'),
(3, 'GPS Satelital Garmin', 8.00, 'Accesorio'),
(4, 'Conductor Adicional', 20.00, 'Servicio'),
(5, 'Nevera Playera', 5.00, 'Accesorio');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_alquileres`
--

CREATE TABLE `historial_alquileres` (
  `id_historial` int(11) NOT NULL,
  `id_alquiler` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_vehiculo` int(11) NOT NULL,
  `id_pago` int(11) DEFAULT NULL,
  `estado_alquiler` enum('Activo','Finalizado','Reservado','Cancelado') NOT NULL,
  `monto_registrado` decimal(10,2) DEFAULT 0.00,
  `fecha_evento` datetime DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id_mantenimiento` int(11) NOT NULL,
  `id_vehiculo` int(11) NOT NULL,
  `tipo_mantenimiento` varchar(100) NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin_estimada` datetime DEFAULT NULL,
  `costo_mantenimiento` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `estado_mantenimiento` enum('Programado','En Proceso','Completado') DEFAULT 'Programado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id_metodo_pago` int(11) NOT NULL,
  `tipo_metodo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id_metodo_pago`, `tipo_metodo`) VALUES
(1, 'Pago Movil'),
(2, 'Debito'),
(3, 'Credito'),
(4, 'Efectivo Bs'),
(5, 'Efectivo dolares'),
(6, 'Binance'),
(7, 'Paypal'),
(8, 'Zelle');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_alquiler` int(11) DEFAULT NULL,
  `id_extra` int(11) DEFAULT NULL,
  `id_metodo_pago` int(11) DEFAULT NULL,
  `fecha_pago` datetime DEFAULT current_timestamp(),
  `monto_total` decimal(10,2) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id_pago`, `id_alquiler`, `id_extra`, `id_metodo_pago`, `fecha_pago`, `monto_total`, `referencia`) VALUES
(1, 1, NULL, 8, '2026-03-17 10:48:34', 375.00, 'ZELLE-H123'),
(2, 1, 2, 5, '2026-03-17 10:48:34', 35.00, 'CASH-001'),
(3, 2, NULL, 1, '2026-03-17 10:48:34', 220.00, 'PM-MIRANDA1');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `registro_de_pagos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `registro_de_pagos` (
`Nro_factura` int(11)
,`Fecha_pago` datetime
,`Tipo_de_Documento` enum('CI','Pasaporte','RIF')
,`Licencia_de_Conducir` varchar(50)
,`Cliente` varchar(201)
,`Vehículo` varchar(124)
,`Fecha_salida` datetime
,`Fecha_fin` datetime
,`Concepto` varchar(50)
,`Metodo_pago` varchar(50)
,`Monto_pagado` decimal(10,2)
,`Referencia` varchar(100)
,`Estatus_actual` enum('Activo','Finalizado','Reservado')
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarifas`
--

CREATE TABLE `tarifas` (
  `id_tarifa` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `precio_dia` decimal(10,2) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarifas`
--

INSERT INTO `tarifas` (`id_tarifa`, `id_categoria`, `precio_dia`, `fecha_inicio`, `fecha_fin`, `descripcion`) VALUES
(1, 1, 30.00, '2026-01-01', '2026-06-30', 'Temporada Baja - Económico'),
(2, 1, 45.00, '2026-07-01', '2026-08-31', 'Temporada Vacacional - Económico'),
(3, 1, 35.00, '2026-09-01', '2026-12-31', 'Temporada Alta Fin de Año - Económico'),
(4, 2, 50.00, '2026-01-01', '2026-06-30', 'Temporada Baja - Sedán'),
(5, 2, 70.00, '2026-07-01', '2026-08-31', 'Temporada Vacacional - Sedán'),
(6, 2, 60.00, '2026-09-01', '2026-12-31', 'Temporada Alta Fin de Año - Sedán'),
(7, 3, 80.00, '2026-01-01', '2026-06-30', 'Temporada Baja - SUV'),
(8, 3, 110.00, '2026-07-01', '2026-08-31', 'Temporada Vacacional - SUV'),
(9, 3, 95.00, '2026-09-01', '2026-12-31', 'Temporada Alta Fin de Año - SUV');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','empleado','cliente') DEFAULT 'cliente',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `email`, `password`, `rol`, `fecha_registro`, `activo`) VALUES
(1, 'admin@logistics.com', '$12345678', 'admin', '2026-04-07 22:29:56', 1),
(2, 'Mimi@empresa.com', '$12345678', 'empleado', '2026-04-07 22:29:56', 1),
(3, 'Hernan@empresa.com', '$12345678', 'empleado', '2026-04-07 22:29:56', 1),
(4, 'Fab@empresa.com', '$12345678', 'empleado', '2026-04-07 22:29:56', 1),
(5, 'Laurylamasgenial@gmail.com', '$12345678', 'cliente', '2026-04-07 22:29:56', 1),
(6, 'Veronicaingenchismesito@gmail.com', '$12345678', 'cliente', '2026-04-07 22:29:56', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id_vehiculo` int(11) NOT NULL,
  `placa` varchar(20) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `anio` int(11) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `capacidad_pasajeros` int(11) DEFAULT NULL,
  `estado` enum('Disponible','Alquilado','Mantenimiento') DEFAULT 'Disponible',
  `id_categoria` int(11) DEFAULT NULL,
  `url_imagen` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id_vehiculo`, `placa`, `marca`, `modelo`, `anio`, `color`, `capacidad_pasajeros`, `estado`, `id_categoria`, `url_imagen`) VALUES
(1, 'AM-123-NE', 'Toyota', 'Corolla', 2024, 'Blanco', 5, 'Disponible', 2, 'https://di-enrollment-api.s3.amazonaws.com/toyota/models/2024/corolla-hatchback/colors/finish_line_red.png'),
(2, 'SUV-001-MG', 'Suzuki', 'Jimny', 2023, 'Verde Selva', 4, 'Alquilado', 3, 'https://img-ik.cars.co.za/news-site-za/images/2023/11/j5.jpg'),
(3, 'ECO-555-VE', 'Hyundai', 'Getz', 2011, 'Plata', 5, 'Disponible', 1, 'https://cdn.wheel-size.com/thumbs/b7/b3/b7b3c9e53aaab1b95ad618359a0baefa.jpg'),
(4, 'TX-998-IO', 'Jeep', 'Wrangler', 2022, 'Rojo', 4, 'Mantenimiento', 3, 'https://www.autobics.com/wp-content/uploads/2022/10/2022-Jeep-Wrangler-Rubicon-4X4-Hydro-Blue-Pearl-Coat.jpg\r\n'),
(5, 'KIA-442-SA', 'Kia', 'Picanto', 2023, 'Azul', 4, 'Disponible', 1, 'https://falcondrive.ae/public/storage/cars/August2024/qsSUMoBVwudMVnl6uKsZ.png'),
(6, 'EXP-771-RT', 'Ford', 'Explorer', 2021, 'Negro', 7, 'Alquilado', 3, 'https://www.motortrend.com/uploads/sites/10/2020/12/2021-ford-explorer-st-4wd-suv-angular-front.png'),
(7, 'ABC-101', 'Toyota', 'Corolla', 2022, 'Gris', 5, 'Disponible', 2, 'https://d2ivfcfbdvj3sm.cloudfront.net/7fc965ab77efe6e0fa62e4ca1ea7673bb65843530c1e3d8e88cb10/stills_0640_png/MY2022/52227/52227_st0640_116.png'),
(8, 'WGO-102', 'Toyota', 'Wigo', 2018, 'Blanco', 5, 'Disponible', 1, 'https://cdn.wheel-size.com/thumbs/1c/f5/1cf56585e6785d057652bdbc7361edff.jpg\r\n'),
(9, 'ELN-103', 'Hyundai', 'Elantra', 2009, 'Negro', 5, 'Disponible', 2, 'https://th.bing.com/th/id/R.be995ff524d19f2014c8241b887afbe4?rik=pEg4OxUxtwmKbA&pid=ImgRaw&r=0'),
(10, 'TUC-104', 'Hyundai', 'Tucson', 2012, 'Plata', 5, 'Disponible', 3, 'https://picolio.auto123.com/12photo/hyundai/2012-hyundai-tucson-gl_1.jpg'),
(11, 'RIO-105', 'Kia', 'Rio', 2008, 'Rojo', 5, 'Disponible', 1, 'https://images.hgmsites.net/med/2008-kia-rio-5dr-hb-auto-copper_100054722_m.jpg'),
(12, 'SPO-106', 'Kia', 'Sportage', 2018, 'Azul', 5, 'Disponible', 3, 'https://smarty-trend.com/img/c/353.jpg'),
(13, 'CIV-107', 'Honda', 'Civic', 2003, 'Dorado', 5, 'Disponible', 2, 'https://tadvantagebetaprod-com.cdn-convertus.com/uploads/sites/257/2023/05/ex.png'),
(14, 'CRV-108', 'Honda', 'CRV', 2019, 'Blanco', 5, 'Disponible', 3, 'https://th.bing.com/th/id/R.c5e34e5e3130997db32535d489d864f8?rik=XGwBGYkm0wrMVw&pid=ImgRaw&r=0'),
(15, 'MAZ-109', 'Mazda', 'CX-5', 2024, 'Rojo', 5, 'Disponible', 3, 'https://tse4.mm.bing.net/th/id/OIP.zEfxk7zWiTMUYfueG23fewHaEg?rs=1&pid=ImgDetMain&o=7&rm=3'),
(16, 'TES-110', 'Tesla', 'Model 3', 2023, 'Blanco', 5, 'Disponible', 2, 'https://img.freepik.com/fotos-premium/coche-aislado-sobre-fondo-blanco-tesla-modelo-3-coche-blanco-limpio-blanco-sobre-fondo-blanco-blanco-negro_655090-605217.jpg?w=2000'),
(17, 'NISS-111', 'Nissan', 'Sentra', 2021, 'Gris', 5, 'Mantenimiento', 2, 'https://di-uploads-pod9.dealerinspire.com/illininissan/uploads/2021/06/2021-Nissan-Sentra-left-1.jpg'),
(18, 'VW-112', 'Volkswagen', 'Amarok', 2022, 'Negro', 5, 'Alquilado', 3, 'https://tse3.mm.bing.net/th/id/OIP.CjUEj4GYzzorRVaIh0H8uwAAAA?rs=1&pid=ImgDetMain&o=7&rm=3');

-- --------------------------------------------------------

--
-- Estructura para la vista `registro_de_pagos`
--
DROP TABLE IF EXISTS `registro_de_pagos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `registro_de_pagos`  AS SELECT `p`.`id_pago` AS `Nro_factura`, `p`.`fecha_pago` AS `Fecha_pago`, `c`.`identificacion` AS `Tipo_de_Documento`, `c`.`licencia_conducir` AS `Licencia_de_Conducir`, concat(`c`.`nombre`,' ',`c`.`apellido`) AS `Cliente`, concat(`v`.`marca`,' ',`v`.`modelo`,' (',`v`.`placa`,')') AS `Vehículo`, `a`.`fecha_salida` AS `Fecha_salida`, `a`.`fecha_retorno_prevista` AS `Fecha_fin`, CASE WHEN `p`.`id_extra` is null THEN 'Alquiler Base' ELSE `e`.`nombre_extra` END AS `Concepto`, `mp`.`tipo_metodo` AS `Metodo_pago`, `p`.`monto_total` AS `Monto_pagado`, `p`.`referencia` AS `Referencia`, `a`.`estado_alquiler` AS `Estatus_actual` FROM (((((`pagos` `p` join `alquileres` `a` on(`p`.`id_alquiler` = `a`.`id_alquiler`)) join `clientes` `c` on(`a`.`id_cliente` = `c`.`id_cliente`)) join `vehiculos` `v` on(`a`.`id_vehiculo` = `v`.`id_vehiculo`)) join `metodos_pago` `mp` on(`p`.`id_metodo_pago` = `mp`.`id_metodo_pago`)) left join `extras` `e` on(`p`.`id_extra` = `e`.`id_extra`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alquileres`
--
ALTER TABLE `alquileres`
  ADD PRIMARY KEY (`id_alquiler`),
  ADD KEY `id_vehiculo` (`id_vehiculo`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `alquiler_extras`
--
ALTER TABLE `alquiler_extras`
  ADD PRIMARY KEY (`id_alquiler`,`id_extra`),
  ADD KEY `id_extra` (`id_extra`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD KEY `fk_cliente_usuario` (`id_usuario`);

--
-- Indices de la tabla `extras`
--
ALTER TABLE `extras`
  ADD PRIMARY KEY (`id_extra`);

--
-- Indices de la tabla `historial_alquileres`
--
ALTER TABLE `historial_alquileres`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `fk_hist_alquiler` (`id_alquiler`),
  ADD KEY `fk_hist_cliente` (`id_cliente`),
  ADD KEY `fk_hist_vehiculo` (`id_vehiculo`),
  ADD KEY `fk_hist_pago` (`id_pago`);

--
-- Indices de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id_mantenimiento`),
  ADD KEY `fk_mantenimiento_vehiculo` (`id_vehiculo`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id_metodo_pago`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_metodo_pago` (`id_metodo_pago`),
  ADD KEY `id_alquiler` (`id_alquiler`,`id_extra`);

--
-- Indices de la tabla `tarifas`
--
ALTER TABLE `tarifas`
  ADD PRIMARY KEY (`id_tarifa`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id_vehiculo`),
  ADD UNIQUE KEY `placa` (`placa`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alquileres`
--
ALTER TABLE `alquileres`
  MODIFY `id_alquiler` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `extras`
--
ALTER TABLE `extras`
  MODIFY `id_extra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_alquileres`
--
ALTER TABLE `historial_alquileres`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id_mantenimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id_metodo_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tarifas`
--
ALTER TABLE `tarifas`
  MODIFY `id_tarifa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id_vehiculo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alquileres`
--
ALTER TABLE `alquileres`
  ADD CONSTRAINT `alquileres_ibfk_1` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculos` (`id_vehiculo`),
  ADD CONSTRAINT `alquileres_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`);

--
-- Filtros para la tabla `alquiler_extras`
--
ALTER TABLE `alquiler_extras`
  ADD CONSTRAINT `alquiler_extras_ibfk_1` FOREIGN KEY (`id_alquiler`) REFERENCES `alquileres` (`id_alquiler`),
  ADD CONSTRAINT `alquiler_extras_ibfk_2` FOREIGN KEY (`id_extra`) REFERENCES `extras` (`id_extra`);

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_alquileres`
--
ALTER TABLE `historial_alquileres`
  ADD CONSTRAINT `fk_hist_alquiler` FOREIGN KEY (`id_alquiler`) REFERENCES `alquileres` (`id_alquiler`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hist_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `fk_hist_pago` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id_pago`),
  ADD CONSTRAINT `fk_hist_vehiculo` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculos` (`id_vehiculo`);

--
-- Filtros para la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `fk_mantenimiento_vehiculo` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculos` (`id_vehiculo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_alquiler_extra` FOREIGN KEY (`id_alquiler`,`id_extra`) REFERENCES `alquiler_extras` (`id_alquiler`, `id_extra`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_alquiler`) REFERENCES `alquileres` (`id_alquiler`),
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodos_pago` (`id_metodo_pago`);

--
-- Filtros para la tabla `tarifas`
--
ALTER TABLE `tarifas`
  ADD CONSTRAINT `tarifas_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
