<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

$mensaje = ""; // Para mostrar mensajes de éxito o error (aunque en este script se redirige)

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $asignacion_id = (int)$_GET['id'];

    // Preparar la consulta para eliminar la asignación
    $stmt = $conn->prepare("DELETE FROM asignaciones WHERE id = ?");
    $stmt->bind_param("i", $asignacion_id);

    if ($stmt->execute()) {
        // Redirigir de vuelta al dashboard con un mensaje de éxito
        header("Location: dashboard_teclados.php?mensaje=Asignación eliminada correctamente.");
        exit();
    } else {
        // Redirigir con un mensaje de error
        header("Location: dashboard_teclados.php?error=Error al eliminar la asignación: " . urlencode($stmt->error));
        exit();
    }
    $stmt->close();
} else {
    // Redirigir si no se proporcionó un ID válido
    header("Location: dashboard_teclados.php?error=ID de asignación no especificado o inválido.");
    exit();
}

$conn->close();
?>