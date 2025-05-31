<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar y limpiar el nombre del usuario
    $nombre_usuario = trim($_POST['nombre_usuario']);

    if (!empty($nombre_usuario)) {
        // Verificar si el usuario ya existe para evitar duplicados
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
        $stmt_check->bind_param("s", $nombre_usuario);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensaje = "<div class='mensaje error'>Error: El usuario '" . htmlspecialchars($nombre_usuario) . "' ya existe.</div>";
        } else {
            // Insertar el nuevo usuario en la base de datos
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre) VALUES (?)");
            $stmt_insert->bind_param("s", $nombre_usuario);

            if ($stmt_insert->execute()) {
                $mensaje = "<div class='mensaje exito'>Usuario '" . htmlspecialchars($nombre_usuario) . "' agregado correctamente.</div>";
                // Opcional: Redirigir a alguna página después de agregar
                // header("Location: dashboard_teclados.php");
                // exit();
            } else {
                $mensaje = "<div class='mensaje error'>Error al agregar el usuario: " . $stmt_insert->error . "</div>";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    } else {
        $mensaje = "<div class='mensaje error'>Error: El nombre del usuario no puede estar vacío.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Nuevo Usuario</title>
    <link rel="stylesheet" href="css/estilo_crear_usuario.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-key"></i> Gestión Teclados</h2>
        </div>
        <div class="sidebar-nav">
            <ul>
                <li><a href="dashboard_teclados.php"><i class="fas fa-desktop"></i> Dashboard Teclados</a></li>
                <li><a href="agregar_teclado.php"><i class="fas fa-plus-square"></i> Agregar Teclado</a></li>
                <li><a href="#" onclick="openModal('modalNuevoUsuario'); return false;"><i class="fas fa-user-plus"></i> Agregar Usuario</a></li>
                <li><a href="asignar_posicion.php">🔗 Asignar Posición</a></li>
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
            <h1>Crear Nuevo Usuario</h1>
        </header>

        <div class="form-container">
            <?= $mensaje ?>
            <form action="crear_usuario.php" method="POST" class="modern-form">
                <div class="form-group">
                    <label for="nombre_usuario"><i class="fas fa-user"></i> Nombre del Usuario:</label>
                    <input type="text" id="nombre_usuario" name="nombre_usuario" required>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Guardar Usuario</button>
            </form>
        </div>
    </div>
</body>
</html>