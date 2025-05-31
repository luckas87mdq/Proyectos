<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

// Función para limpiar y validar datos
function limpiar_dato($dato) {
    // Asegura que $dato no sea null antes de pasarlo a trim y htmlspecialchars
    return trim(htmlspecialchars($dato ?? ''));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_teclado_csv = limpiar_dato($_POST['nombre_teclado_csv']);

    // Validar que el nombre del teclado no esté vacío
    if (empty($nombre_teclado_csv)) {
        header("Location: cargar_asignaciones_csv.php?status=error&msg=" . urlencode("El nombre del teclado no puede estar vacío."));
        exit();
    }

    // Validar que se haya subido un archivo
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
        header("Location: cargar_asignaciones_csv.php?status=error&msg=" . urlencode("No se pudo subir el archivo o no se seleccionó ninguno."));
        exit();
    }

    $file_tmp_path = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    
    // Validar tipo de archivo de forma más robusta
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp_path);
    finfo_close($finfo);

    // Permitir text/csv y application/vnd.ms-excel (común para CSV en Windows)
    // También verificar la extensión como respaldo
    if (!in_array($mime_type, ['text/csv', 'application/vnd.ms-excel']) && pathinfo($file_name, PATHINFO_EXTENSION) != 'csv') {
        header("Location: cargar_asignaciones_csv.php?status=error&msg=" . urlencode("Tipo de archivo no permitido. Solo se aceptan archivos CSV."));
        exit();
    }

    // --- Iniciar Transacción ---
    // Esto es importante para asegurar la integridad de los datos.
    // Si algo falla, se revierte todo.
    $conn->begin_transaction();
    $errores_csv = [];

    try {
        // 1. Obtener o insertar el ID del Teclado
        $teclado_id = null;
        $stmt_check_teclado = $conn->prepare("SELECT id FROM teclados WHERE nombre = ?");
        $stmt_check_teclado->bind_param("s", $nombre_teclado_csv);
        $stmt_check_teclado->execute();
        $result_check_teclado = $stmt_check_teclado->get_result();

        if ($result_check_teclado->num_rows > 0) {
            $row_teclado = $result_check_teclado->fetch_assoc();
            $teclado_id = $row_teclado['id'];
        } else {
            // El teclado no existe, lo insertamos
            $stmt_insert_teclado = $conn->prepare("INSERT INTO teclados (nombre) VALUES (?)");
            $stmt_insert_teclado->bind_param("s", $nombre_teclado_csv);
            if ($stmt_insert_teclado->execute()) {
                $teclado_id = $conn->insert_id;
            } else {
                throw new Exception("Error al insertar el teclado '" . htmlspecialchars($nombre_teclado_csv) . "': " . $stmt_insert_teclado->error);
            }
        }

        // 2. Procesar el archivo CSV
        // Configurar el locale para manejar caracteres especiales si es necesario (ej. Windows-1252)
        // setlocale(LC_ALL, 'es_ES.UTF-8'); // O 'en_US.UTF-8' si tu sistema es otro.
        // Si el CSV está en un encoding diferente (ej. ISO-8859-1), necesitarás iconv
        // $csv_content = file_get_contents($file_tmp_path);
        // $csv_content_utf8 = iconv("ISO-8859-1", "UTF-8//IGNORE", $csv_content);
        // $handle = fopen("php://temp", "r+");
        // fwrite($handle, $csv_content_utf8);
        // rewind($handle);


        if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
            fgetcsv($handle); // Saltar la primera fila (encabezados)

            $linea = 1;
            while (($data = fgetcsv($handle)) !== FALSE) {
                $linea++;
                // Validar que la fila tenga al menos 4 columnas
                if (count($data) < 4) {
                    $errores_csv[] = "Fila " . $linea . ": Formato incorrecto. Se esperaban 4 columnas (posicion, empleado, numero_tarjeta, numero_llavero), se encontraron " . count($data) . ".";
                    continue;
                }

                $posicion = limpiar_dato($data[0]);
                $empleado_nombre = limpiar_dato($data[1]);
                $numero_tarjeta = limpiar_dato($data[2]);
                $numero_llavero = limpiar_dato($data[3]);

                // Validaciones de datos de la fila
                if (empty($posicion) || !is_numeric($posicion) || $posicion <= 0) {
                    $errores_csv[] = "Fila " . $linea . ": La posición debe ser un número positivo. Valor: '" . htmlspecialchars($data[0]) . "'";
                    continue;
                }
                if (empty($empleado_nombre)) {
                    $errores_csv[] = "Fila " . $linea . ": El nombre del empleado no puede estar vacío.";
                    continue;
                }

                // Obtener o insertar el ID del Usuario
                $usuario_id = null;
                $stmt_check_usuario = $conn->prepare("SELECT id FROM empleados WHERE nombre = ?");
                $stmt_check_usuario->bind_param("s", $empleado_nombre);
                $stmt_check_usuario->execute();
                $result_check_usuario = $stmt_check_usuario->get_result();

                if ($result_check_usuario->num_rows > 0) {
                    $row_usuario = $result_check_usuario->fetch_assoc();
                    $usuario_id = $row_usuario['id'];
                } else {
                    // El usuario no existe, lo insertamos
                    $stmt_insert_usuario = $conn->prepare("INSERT INTO empleados (nombre) VALUES (?)");
                    $stmt_insert_usuario->bind_param("s", $empleado_nombre);
                    if ($stmt_insert_usuario->execute()) {
                        $usuario_id = $conn->insert_id;
                    } else {
                        $errores_csv[] = "Fila " . $linea . ": Error al insertar el usuario '" . htmlspecialchars($empleado_nombre) . "': " . $stmt_insert_usuario->error;
                        continue;
                    }
                }

                // Insertar o Actualizar Asignación
                // Verificamos si la asignación (teclado_id, posicion) ya existe
                $stmt_check_asignacion = $conn->prepare("SELECT id FROM asignaciones WHERE teclado_id = ? AND posicion = ?");
                $stmt_check_asignacion->bind_param("ii", $teclado_id, $posicion);
                $stmt_check_asignacion->execute();
                $result_check_asignacion = $stmt_check_asignacion->get_result();

                if ($result_check_asignacion->num_rows > 0) {
                    // La asignación ya existe, la actualizamos
                    $row_asignacion = $result_check_asignacion->fetch_assoc();
                    $asignacion_existente_id = $row_asignacion['id'];

                    $stmt_update_asignacion = $conn->prepare(
                        "UPDATE asignaciones SET usuario_id = ?, numero_tarjeta = ?, numero_llavero = ? WHERE id = ?"
                    );
                    // 's' para string, 'i' para integer. Aquí tenemos usuario_id (int), numero_tarjeta (string), numero_llavero (string), id (int)
                    $stmt_update_asignacion->bind_param("issi", $usuario_id, $numero_tarjeta, $numero_llavero, $asignacion_existente_id);
                    if (!$stmt_update_asignacion->execute()) {
                        $errores_csv[] = "Fila " . $linea . ": Error al actualizar la asignación para posición " . $posicion . " en el teclado '" . htmlspecialchars($nombre_teclado_csv) . "': " . $stmt_update_asignacion->error;
                    }
                } else {
                    // La asignación no existe, la insertamos
                    $stmt_insert_asignacion = $conn->prepare(
                        "INSERT INTO asignaciones (teclado_id, usuario_id, posicion, numero_tarjeta, numero_llavero) VALUES (?, ?, ?, ?, ?)"
                    );
                    // 'i' para integer, 's' para string
                    $stmt_insert_asignacion->bind_param("iisss", $teclado_id, $usuario_id, $posicion, $numero_tarjeta, $numero_llavero);
                    if (!$stmt_insert_asignacion->execute()) {
                        $errores_csv[] = "Fila " . $linea . ": Error al insertar la asignación para posición " . $posicion . " en el teclado '" . htmlspecialchars($nombre_teclado_csv) . "': " . $stmt_insert_asignacion->error;
                    }
                }
            }
            fclose($handle);
        } else {
            throw new Exception("No se pudo abrir el archivo CSV. Verifique los permisos.");
        }

        if (empty($errores_csv)) {
            $conn->commit(); // Confirmar la transacción
            header("Location: cargar_asignaciones_csv.php?status=success");
        } else {
            $conn->rollback(); // Revertir la transacción si hay errores
            $error_msg_full = "<ul>";
            foreach ($errores_csv as $error) {
                $error_msg_full .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $error_msg_full .= "</ul>";
            header("Location: cargar_asignaciones_csv.php?status=error&msg=" . urlencode("Hubo errores al procesar el CSV:<br>" . $error_msg_full));
        }
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Revertir la transacción ante cualquier excepción
        header("Location: cargar_asignaciones_csv.php?status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Asignaciones desde CSV</title>
    <link rel="stylesheet" href="css/estilo_general.css">
    <link rel="stylesheet" href="css/estilo_cargar_asignaciones_csv.css">
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
                <li><a href="asignar_posicion.php">🔗 Asignar Posición</a></li>
                
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
            <h1>Cargar Asignaciones desde CSV</h1>
        </header>

        <div class="form-container">
            <?php
            // Aquí se mostrarán los mensajes de éxito o error después de procesar el CSV
            $mensaje = "";
            if (isset($_GET['status'])) {
                if ($_GET['status'] == 'success') {
                    $mensaje = "<div class='mensaje exito'>¡Archivo CSV procesado exitosamente!</div>";
                } elseif ($_GET['status'] == 'error' && isset($_GET['msg'])) {
                    // Decodificar el mensaje URL-encoded para mostrarlo correctamente
                    $mensaje = "<div class='mensaje error'>Error: " . urldecode($_GET['msg']) . "</div>";
                }
            }
            echo $mensaje;
            ?>

            <form action="cargar_asignaciones_csv.php" method="POST" enctype="multipart/form-data" class="modern-form">
                <div class="form-group">
                    <label for="nombre_teclado_csv"><i class="fas fa-keyboard"></i> Nombre del Teclado para este CSV:</label>
                    <input type="text" id="nombre_teclado_csv" name="nombre_teclado_csv" required placeholder="Ej: Teclado Entrada Principal">
                </div>
                <div class="form-group">
                    <label for="csv_file"><i class="fas fa-file-upload"></i> Seleccionar Archivo CSV:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-upload"></i> Cargar CSV</button>
            </form>
        </div>
    </div>
</body>
</html>