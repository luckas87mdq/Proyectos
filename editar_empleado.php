<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error
$empleado = null; // Variable para almacenar los datos del empleado a editar
$sectores_existentes = []; // Para poblar el select del sector

// --- Lógica para cargar sectores existentes para el select ---
$stmt_sectores = $conn->prepare("SELECT id, nombre FROM sectores ORDER BY nombre");
if ($stmt_sectores->execute()) {
    $result_sectores = $stmt_sectores->get_result();
    while ($row = $result_sectores->fetch_assoc()) {
        $sectores_existentes[] = $row;
    }
} else {
    $mensaje = "<div class='mensaje error'>Error al cargar sectores: " . $stmt_sectores->error . "</div>";
}
$stmt_sectores->close();


// 1. Obtener el ID del empleado de la URL y cargar sus datos
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $empleado_id = (int)$_GET['id'];

    $stmt_empleado = $conn->prepare("SELECT id, nombre, apellido, legajo_matricula, sector_id FROM empleados WHERE id = ?");
    $stmt_empleado->bind_param("i", $empleado_id);
    if ($stmt_empleado->execute()) {
        $result_empleado = $stmt_empleado->get_result();
        if ($result_empleado->num_rows == 1) {
            $empleado = $result_empleado->fetch_assoc();
        } else {
            $mensaje = "<div class='mensaje error'>Empleado no encontrado.</div>";
        }
    } else {
        $mensaje = "<div class='mensaje error'>Error al cargar datos del empleado: " . $stmt_empleado->error . "</div>";
    }
    $stmt_empleado->close();
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Solo mostrar este error si no es un POST (es decir, si se accede sin ID en la URL inicialmente)
    $mensaje = "<div class='mensaje error'>ID de empleado no especificado.</div>";
}


