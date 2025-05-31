<?php
// agregar_empleado.php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error
$sectores_existentes = []; // Para poblar el select del sector
$teclados_existentes = []; // Para poblar los checkboxes de teclados

// --- Función auxiliar para obtener la próxima posición libre ---
// Esta función encapsula la lógica que estaba en obtener_proxima_posicion_libre.php
function obtenerSiguientePosicionLibre($conn, $teclado_id) {
    $next_free_position = 1;
    $max_position = 9999;

    $stmt = $conn->prepare("SELECT posicion FROM asignaciones WHERE teclado_id = ? ORDER BY posicion ASC");
    if ($stmt === false) {
        return ['success' => false, 'error' => "Error al preparar consulta de posición: " . $conn->error];
    }
    $stmt->bind_param("i", $teclado_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $occupied_positions = [];
    while ($row = $result->fetch_assoc()) {
        $occupied_positions[] = (int)$row['posicion'];
    }
    $stmt->close();

    $occupied_positions_map = array_flip($occupied_positions);

    for ($i = 1; $i <= $max_position; $i++) {
        if (!isset($occupied_positions_map[$i])) {
            $next_free_position = $i;
            break;
        }
    }

    if ($next_free_position <= $max_position) {
        return ['success' => true, 'position' => sprintf('%04d', $next_free_position)];
    } else {
        return ['success' => false, 'error' => "Todas las posiciones están ocupadas para este teclado."];
    }
}

// --- Obtener sectores existentes para el select ---
$stmt_sectores = $conn->prepare("SELECT id, nombre FROM sectores ORDER BY nombre");
if ($stmt_sectores->execute()) {
    $result_sectores = $stmt_sectores->get_result();
    while ($row = $result_sectores->fetch_assoc()) {
        $sectores_existentes[] = $row;
    }
} else {
    $mensaje .= "<div class='mensaje error'>Error al cargar sectores: " . $stmt_sectores->error . "</div>";
}
$stmt_sectores->close();

// --- Obtener teclados existentes para los checkboxes ---
$stmt_teclados = $conn->prepare("SELECT id, nombre FROM teclados ORDER BY nombre");
if ($stmt_teclados->execute()) {
    $result_teclados = $stmt_teclados->get_result();
    while ($row = $result_teclados->fetch_assoc()) {
        $teclados_existentes[] = $row;
    }
} else {
    $mensaje .= "<div class='mensaje error'>Error al cargar teclados: " . $stmt_teclados->error . "</div>";
}
$stmt_teclados->close();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener y limpiar los datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $legajo_matricula = trim($_POST['legajo_matricula'] ?? '');
    $sector_seleccionado_id = $_POST['sector_id'] ?? '';
    $nuevo_sector_nombre = trim($_POST['nuevo_sector_nombre'] ?? '');
    $teclados_seleccionados = $_POST['teclados_asignar'] ?? []; // Array de IDs de teclados seleccionados
    $numero_tarjeta = trim($_POST['numero_tarjeta'] ?? ''); // Número de tarjeta (aplicable a todas las asignaciones)
    $numero_llavero = trim($_POST['numero_llavero'] ?? ''); // Número de llavero (aplicable a todas las asignaciones)

    $errores_validacion = [];

    // Validaciones básicas de empleado
    if (empty($nombre)) {
        $errores_validacion[] = "El Nombre del empleado no puede estar vacío.";
    }
    if (empty($apellido)) {
        $errores_validacion[] = "El Apellido del empleado no puede estar vacío.";
    }
    if (empty($legajo_matricula)) {
        $errores_validacion[] = "El Legajo/Matrícula del empleado no puede estar vacío.";
    }

    // Lógica para el sector
    $id_sector_final = null;
    if ($sector_seleccionado_id === 'new_sector') {
        if (empty($nuevo_sector_nombre)) {
            $errores_validacion[] = "Debe especificar un nombre para el nuevo sector.";
        } else {
            // Verificar si el nuevo sector ya existe
            $stmt_check_sector = $conn->prepare("SELECT id FROM sectores WHERE nombre = ?");
            if ($stmt_check_sector === false) {
                $errores_validacion[] = "Error al preparar la verificación del nuevo sector: " . $conn->error;
            } else {
                $stmt_check_sector->bind_param("s", $nuevo_sector_nombre);
                $stmt_check_sector->execute();
                $stmt_check_sector->store_result();
                if ($stmt_check_sector->num_rows > 0) {
                    $stmt_check_sector->bind_result($id_sector_final);
                    $stmt_check_sector->fetch(); // Obtener el ID si ya existe
                    $mensaje_sector_existente = "El sector '" . htmlspecialchars($nuevo_sector_nombre) . "' ya existe y se utilizará.";
                } else {
                    // Insertar nuevo sector
                    $stmt_insert_sector = $conn->prepare("INSERT INTO sectores (nombre) VALUES (?)");
                    if ($stmt_insert_sector === false) {
                        $errores_validacion[] = "Error al preparar la inserción del nuevo sector: " . $conn->error;
                    } else {
                        $stmt_insert_sector->bind_param("s", $nuevo_sector_nombre);
                        if ($stmt_insert_sector->execute()) {
                            $id_sector_final = $conn->insert_id;
                        } else {
                            $errores_validacion[] = "Error al insertar el nuevo sector: " . $stmt_insert_sector->error;
                        }
                        $stmt_insert_sector->close();
                    }
                }
                $stmt_check_sector->close();
            }
        }
    } elseif (!empty($sector_seleccionado_id)) {
        $id_sector_final = (int)$sector_seleccionado_id;
    } else {
        $errores_validacion[] = "Debe seleccionar un sector o crear uno nuevo.";
    }

    // Si hay errores de validación, mostrarlos
    if (!empty($errores_validacion)) {
        $mensaje = "<div class='mensaje error'>" . implode("<br>", $errores_validacion) . "</div>";
    } else {
        // Iniciar transacción para asegurar que empleado y asignaciones se guarden juntos
        $conn->begin_transaction();
        $todo_ok = true;

        try {
            // 1. Insertar el nuevo empleado
            $stmt_empleado = $conn->prepare("INSERT INTO empleados (nombre, apellido, legajo_matricula, sector_id) VALUES (?, ?, ?, ?)");
            if ($stmt_empleado === false) {
                throw new Exception("Error al preparar la inserción del empleado: " . $conn->error);
            }
            $stmt_empleado->bind_param("sssi", $nombre, $apellido, $legajo_matricula, $id_sector_final);

            if (!$stmt_empleado->execute()) {
                throw new Exception("Error al insertar el empleado: " . $stmt_empleado->error);
            }
            $empleado_id = $conn->insert_id; // ID del empleado recién insertado
            $stmt_empleado->close();

            $mensajes_asignacion = [];

            // 2. Procesar las asignaciones de teclado si hay teclados seleccionados
            if (!empty($teclados_seleccionados)) {
                $stmt_asignacion = $conn->prepare("INSERT INTO asignaciones (empleado_id, teclado_id, posicion, numero_tarjeta, numero_llavero) VALUES (?, ?, ?, ?, ?)");
                if ($stmt_asignacion === false) {
                    throw new Exception("Error al preparar la inserción de asignaciones: " . $conn->error);
                }

                foreach ($teclados_seleccionados as $teclado_id_actual) {
                    $resultado_posicion = obtenerSiguientePosicionLibre($conn, (int)$teclado_id_actual);

                    if ($resultado_posicion['success']) {
                        $posicion_libre = $resultado_posicion['position'];

                        $stmt_asignacion->bind_param("iisss", $empleado_id, $teclado_id_actual, $posicion_libre, $numero_tarjeta, $numero_llavero);
                        if (!$stmt_asignacion->execute()) {
                            $teclado_nombre = "ID " . $teclado_id_actual; // Fallback si no encontramos el nombre
                            foreach ($teclados_existentes as $t) {
                                if ($t['id'] == $teclado_id_actual) {
                                    $teclado_nombre = $t['nombre'];
                                    break;
                                }
                            }
                            $mensajes_asignacion[] = "Error al asignar al teclado '{$teclado_nombre}' en la posición '{$posicion_libre}': " . $stmt_asignacion->error;
                            $todo_ok = false; // Marcar que hubo un problema
                        } else {
                             $teclado_nombre = "ID " . $teclado_id_actual; // Fallback si no encontramos el nombre
                             foreach ($teclados_existentes as $t) {
                                if ($t['id'] == $teclado_id_actual) {
                                    $teclado_nombre = $t['nombre'];
                                    break;
                                }
                            }
                            $mensajes_asignacion[] = "Asignado al teclado '{$teclado_nombre}' en la posición '{$posicion_libre}'.";
                        }
                    } else {
                        $teclado_nombre = "ID " . $teclado_id_actual;
                        foreach ($teclados_existentes as $t) {
                            if ($t['id'] == $teclado_id_actual) {
                                $teclado_nombre = $t['nombre'];
                                break;
                            }
                        }
                        $mensajes_asignacion[] = "No se pudo obtener posición libre para el teclado '{$teclado_nombre}': " . $resultado_posicion['error'];
                        $todo_ok = false; // Marcar que hubo un problema
                    }
                }
                $stmt_asignacion->close();
            } else {
                $mensajes_asignacion[] = "No se seleccionó ningún teclado para asignar.";
            }

            // Si todo fue bien, confirmar la transacción
            if ($todo_ok) {
                $conn->commit();
                $mensaje = "<div class='mensaje exito'>Empleado agregado y asignaciones procesadas correctamente.<br>" . implode("<br>", $mensajes_asignacion) . "</div>";
                // Limpiar los campos del formulario después del éxito (excepto el mensaje)
                $_POST = [];
            } else {
                // Si hubo algún problema en las asignaciones, hacer rollback
                $conn->rollback();
                $mensaje = "<div class='mensaje error'>Empleado NO agregado o hubo errores en la asignación:<br>" . implode("<br>", $mensajes_asignacion) . "</div>";
                // No limpiar $_POST para que el usuario pueda corregir y reintentar
            }

        } catch (Exception $e) {
            $conn->rollback(); // Revertir cambios si hay una excepción
            $mensaje = "<div class='mensaje error'>Error fatal en la transacción: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Nuevo Empleado</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_agregar_empleado.css">
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
            <h1>Agregar Nuevo Empleado</h1>
        </header>

        <?= $mensaje ?>

        <div class="form-container">
            <form action="agregar_empleado.php" method="POST" class="modern-form">
                <div class="form-group">
                    <label for="nombre"><i class="fas fa-user"></i> Nombre del Empleado:</label>
                    <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" placeholder="Ej: Juan">
                </div>

                <div class="form-group">
                    <label for="apellido"><i class="fas fa-user"></i> Apellido del Empleado:</label>
                    <input type="text" id="apellido" name="apellido" required value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>" placeholder="Ej: Pérez">
                </div>

                <div class="form-group">
                    <label for="legajo_matricula"><i class="fas fa-id-badge"></i> Legajo/Matrícula:</label>
                    <input type="text" id="legajo_matricula" name="legajo_matricula" required value="<?= htmlspecialchars($_POST['legajo_matricula'] ?? '') ?>" placeholder="Ej: 12345">
                </div>

                <div class="form-group">
                    <label for="sector_id"><i class="fas fa-building"></i> Sector:</label>
                    <select id="sector_id" name="sector_id" required>
                        <option value="">Seleccione un sector</option>
                        <?php foreach ($sectores_existentes as $sector): ?>
                            <option value="<?= htmlspecialchars($sector['id']) ?>"
                                <?= (isset($_POST['sector_id']) && $_POST['sector_id'] == $sector['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sector['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="new_sector"
                            <?= (isset($_POST['sector_id']) && $_POST['sector_id'] == 'new_sector') ? 'selected' : '' ?>>
                            🌟 Nuevo Sector
                        </option>
                    </select>
                </div>
                <div class="form-group" id="nuevo_sector_input_group" style="display:none;">
                    <label for="nuevo_sector_nombre"><i class="fas fa-plus-circle"></i> Nombre del Nuevo Sector:</label>
                    <input type="text" id="nuevo_sector_nombre" name="nuevo_sector_nombre" value="<?= htmlspecialchars($_POST['nuevo_sector_nombre'] ?? '') ?>" placeholder="Ej: I+D">
                </div>

                <hr>

                <h3>Asignación a Teclados</h3>
                <p class="form-help">Seleccione uno o más teclados donde desea asignar a este empleado. Se asignará la próxima posición libre automáticamente.</p>

                <?php if (!empty($teclados_existentes)): ?>
                <div class="form-group teclado-selection-group">
                    <?php foreach ($teclados_existentes as $teclado): ?>
                        <label>
                            <input type="checkbox" name="teclados_asignar[]" value="<?= htmlspecialchars($teclado['id']) ?>"
                                <?= (isset($_POST['teclados_asignar']) && in_array($teclado['id'], $_POST['teclados_asignar'])) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($teclado['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="mensaje info">No hay teclados registrados. Por favor, <a href="agregar_teclado.php">agregue uno primero</a>.</div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="numero_tarjeta"><i class="fas fa-credit-card"></i> Número de Tarjeta (Opcional):</label>
                    <input type="text" id="numero_tarjeta" name="numero_tarjeta" value="<?= htmlspecialchars($_POST['numero_tarjeta'] ?? '') ?>" placeholder="Ej: 12345678">
                    <small class="form-help">Este número se asociará a todas las asignaciones de teclado.</small>
                </div>

                <div class="form-group">
                    <label for="numero_llavero"><i class="fas fa-keychain"></i> Número de Llavero (Opcional):</label>
                    <input type="text" id="numero_llavero" name="numero_llavero" value="<?= htmlspecialchars($_POST['numero_llavero'] ?? '') ?>" placeholder="Ej: A-001">
                    <small class="form-help">Este número se asociará a todas las asignaciones de teclado.</small>
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-user-plus"></i> Agregar Empleado y Asignar</button>
            </form>
        </div>
    </div>

    <script>
        // Lógica JavaScript para mostrar/ocultar el campo "Nuevo Sector"
        document.addEventListener('DOMContentLoaded', function() {
            const sectorSelect = document.getElementById('sector_id');
            const nuevoSectorInputGroup = document.getElementById('nuevo_sector_input_group');
            const nuevoSectorNombreInput = document.getElementById('nuevo_sector_nombre');

            function toggleNuevoSectorInput() {
                if (sectorSelect.value === 'new_sector') {
                    nuevoSectorInputGroup.style.display = 'block';
                    nuevoSectorNombreInput.setAttribute('required', 'required');
                } else {
                    nuevoSectorInputGroup.style.display = 'none';
                    nuevoSectorNombreInput.removeAttribute('required');
                    // Limpiar el campo si se oculta para evitar que se envíe con un valor no deseado
                    if (sectorSelect.value !== 'new_sector') { // Solo limpiar si no se selecciona "Nuevo Sector"
                        nuevoSectorNombreInput.value = '';
                    }
                }
            }

            // Llamar al cargar la página para establecer el estado inicial
            toggleNuevoSectorInput();

            // Añadir el listener para cuando cambia la selección
            sectorSelect.addEventListener('change', toggleNuevoSectorInput);
        });
    </script>
</body>
</html>