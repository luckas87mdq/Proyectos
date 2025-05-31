<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error
$asignacion = null; // Variable para almacenar los datos de la asignación a editar
$teclados = []; // Para poblar el select de teclados
$empleados = []; // Para poblar el select de empleados

// --- Lógica para cargar teclados existentes para el select ---
$stmt_teclados = $conn->prepare("SELECT id, nombre FROM teclados ORDER BY nombre");
if ($stmt_teclados->execute()) {
    $result_teclados = $stmt_teclados->get_result();
    while ($row = $result_teclados->fetch_assoc()) {
        $teclados[] = $row;
    }
} else {
    $mensaje .= "<div class='mensaje error'>Error al cargar teclados: " . $stmt_teclados->error . "</div>";
}
$stmt_teclados->close();

// --- Lógica para cargar empleados existentes para el select ---
$stmt_empleados = $conn->prepare("SELECT id, nombre, apellido, legajo_matricula FROM empleados ORDER BY nombre, apellido");
if ($stmt_empleados->execute()) {
    $result_empleados = $stmt_empleados->get_result();
    while ($row = $result_empleados->fetch_assoc()) {
        $empleados[] = $row;
    }
} else {
    $mensaje .= "<div class='mensaje error'>Error al cargar empleados: " . $stmt_empleados->error . "</div>";
}
$stmt_empleados->close();


// 1. Obtener el ID de la asignación de la URL y cargar sus datos
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $asignacion_id = (int)$_GET['id'];

    $stmt_asignacion = $conn->prepare("SELECT id, teclado_id, empleado_id, posicion, numero_tarjeta, numero_llavero FROM asignaciones WHERE id = ?");
    $stmt_asignacion->bind_param("i", $asignacion_id);
    if ($stmt_asignacion->execute()) {
        $result_asignacion = $stmt_asignacion->get_result();
        if ($result_asignacion->num_rows == 1) {
            $asignacion = $result_asignacion->fetch_assoc();
        } else {
            $mensaje = "<div class='mensaje error'>Error: Asignación no encontrada.</div>";
            $asignacion_id = null; // Para no intentar procesar el formulario si no se encuentra
        }
    } else {
        $mensaje = "<div class='mensaje error'>Error al buscar la asignación: " . $stmt_asignacion->error . "</div>";
        $asignacion_id = null;
    }
    $stmt_asignacion->close();
} else {
    $mensaje = "<div class='mensaje error'>ID de asignación no especificado o inválido.</div>";
    $asignacion_id = null;
}


