<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'];

    $stmt = $conn->prepare("UPDATE empleados SET inscrito = 1 WHERE transaccion_codigo = ?");
    $stmt->execute([$codigo]);

    echo "Empleado marcado como inscrito";
}
?>

<form method="POST">
  <input type="text" name="codigo" required>
  <button type="submit">Validar</button>
</form>