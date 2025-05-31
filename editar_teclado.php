<?php
// editar_teclado.php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error
$teclado_data = null; // Para almacenar los datos del teclado a editar

// --- Lógica para cargar los datos del teclado existente ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $teclado_id = $_GET['id'];

    $stmt = $conn->prepare("SELECT id, nombre, ubicacion FROM teclados WHERE id = ?");
    if ($stmt === false) {
        die("Error al preparar la consulta de selección: " . $conn->error);
    }
    $stmt->bind_param("i", $teclado_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teclado_data = $result->fetch_assoc();
    } else {
        $mensaje = "<div class='mensaje error'>Teclado no encontrado.</div>";
        // Si el teclado no existe, redirigir a la gestión de teclados
        header("Location: gestion_teclados.php?error=" . urlencode("Teclado no encontrado para editar."));
        exit();
    }
    $stmt->close();

} else {
    // Si no se proporcionó un ID válido
    $mensaje = "<div class='mensaje error'>ID de teclado no especificado para editar.</div>";
    header("Location: gestion_teclados.php?error=" . urlencode("ID de teclado no especificado para editar."));
    exit();
}

// --- Lógica para procesar la actualización del teclado ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_a_actualizar = $_POST['teclado_id'];
    $nombre = trim($_POST['nombre']);
    $ubicacion = trim($_POST['ubicacion']);

    // Validaciones básicas
    if (empty($nombre) || empty($ubicacion)) {
        $mensaje = "<div class='mensaje error'>El nombre y la ubicación no pueden estar vacíos.</div>";
    } else {
        // Preparar la consulta de actualización
        $stmt_update = $conn->prepare("UPDATE teclados SET nombre = ?, ubicacion = ? WHERE id = ?");
        if ($stmt_update === false) {
            $mensaje = "<div class='mensaje error'>Error al preparar la consulta de actualización: " . $conn->error . "</div>";
        } else {
            $stmt_update->bind_param("ssi", $nombre, $ubicacion, $id_a_actualizar);

            if ($stmt_update->execute()) {
                // Éxito en la actualización
                $mensaje_exito = "Teclado '{$nombre}' actualizado exitosamente.";
                header("Location: gestion_teclados.php?mensaje=" . urlencode($mensaje_exito));
                exit();
            } else {
                // Error en la ejecución de la consulta
                $mensaje = "<div class='mensaje error'>Error al actualizar el teclado: " . $stmt_update->error . "</div>";
            }
            $stmt_update->close();
        }
    }
    // Si hubo un error, recargar los datos para que el formulario muestre los últimos valores (si se enviaron)
    // Esto es útil si el usuario corrige errores después de un intento fallido
    $teclado_data['nombre'] = $nombre;
    $teclado_data['ubicacion'] = $ubicacion;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Teclado</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_editar_teclado.css">
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
            <h1><i class="fas fa-edit"></i> Editar Teclado</h1>
        </header>

        <?= $mensaje ?>

        <?php if ($teclado_data): ?>
            <div class="form-container">
                <h2>Editar Teclado ID: <?= htmlspecialchars($teclado_data['id']) ?></h2>
                <form action="editar_teclado.php?id=<?= htmlspecialchars($teclado_data['id']) ?>" method="POST">
                    <input type="hidden" name="teclado_id" value="<?= htmlspecialchars($teclado_data['id']) ?>">

                    <div class="input-group">
                        <label for="nombre"><i class="fas fa-keyboard"></i> Nombre del Teclado:</label>
                        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($teclado_data['nombre']) ?>" required>
                    </div>

                    <div class="input-group">
                        <label for="ubicacion"><i class="fas fa-map-marker-alt"></i> Ubicación:</label>
                        <input type="text" id="ubicacion" name="ubicacion" value="<?= htmlspecialchars($teclado_data['ubicacion']) ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-button"><i class="fas fa-save"></i> Guardar Cambios</button>
                        <a href="gestion_teclados.php" class="cancel-button"><i class="fas fa-times-circle"></i> Cancelar</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <p class="no-data">No se pudo cargar la información del teclado para editar.</p>
        <?php endif; ?>
    </div>
</body>
</html>