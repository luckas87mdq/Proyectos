<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error

// Lógica para capturar y mostrar mensajes de redirección (ej. desde eliminar_empleado.php)
if (isset($_GET['mensaje'])) {
    $mensaje = "<div class='mensaje exito'>" . htmlspecialchars($_GET['mensaje']) . "</div>";
}
if (isset($_GET['error'])) {
    $mensaje = "<div class='mensaje error'>" . htmlspecialchars($_GET['error']) . "</div>";
}

// Variables para la búsqueda
$search_term = '';
if (isset($_GET['search_term'])) {
    $search_term = trim($_GET['search_term']);
}

// Consulta para obtener todos los empleados (con búsqueda opcional)
$sql = "SELECT e.id, e.nombre, e.apellido, e.legajo_matricula, s.nombre AS sector_nombre
        FROM empleados e
        LEFT JOIN sectores s ON e.sector_id = s.id";

$params = [];
$types = "";

if (!empty($search_term)) {
    // Añadir condiciones de búsqueda
    $sql .= " WHERE e.nombre LIKE ? OR e.apellido LIKE ? OR e.legajo_matricula LIKE ? OR s.nombre LIKE ?";
    $search_param = '%' . $search_term . '%';
    $params = [$search_param, $search_param, $search_param, $search_param];
    $types = "ssss";
}

$sql .= " ORDER BY e.apellido, e.nombre";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$empleados = [];
if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $empleados[] = $row;
    }
} else {
    $mensaje = "<div class='mensaje error'>Error al cargar empleados: " . $stmt->error . "</div>";
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados</title>
    <link rel="stylesheet" href="css/estilo_gestion_empleados.css"> 
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
            <h1>Gestión de Empleados</h1>
        </header>

        <?= $mensaje ?>

        <a href="agregar_empleado.php" class="add-employee-btn"><i class="fas fa-user-plus"></i> Agregar Nuevo Empleado</a>

        <div class="search-container">
            <form action="gestion_empleados.php" method="GET">
                <input type="text" name="search_term" placeholder="Buscar por nombre, legajo o sector..." value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit"><i class="fas fa-search"></i> Buscar</button>
            </form>
            <?php if (!empty($search_term)): ?>
                <a href="gestion_empleados.php" style="text-decoration: none; color: #dc3545; font-size: 0.9em;"><i class="fas fa-times-circle"></i> Limpiar búsqueda</a>
            <?php endif; ?>
        </div>


        <?php if (empty($empleados)): ?>
            <p class="no-data">
                <?php if (!empty($search_term)): ?>
                    No se encontraron empleados que coincidan con "<?= htmlspecialchars($search_term) ?>".
                <?php else: ?>
                    No hay empleados registrados en el sistema.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Legajo/Matrícula</th>
                            <th>Sector</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $empleado): ?>
                            <tr>
                                <td><?= htmlspecialchars($empleado['nombre']) ?></td>
                                <td><?= htmlspecialchars($empleado['apellido']) ?></td>
                                <td><?= htmlspecialchars($empleado['legajo_matricula']) ?></td>
                                <td><?= htmlspecialchars($empleado['sector_nombre'] ?? 'Sin Sector') ?></td>
                                <td class="acciones">
                                    <a href="editar_empleado.php?id=<?= htmlspecialchars($empleado['id']) ?>" class="action-link edit-btn" title="Editar"><i class="fas fa-edit"></i> Editar</a>
                                    <a href="eliminar_empleado.php?id=<?= htmlspecialchars($empleado['id']) ?>" class="action-link delete-btn" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar a este empleado y todas sus asignaciones?');"><i class="fas fa-trash-alt"></i> Eliminar</a>
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