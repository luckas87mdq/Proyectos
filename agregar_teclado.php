<?php
// agregar_teclado.php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $ubicacion = trim($_POST['ubicacion']); // Captura la ubicación

    // Validaciones
    if (empty($nombre)) {
        $mensaje = "<div class='mensaje error'>El nombre del teclado no puede estar vacío.</div>";
    } elseif (empty($ubicacion)) { // Nueva validación para la ubicación
        $mensaje = "<div class='mensaje error'>La ubicación del teclado no puede estar vacía.</div>";
    } else {
        // Verificar si el nombre del teclado ya existe (opcional, pero buena práctica)
        $stmt_check = $conn->prepare("SELECT id FROM teclados WHERE nombre = ?");
        if ($stmt_check === false) {
            $mensaje = "<div class='mensaje error'>Error al preparar la verificación del nombre: " . $conn->error . "</div>";
        } else {
            $stmt_check->bind_param("s", $nombre);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $mensaje = "<div class='mensaje error'>Ya existe un teclado con ese nombre. Por favor, elige otro.</div>";
            } else {
                // Insertar el nuevo teclado (incluyendo la ubicación)
                $stmt_insert = $conn->prepare("INSERT INTO teclados (nombre, ubicacion) VALUES (?, ?)");
                if ($stmt_insert === false) {
                    $mensaje = "<div class='mensaje error'>Error al preparar la inserción: " . $conn->error . "</div>";
                } else {
                    $stmt_insert->bind_param("ss", $nombre, $ubicacion); // "ss" para dos strings

                    if ($stmt_insert->execute()) {
                        $mensaje = "<div class='mensaje exito'>Teclado '{$nombre}' agregado exitosamente.</div>";
                        // Limpiar el formulario después del éxito
                        $nombre = "";
                        $ubicacion = ""; // Limpia también la ubicación
                    } else {
                        $mensaje = "<div class='mensaje error'>Error al agregar el teclado: " . $stmt_insert->error . "</div>";
                    }
                    $stmt_insert->close();
                }
            }
            $stmt_check->close();
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
    <title>Agregar Teclado</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_agregar_teclado.css">
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
                <li><a href="gestion_teclados.php"><i class="fas fa-keyboard"></i> Gestión Teclados</a></li>
                <li class="active"><a href="agregar_teclado.php"><i class="fas fa-plus-square"></i> Agregar Teclado</a></li>
                <li><a href="agregar_empleado.php"><i class="fas fa-user-plus"></i> Agregar Empleado</a></li>
                <li><a href="cargar_asignaciones_csv.php"><i class="fas fa-file-csv"></i> Cargar CSV Asignaciones</a></li>
                <li><a href="crear_usuario.php"><i class="fas fa-user-shield"></i> Crear Usuario Admin</a></li>
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
            <h1><i class="fas fa-plus-circle"></i> Agregar Nuevo Teclado</h1>
        </header>

        <?= $mensaje ?>

        <div class="form-container">
            <form action="agregar_teclado.php" method="POST">
                <div class="input-group">
                    <label for="nombre"><i class="fas fa-keyboard"></i> Nombre del Teclado:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <label for="ubicacion"><i class="fas fa-map-marker-alt"></i> Ubicación del Teclado:</label>
                    <input type="text" id="ubicacion" name="ubicacion" value="<?= htmlspecialchars($ubicacion ?? '') ?>" required>
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-plus"></i> Agregar Teclado</button>
            </form>
        </div>
    </div>
</body>
</html>