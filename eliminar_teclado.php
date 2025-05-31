<?php
// eliminar_teclado.php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $teclado_id = $_GET['id'];

    // Iniciar una transacción para asegurar que ambas operaciones (eliminar asignaciones y teclado)
    // se completen o ninguna lo haga. Esto es crucial para la integridad de los datos.
    $conn->begin_transaction();

    try {
        // 1. Eliminar asignaciones relacionadas con este teclado
        $stmt_delete_asignaciones = $conn->prepare("DELETE FROM asignaciones WHERE teclado_id = ?");
        if ($stmt_delete_asignaciones === false) {
            throw new Exception("Error al preparar la consulta para eliminar asignaciones: " . $conn->error);
        }
        $stmt_delete_asignaciones->bind_param("i", $teclado_id);

        if (!$stmt_delete_asignaciones->execute()) {
            throw new Exception("Error al eliminar asignaciones del teclado: " . $stmt_delete_asignaciones->error);
        }
        $stmt_delete_asignaciones->close();

        // 2. Eliminar el teclado de la tabla 'teclados'
        $stmt_delete_teclado = $conn->prepare("DELETE FROM teclados WHERE id = ?");
        if ($stmt_delete_teclado === false) {
            throw new Exception("Error al preparar la consulta para eliminar el teclado: " . $conn->error);
        }
        $stmt_delete_teclado->bind_param("i", $teclado_id);

        if (!$stmt_delete_teclado->execute()) {
            throw new Exception("Error al eliminar el teclado: " . $stmt_delete_teclado->error);
        }
        $stmt_delete_teclado->close();

        // Si todo fue bien, confirmar la transacción
        $conn->commit();
        $mensaje = "Teclado y sus asignaciones eliminados exitosamente.";
        header("Location: gestion_teclados.php?mensaje=" . urlencode($mensaje));
        exit();

    } catch (Exception $e) {
        // Si algo sale mal, revertir la transacción
        $conn->rollback();
        $error_mensaje = "Error al eliminar el teclado: " . $e->getMessage();
        header("Location: gestion_teclados.php?error=" . urlencode($error_mensaje));
        exit();
    } finally {
        $conn->close();
    }

} else {
    // Si no se proporcionó un ID de teclado válido
    $error_mensaje = "ID de teclado no especificado para eliminar.";
    header("Location: gestion_teclados.php?error=" . urlencode($error_mensaje));
    exit();
}
?>