// 2. Procesar el formulario cuando se envía (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $asignacion_id !== null) {
    // Obtener y limpiar los datos del formulario
    $id_a_actualizar = $_POST['asignacion_id'] ?? null; // Asegurarse de que el ID del formulario coincida
    $teclado_id = $_POST['teclado_id'] ?? '';
    $empleado_id = $_POST['empleado_id'] ?? '';
    $posicion = trim($_POST['posicion'] ?? '');
    $numero_tarjeta = trim($_POST['numero_tarjeta'] ?? '');
    $numero_llavero = trim($_POST['numero_llavero'] ?? '');

    // Validar que el ID del formulario coincida con el ID que se está editando
    if ($id_a_actualizar != $asignacion_id) {
        $mensaje = "<div class='mensaje error'>Error de seguridad: ID de asignación no coincide.</div>";
    } elseif (empty($teclado_id) || empty($empleado_id) || empty($posicion)) {
        $mensaje = "<div class='mensaje error'>Error: Todos los campos obligatorios (Teclado, Empleado, Posición) deben ser completados.</div>";
    } elseif (!is_numeric($posicion) || $posicion <= 0) {
        $mensaje = "<div class='mensaje error'>Error: La Posición debe ser un número positivo.</div>";
    } else {
        // Iniciar transacción
        $conn->begin_transaction();

        try {
            // 3. Verificar si la posición ya está asignada en el teclado seleccionado
            // Pero permitiendo que la misma asignación mantenga su posición
            $stmt_check_posicion = $conn->prepare("SELECT id FROM asignaciones WHERE teclado_id = ? AND posicion = ? AND id != ?");
            $stmt_check_posicion->bind_param("iii", $teclado_id, $posicion, $asignacion_id);
            $stmt_check_posicion->execute();
            $stmt_check_posicion->store_result();

            if ($stmt_check_posicion->num_rows > 0) {
                $mensaje = "<div class='mensaje error'>Error: La posición " . htmlspecialchars($posicion) . " ya está asignada en este teclado.</div>";
                $conn->rollback();
            } else {
                // 4. Actualizar la asignación en la base de datos
                $stmt_update_asignacion = $conn->prepare("UPDATE asignaciones SET teclado_id = ?, empleado_id = ?, posicion = ?, numero_tarjeta = ?, numero_llavero = ? WHERE id = ?");
                $stmt_update_asignacion->bind_param("iisssi", $teclado_id, $empleado_id, $posicion, $numero_tarjeta, $numero_llavero, $asignacion_id);

                if ($stmt_update_asignacion->execute()) {
                    $mensaje = "<div class='mensaje exito'>Asignación actualizada correctamente.</div>";
                    $conn->commit();
                    // Refrescar los datos de la asignación después de la actualización exitosa
                    $asignacion['teclado_id'] = $teclado_id;
                    $asignacion['empleado_id'] = $empleado_id;
                    $asignacion['posicion'] = $posicion;
                    $asignacion['numero_tarjeta'] = $numero_tarjeta;
                    $asignacion['numero_llavero'] = $numero_llavero;
                } else {
                    throw new Exception("Error al actualizar la asignación: " . $stmt_update_asignacion->error);
                }
                $stmt_update_asignacion->close();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "<div class='mensaje error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Asignación</title>
    <link rel="stylesheet" href="css/estilo_general.css">
<link rel="stylesheet" href="css/estilo_editar_asignacion.css">
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
                <li class="active"><a href="dashboard_teclados.php"><i class="fas fa-desktop"></i> Dashboard Teclados</a></li> <li><a href="gestion_empleados.php"><i class="fas fa-users"></i> Gestión Empleados</a></li> <li><a href="agregar_teclado.php"><i class="fas fa-plus-square"></i> Agregar Teclado</a></li>
                <li><a href="agregar_empleado.php"><i class="fas fa-user-plus"></i> Agregar Empleado</a></li>
                <li><a href="asignar_posicion.php">🔗 Asignar Posición</a></li>
                <li><a href="cargar_asignaciones_csv.php"><i class="fas fa-file-csv"></i> Cargar CSV Asignaciones</a></li>
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
            <h1>Editar Asignación</h1>
        </header>

        <div class="form-container">
            <?= $mensaje ?>

            <?php if ($asignacion_id !== null && $asignacion !== null): ?>
            <form action="editar_asignacion.php?id=<?= $asignacion_id ?>" method="POST" class="modern-form">
                <input type="hidden" name="asignacion_id" value="<?= htmlspecialchars($asignacion['id']) ?>">

                <div class="form-group">
                    <label for="teclado_id"><i class="fas fa-keyboard"></i> Seleccionar Teclado:</label>
                    <select id="teclado_id" name="teclado_id" required>
                        <option value="">-- Seleccione un teclado --</option>
                        <?php foreach ($teclados as $teclado): ?>
                            <option value="<?= htmlspecialchars($teclado['id']) ?>"
                                <?= ($asignacion['teclado_id'] == $teclado['id']) ? 'selected' : '' ?>>
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
                                <?= ($asignacion['empleado_id'] == $empleado['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($empleado['nombre']) . ' ' . htmlspecialchars($empleado['apellido']) . ' (' . htmlspecialchars($empleado['legajo_matricula']) . ')' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="posicion"><i class="fas fa-hashtag"></i> Posición:</label>
                    <input type="number" id="posicion" name="posicion" min="1" required value="<?= htmlspecialchars($asignacion['posicion'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="numero_tarjeta"><i class="fas fa-credit-card"></i> Número de Tarjeta (Opcional):</label>
                    <input type="text" id="numero_tarjeta" name="numero_tarjeta" value="<?= htmlspecialchars($asignacion['numero_tarjeta'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="numero_llavero"><i class="fas fa-keychain"></i> Número de Llavero (Opcional):</label>
                    <input type="text" id="numero_llavero" name="numero_llavero" value="<?= htmlspecialchars($asignacion['numero_llavero'] ?? '') ?>">
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Actualizar Asignación</button>
            </form>
            <?php else: ?>
                <p class="no-data">No se pudo cargar la información de la asignación para editar.</p>
                <p><a href="dashboard_teclados.php">Volver al Dashboard</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>