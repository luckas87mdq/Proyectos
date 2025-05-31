<?php
// gestion_teclados.php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error

// Lógica para capturar y mostrar mensajes de redirección
if (isset($_GET['mensaje'])) {
    $mensaje = "<div class='mensaje exito'>" . htmlspecialchars($_GET['mensaje']) . "</div>";
} elseif (isset($_GET['error'])) {
    $mensaje = "<div class='mensaje error'>" . htmlspecialchars($_GET['error']) . "</div>";
}

// Variables para la búsqueda
$search_term = '';
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Consulta para obtener todos los teclados, incluyendo el conteo de asignaciones
$sql = "SELECT t.id, t.nombre, t.ubicacion, COUNT(a.id) AS total_asignaciones
        FROM teclados t
        LEFT JOIN asignaciones a ON t.id = a.teclado_id";

$params = [];
$types = "";

if (!empty($search_term)) {
    // Añadir condiciones de búsqueda
    $sql .= " WHERE t.nombre LIKE ? OR t.ubicacion LIKE ?";
    $search_param = '%' . $search_term . '%';
    $params = [$search_param, $search_param];
    $types = "ss"; // Dos parámetros de string
}

$sql .= " GROUP BY t.id, t.nombre, t.ubicacion"; // Agrupar por teclado para contar asignaciones
$sql .= " ORDER BY t.nombre ASC"; // Ordenar por nombre del teclado

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $mensaje = "<div class='mensaje error'>Error al preparar la consulta de teclados: " . $conn->error . "</div>";
    $teclados = [];
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $teclados = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teclados[] = $row;
        }
    } else {
        $mensaje = "<div class='mensaje error'>Error al cargar teclados: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Teclados</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_gestion_teclados.css">
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
                <li><a href="dashboard_teclados.php"><i class="fas fa-desktop"></i> Dashboard Teclados</a></li>
                <li><a href="gestion_empleados.php"><i class="fas fa-users"></i> Gestión Empleados</a></li>
                 <li class="active"><a href="gestion_teclados.php"><i class="fas fa-keyboard"></i> Gestión Teclados</a></li>
                 <li><a href="asignar_posicion.php"><i class="fas fa-link"></i> Asignar Posición</a></li>
                 <li><a href="cargar_asignaciones_csv.php"><i class="fas fa-file-csv"></i> Cargar Asignaciones CSV</a></li>
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
            <h1><i class="fas fa-keyboard"></i> Gestión de Teclados</h1>
        </header>

        <?= $mensaje ?>

        <div class="search-container">
            <form action="gestion_teclados.php" method="GET">
                <input type="text" name="search" placeholder="Buscar por nombre o ubicación..." value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit"><i class="fas fa-search"></i> Buscar</button>
            </form>
            <a href="agregar_teclado.php" class="add-new-btn"><i class="fas fa-plus-circle"></i> Agregar Nuevo Teclado</a>
            <?php if (!empty($search_term)): ?>
                <a href="gestion_teclados.php" class="clear-search-link"><i class="fas fa-times-circle"></i> Limpiar búsqueda</a>
            <?php endif; ?>
        </div>

        <?php if (empty($teclados)): ?>
            <p class="no-data">
                <?php if (!empty($search_term)): ?>
                    No se encontraron teclados que coincidan con "<?= htmlspecialchars($search_term) ?>".
                <?php else: ?>
                    No hay teclados registrados en el sistema.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Ubicación</th>
                            <th>Asignaciones</th> <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teclados as $teclado): ?>
                            <tr>
                                <td data-label="ID:"><?= htmlspecialchars($teclado['id']) ?></td>
                                <td data-label="Nombre:"><?= htmlspecialchars($teclado['nombre']) ?></td>
                                <td data-label="Ubicación:">
                                    <?= htmlspecialchars($teclado['ubicacion']) ?>
                                </td>
                                <td data-label="Asignaciones:">
                                    <?= htmlspecialchars($teclado['total_asignaciones']) ?> </td>
                                <td data-label="Acciones:" class="action-links">
                                    <a href="dashboard_teclados.php?teclado_id=<?= htmlspecialchars($teclado['id']) ?>" class="action-link view-link" title="Ver Asignaciones"><i class="fas fa-eye"></i> Ver Asignaciones</a>
                                    <a href="editar_teclado.php?id=<?= htmlspecialchars($teclado['id']) ?>" class="action-link edit-link" title="Editar Teclado"><i class="fas fa-edit"></i></a>
                                    <a href="eliminar_teclado.php?id=<?= htmlspecialchars($teclado['id']) ?>" class="action-link delete-link" title="Eliminar Teclado" onclick="return confirm('¿Estás seguro de eliminar este teclado? Se eliminarán también todas sus asignaciones.');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>