<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error

// Obtener la lista de teclados para el selector
$teclados = [];
$result_teclados = $conn->query("SELECT id, nombre FROM teclados ORDER BY nombre");
if ($result_teclados->num_rows > 0) {
    while ($row = $result_teclados->fetch_assoc()) {
        $teclados[] = $row;
    }
}

// Obtener la lista de empleados para el selector
$empleados = []; // Ahora se llama $empleados
$result_empleados = $conn->query("SELECT id, nombre, apellido, legajo_matricula FROM empleados ORDER BY nombre, apellido");
if ($result_empleados->num_rows > 0) {
    while ($row = $result_empleados->fetch_assoc()) {
        $empleados[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teclado_id = $_POST['teclado_id'] ?? '';
    $empleado_id = $_POST['empleado_id'] ?? ''; // Ahora se llama $empleado_id
    $posicion = trim($_POST['posicion'] ?? '');
    $numero_tarjeta = trim($_POST['numero_tarjeta'] ?? '');
    $numero_llavero = trim($_POST['numero_llavero'] ?? '');

    // Validaciones básicas
    if (empty($teclado_id) || empty($empleado_id) || empty($posicion)) {
        $mensaje = "<div class='mensaje error'>Error: Todos los campos obligatorios deben ser completados (Teclado, Empleado, Posición).</div>";
    } elseif (!is_numeric($posicion) || $posicion <= 0) {
        $mensaje = "<div class='mensaje error'>Error: La posición debe ser un número positivo.</div>";
    } else {
        // Verificar si ya existe una asignación para este teclado y posición
        $stmt_check = $conn->prepare("SELECT id FROM asignaciones WHERE teclado_id = ? AND posicion = ?");
        $stmt_check->bind_param("ii", $teclado_id, $posicion);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensaje = "<div class='mensaje error'>Error: Ya existe una asignación para la posición " . htmlspecialchars($posicion) . " en este teclado.</div>";
        } else {
            // Verificar si el empleado ya tiene una asignación en CUALQUIER teclado
            $stmt_check_empleado_assigned = $conn->prepare("SELECT id FROM asignaciones WHERE empleado_id = ?");
            $stmt_check_empleado_assigned->bind_param("i", $empleado_id);
            $stmt_check_empleado_assigned->execute();
            $stmt_check_empleado_assigned->store_result();

            if ($stmt_check_empleado_assigned->num_rows > 0) {
                $mensaje = "<div class='mensaje error'>Error: Este empleado ya tiene una posición asignada.</div>";
            } else {
                // Insertar la asignación
                $stmt = $conn->prepare("INSERT INTO asignaciones (teclado_id, empleado_id, posicion, numero_tarjeta, numero_llavero) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iisss", $teclado_id, $empleado_id, $posicion, $numero_tarjeta, $numero_llavero);

                if ($stmt->execute()) {
                    $mensaje = "<div class='mensaje exito'>Posición asignada correctamente.</div>";
                    // Limpiar los campos después de un éxito
                    $_POST['posicion'] = '';
                    $_POST['numero_tarjeta'] = '';
                    $_POST['numero_llavero'] = '';
                } else {
                    $mensaje = "<div class='mensaje error'>Error al asignar posición: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
            $stmt_check_empleado_assigned->close();
        }
        $stmt_check->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Posición</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_asignar_posicion.css">
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
            <h1>Asignar Posición a Teclado</h1>
        </header>

        <div class="form-container">
            <?= $mensaje ?>
            <form action="asignar_posicion.php" method="POST" class="modern-form">
                <div class="form-group">
                    <label for="teclado_id"><i class="fas fa-keyboard"></i> Seleccionar Teclado:</label>
                    <select id="teclado_id" name="teclado_id" required>
                        <option value="">-- Seleccione un teclado --</option>
                        <?php foreach ($teclados as $teclado): ?>
                            <option value="<?= htmlspecialchars($teclado['id']) ?>"
                                <?= (isset($_POST['teclado_id']) && $_POST['teclado_id'] == $teclado['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($teclado['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="empleado_id"><i class="fas fa-user"></i> Seleccionar Empleado:</label>
                    <select id="empleado_id" name="empleado_id" required>
                        <option value="">-- Seleccione un empleado --</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?= htmlspecialchars($empleado['id']) ?>"
                                <?= (isset($_POST['empleado_id']) && $_POST['empleado_id'] == $empleado['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($empleado['nombre']) . ' ' . htmlspecialchars($empleado['apellido']) . ' (' . htmlspecialchars($empleado['legajo_matricula']) . ')' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="posicion"><i class="fas fa-hashtag"></i> Posición:</label>
                    <input type="number" id="posicion" name="posicion" min="1" required value="<?= htmlspecialchars($_POST['posicion'] ?? '') ?>">
                    <small class="form-help">Posición en el teclado (ej. 1, 2, 3).</small>
                </div>

                <div class="form-group">
                    <label for="numero_tarjeta"><i class="fas fa-credit-card"></i> Número de Tarjeta (Opcional):</label>
                    <input type="text" id="numero_tarjeta" name="numero_tarjeta" value="<?= htmlspecialchars($_POST['numero_tarjeta'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="numero_llavero"><i class="fas fa-keychain"></i> Número de Llavero (Opcional):</label>
                    <input type="text" id="numero_llavero" name="numero_llavero" value="<?= htmlspecialchars($_POST['numero_llavero'] ?? '') ?>">
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-link"></i> Asignar Posición</button>
            </form>
        </div>
    </div>
</body>
</html>