// 2. Procesar el formulario cuando se envía (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['empleado_id'])) {
    $empleado_id = (int)$_POST['empleado_id']; // Asegúrate de que el ID del empleado se pase en el formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $legajo_matricula = trim($_POST['legajo_matricula'] ?? '');
    $sector_seleccionado_id = $_POST['sector_id'] ?? '';
    $nuevo_sector_nombre = trim($_POST['nuevo_sector_nombre'] ?? '');

    // Re-cargar los datos del empleado por si la validación falla y se necesita mostrar el formulario con los datos originales
    // Esto es importante porque si no, el $empleado variable sería nula y el formulario aparecería vacío.
    $stmt_empleado_recharge = $conn->prepare("SELECT id, nombre, apellido, legajo_matricula, sector_id FROM empleados WHERE id = ?");
    $stmt_empleado_recharge->bind_param("i", $empleado_id);
    if ($stmt_empleado_recharge->execute()) {
        $result_empleado_recharge = $stmt_empleado_recharge->get_result();
        if ($result_empleado_recharge->num_rows == 1) {
            $empleado = $result_empleado_recharge->fetch_assoc();
        }
    }
    $stmt_empleado_recharge->close();


    // Validaciones básicas
    if (empty($nombre)) {
        $mensaje = "<div class='mensaje error'>Error: El Nombre del empleado no puede estar vacío.</div>";
    } elseif (empty($apellido)) {
        $mensaje = "<div class='mensaje error'>Error: El Apellido del empleado no puede estar vacío.</div>";
    } elseif (empty($legajo_matricula)) {
        $mensaje = "<div class='mensaje error'>Error: El Legajo/Matrícula del empleado no puede estar vacío.</div>";
    } elseif ($sector_seleccionado_id === 'new_sector' && empty($nuevo_sector_nombre)) {
        $mensaje = "<div class='mensaje error'>Error: Debes especificar el nombre del nuevo sector.</div>";
    } else {
        $conn->begin_transaction(); // Iniciar transacción

        try {
            $sector_id_final = $sector_seleccionado_id;

            // Si se seleccionó "Nuevo Sector", insertarlo primero
            if ($sector_seleccionado_id === 'new_sector') {
                // Verificar si el nuevo sector ya existe para evitar duplicados
                $stmt_check_sector = $conn->prepare("SELECT id FROM sectores WHERE nombre = ?");
                $stmt_check_sector->bind_param("s", $nuevo_sector_nombre);
                $stmt_check_sector->execute();
                $stmt_check_sector->store_result();

                if ($stmt_check_sector->num_rows > 0) {
                    $stmt_check_sector->bind_result($existing_sector_id);
                    $stmt_check_sector->fetch();
                    $sector_id_final = $existing_sector_id; // Usar el ID del sector existente
                    $mensaje .= "<div class='mensaje error'>Advertencia: El sector '" . htmlspecialchars($nuevo_sector_nombre) . "' ya existe y ha sido asignado al empleado.</div>";
                } else {
                    $stmt_insert_sector = $conn->prepare("INSERT INTO sectores (nombre) VALUES (?)");
                    if ($stmt_insert_sector === false) {
                        throw new Exception("Error al preparar la consulta de inserción de sector: " . $conn->error);
                    }
                    $stmt_insert_sector->bind_param("s", $nuevo_sector_nombre);
                    if (!$stmt_insert_sector->execute()) {
                        throw new Exception("Error al insertar el nuevo sector: " . $stmt_insert_sector->error);
                    }
                    $sector_id_final = $conn->insert_id; // Obtener el ID del nuevo sector
                    $stmt_insert_sector->close();
                }
                $stmt_check_sector->close();
            }

            // Actualizar el empleado
            $stmt_update_empleado = $conn->prepare("UPDATE empleados SET nombre = ?, apellido = ?, legajo_matricula = ?, sector_id = ? WHERE id = ?");
            if ($stmt_update_empleado === false) {
                throw new Exception("Error al preparar la consulta de actualización de empleado: " . $conn->error);
            }
            $stmt_update_empleado->bind_param("sssii", $nombre, $apellido, $legajo_matricula, $sector_id_final, $empleado_id);

            if (!$stmt_update_empleado->execute()) {
                throw new Exception("Error al actualizar el empleado: " . $stmt_update_empleado->error);
            }
            $stmt_update_empleado->close();

            $conn->commit(); // Confirmar la transacción
            $mensaje = "<div class='mensaje exito'>Empleado actualizado correctamente.</div>";
            // Actualizar $empleado con los nuevos datos para que se reflejen en el formulario
            $empleado['nombre'] = $nombre;
            $empleado['apellido'] = $apellido;
            $empleado['legajo_matricula'] = $legajo_matricula;
            $empleado['sector_id'] = $sector_id_final;

        } catch (Exception $e) {
            $conn->rollback(); // Revertir la transacción en caso de error
            $mensaje = "<div class='mensaje error'>Error en la transacción: " . $e->getMessage() . "</div>";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empleado - Sistema de Accesos</title>
    <link rel="stylesheet" href="css/estilo_general.css">
<link rel="stylesheet" href="css/estilo_editar_empleado.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-lock"></i> Sistema de Accesos</h2>
        </div>
        <div class="sidebar-nav">
            <ul>
                <li><a href="dashboard_teclados.php"><i class="fas fa-desktop"></i> Dashboard Teclados</a></li>
                <li><a href="gestion_empleados.php"><i class="fas fa-users"></i> Gestión de Empleados</a></li>
                <li><a href="agregar_teclado.php"><i class="fas fa-plus-square"></i> Agregar Teclado</a></li>
                <li><a href="asignar_posicion.php"><i class="fas fa-user-tag"></i> Asignar Posición</a></li>
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
            <h1><i class="fas fa-user-edit"></i> Editar Empleado</h1>
        </header>

        <div class="form-container">
            <?= $mensaje ?>

            <?php if ($empleado): ?>
            <form action="editar_empleado.php" method="POST" class="modern-form">
                <input type="hidden" name="empleado_id" value="<?= htmlspecialchars($empleado['id']) ?>">

                <div class="form-group">
                    <label for="nombre"><i class="fas fa-user"></i> Nombre:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($empleado['nombre'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="apellido"><i class="fas fa-user"></i> Apellido:</label>
                    <input type="text" id="apellido" name="apellido" value="<?= htmlspecialchars($empleado['apellido'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="legajo_matricula"><i class="fas fa-id-badge"></i> Legajo/Matrícula:</label>
                    <input type="text" id="legajo_matricula" name="legajo_matricula" value="<?= htmlspecialchars($empleado['legajo_matricula'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="sector_id"><i class="fas fa-building"></i> Sector:</label>
                    <select id="sector_id" name="sector_id" required>
                        <option value="">Seleccione un sector</option>
                        <?php foreach ($sectores_existentes as $sector): ?>
                            <option value="<?= htmlspecialchars($sector['id']) ?>"
                                <?= (isset($empleado['sector_id']) && $empleado['sector_id'] == $sector['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sector['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="new_sector" <?= (isset($_POST['sector_id']) && $_POST['sector_id'] === 'new_sector') ? 'selected' : '' ?>>+ Nuevo Sector</option>
                    </select>
                </div>

                <div class="form-group" id="nuevo_sector_input_group" style="display: none;">
                    <label for="nuevo_sector_nombre"><i class="fas fa-plus-circle"></i> Nombre del Nuevo Sector:</label>
                    <input type="text" id="nuevo_sector_nombre" name="nuevo_sector_nombre" value="<?= htmlspecialchars($_POST['nuevo_sector_nombre'] ?? '') ?>">
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Actualizar Empleado</button>
            </form>
            <?php else: ?>
                <p class="no-data">No se pudo cargar la información del empleado para editar.</p>
                <p><a href="gestion_empleados.php">Volver a Gestión de Empleados</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
                    nuevoSectorNombreInput.value = ''; // Limpiar el campo si se oculta
                }
            }

            // Llamar al cargar la página para establecer el estado inicial
            // Esto es crucial para si el formulario se recarga con un error de POST
            // o si el sector_id del empleado es 'new_sector' (aunque no debería serlo,
            // si el nuevo sector ya fue guardado, su ID será numérico).
            // Si el valor del select no es 'new_sector', entonces el campo estará oculto inicialmente.
            toggleNuevoSectorInput();

            // Añadir el listener para cuando cambia la selección
            sectorSelect.addEventListener('change', toggleNuevoSectorInput);

            // Si el formulario se envió con "new_sector" seleccionado y hubo un error de validación,
            // el valor del select se mantendrá como "new_sector", lo que hará que el input se muestre.
            // Para asegurar, si el campo de nuevo sector ya tiene un valor (ej. por un error de POST),
            // y la opción "new_sector" está seleccionada, también mostrarlo.
            if (sectorSelect.value === 'new_sector' && nuevoSectorNombreInput.value !== '') {
                 nuevoSectorInputGroup.style.display = 'block';
                 nuevoSectorNombreInput.setAttribute('required', 'required');
            }
        });
    </script>
</body>
</html>