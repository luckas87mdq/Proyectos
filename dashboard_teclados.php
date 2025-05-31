<?php
include 'db_teclados.php'; // Conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes (aunque no se usa mucho aquí)

// --- Consulta para obtener la cantidad total de teclados ---
$total_teclados = 0;
$stmt_total_teclados = $conn->prepare("SELECT COUNT(id) AS total FROM teclados");
if ($stmt_total_teclados->execute()) {
    $result_total_teclados = $stmt_total_teclados->get_result();
    $row_total_teclados = $result_total_teclados->fetch_assoc();
    $total_teclados = $row_total_teclados['total'];
} else {
    $mensaje .= "<div class='mensaje error'>Error al contar teclados: " . $stmt_total_teclados->error . "</div>";
}
$stmt_total_teclados->close();

// --- Consulta para obtener la cantidad total de empleados ---
$total_empleados = 0;
$stmt_total_empleados = $conn->prepare("SELECT COUNT(id) AS total FROM empleados");
if ($stmt_total_empleados->execute()) {
    $result_total_empleados = $stmt_total_empleados->get_result();
    $row_total_empleados = $result_total_empleados->fetch_assoc();
    $total_empleados = $row_total_empleados['total'];
} else {
    $mensaje .= "<div class='mensaje error'>Error al contar empleados: " . $stmt_total_empleados->error . "</div>";
}
$stmt_total_empleados->close();

// --- Consulta para obtener la cantidad total de teclados REGISTRADOS (sin importar asignaciones) ---
$total_teclados_registrados = 0;
$stmt_teclados_registrados = $conn->prepare("SELECT COUNT(id) AS total FROM teclados");
if ($stmt_teclados_registrados->execute()) {
    $result_teclados_registrados = $stmt_teclados_registrados->get_result();
    $row_teclados_registrados = $result_teclados_registrados->fetch_assoc();
    $total_teclados_registrados = $row_teclados_registrados['total'];
}
$stmt_teclados_registrados->close();


// Consulta principal para obtener teclados con sus asignaciones, empleados y sectores
$sql = "SELECT
            t.id AS teclado_id,
            t.nombre AS teclado_nombre,
            a.posicion,
            e.id AS empleado_id, -- Añadir el ID del empleado para el enlace de edición
            e.nombre AS empleado_nombre,
            e.apellido AS empleado_apellido,
            e.legajo_matricula,
            s.nombre AS sector_nombre,
            a.numero_tarjeta,
            a.numero_llavero,
            a.id AS asignacion_id
        FROM
            teclados t
        LEFT JOIN
            asignaciones a ON t.id = a.teclado_id
        LEFT JOIN
            empleados e ON a.empleado_id = e.id
        LEFT JOIN
            sectores s ON e.sector_id = s.id
        ORDER BY
            t.nombre, a.posicion";

$result = $conn->query($sql);

$teclados_data = []; // Usaremos este array para organizar los datos por teclado

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $teclado_id = $row['teclado_id'];
        $teclado_nombre = htmlspecialchars($row['teclado_nombre']);

        // Si es la primera vez que encontramos este teclado, lo inicializamos en el array
        if (!isset($teclados_data[$teclado_id])) {
            $teclados_data[$teclado_id] = [
                'nombre' => $teclado_nombre,
                'asignaciones' => []
            ];
        }

        // Si hay una asignación para este teclado, la agregamos
        if ($row['asignacion_id'] !== null) { // Verifica si hay una asignación real
            $teclados_data[$teclado_id]['asignaciones'][] = [
                'posicion' => htmlspecialchars($row['posicion']),
                'empleado_id' => $row['empleado_id'], // Asegurarse de pasar el ID del empleado
                'empleado_nombre' => htmlspecialchars($row['empleado_nombre'] ?? 'N/A'),
                'empleado_apellido' => htmlspecialchars($row['empleado_apellido'] ?? ''),
                'legajo_matricula' => htmlspecialchars($row['legajo_matricula'] ?? 'N/A'),
                'sector_nombre' => htmlspecialchars($row['sector_nombre'] ?? 'N/A'),
                'numero_tarjeta' => htmlspecialchars($row['numero_tarjeta'] ?? 'N/A'),
                'numero_llavero' => htmlspecialchars($row['numero_llavero'] ?? 'N/A'),
                'asignacion_id' => $row['asignacion_id']
            ];
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Teclados</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_dashboard_teclados.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-fingerprint"></i>
            <h3>Control de Accesos</h3>
        </div>
        <div class="sidebar-nav">
            <ul>
                <li><a href="gestion_empleados.php"><i class="fas fa-users"></i> Gestión Empleados</a></li> 
                <li class="active"><a href="gestion_teclados.php"><i class="fas fa-keyboard"></i> Gestión Teclados</a></li>

            </ul>
        </div>
        <div class="user-info-sidebar">
            <div class="user-details">
                <p class="user-name">Lucas Musante</p>
                <p class="user-role">Administrador</p>
            </div>
        </div>
    </div>

    <div class="main-content">
        <header>
            <h1>Dashboard de Teclados</h1>
        </header>

        <?= $mensaje ?>

        <div class="info-gadgets">
            <div class="gadget-card">
                <div class="gadget-icon"><i class="fas fa-keyboard"></i></div>
                <div class="gadget-info">
                    <h3>Total Teclados</h3>
                    <p class="gadget-number"><?= $total_teclados ?></p>
                </div>
            </div>
            <div class="gadget-card">
                <div class="gadget-icon"><i class="fas fa-users"></i></div>
                <div class="gadget-info">
                    <h3>Total Empleados</h3>
                    <p class="gadget-number"><?= $total_empleados ?></p>
                </div>
            </div>
        </div>
        
    </div>
</body>
</html>