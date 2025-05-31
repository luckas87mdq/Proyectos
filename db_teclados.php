<?php
$servername = "localhost"; // Generalmente es 'localhost' si tu base de datos está en el mismo servidor
$username = "root";       // <-- ¡IMPORTANTE! Cambia esto por tu usuario de MySQL
$password = "";           // <-- ¡IMPORTANTE! Cambia esto por tu contraseña de MySQL (a menudo vacía en XAMPP/WAMP)
$dbname = "accesos";  // <-- ¡IMPORTANTE! Cambia esto por el nombre real de tu base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("La conexión a la base de datos ha fallado: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres a UTF-8 para evitar problemas con tildes y caracteres especiales
$conn->set_charset("utf8mb4");

// Opcional: Establecer el modo de error para MySQLi a excepciones (útil para depuración)
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

?>