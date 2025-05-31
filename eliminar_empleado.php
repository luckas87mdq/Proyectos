<?php
include 'db_teclados.php'; // Incluye tu archivo de conexión a la base de datos

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $empleado_id = (int)$_GET['id'];

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // 1. Eliminar asignaciones relacionadas con este empleado (si las hay)
        // Esto es crucial para evitar errores de clave foránea si tienes asignaciones
        $stmt_delete_asignaciones = $conn->prepare("DELETE FROM asignaciones WHERE empleado_id = ?");
        if ($stmt_delete_asignaciones === false) {
            throw new Exception("Error al preparar la consulta de eliminación de asignaciones: " . $conn->error);
        }
        $stmt_delete_asignaciones->bind_param("i", $empleado_id);
        if (!$stmt_delete_asignaciones->execute()) {
            throw new Exception("Error al eliminar asignaciones del empleado: " . $stmt_delete_asignaciones->error);
        }
        $stmt_delete_asignaciones->close();

        // 2. Eliminar el empleado
        $stmt_delete_empleado = $conn->prepare("DELETE FROM empleados WHERE id = ?");
        if ($stmt_delete_empleado === false) {
            throw new Exception("Error al preparar la consulta de eliminación de empleado: " . $conn->error);
        }
        $stmt_delete_empleado->bind_param("i", $empleado_id);

        if (!$stmt_delete_empleado->execute()) {
            throw new Exception("Error al eliminar el empleado: " . $stmt_delete_empleado->error);
        }
        $stmt_delete_empleado->close();

        $conn->commit(); // Confirmar la transacción
        // Redirigir de vuelta a la página de gestión de empleados con un mensaje de éxito
        header("Location: gestion_empleados.php?mensaje=Empleado y sus asignaciones eliminados correctamente.");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Revertir la transacción en caso de error
        // Redirigir con un mensaje de error
        header("Location: gestion_empleados.php?error=" . urlencode("Error al eliminar el empleado: " . $e->getMessage()));
        exit();
    }
} else {
    // Redirigir si no se proporcionó un ID válido
    header("Location: gestion_empleados.php?error=" . urlencode("ID de empleado no especificado o inválido para eliminar."));
    exit();
}

$conn->close();
?>