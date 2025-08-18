<?php
include 'conexion.php';
session_start();

// Verifica si el usuario tiene una sesión activa y un rol válido
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'usuario')) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// Obtener el valor de la cuota de inscripción
$sql_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY fecha_establecida DESC LIMIT 1";
$result_cuota = $conexion->query($sql_cuota);
$cuota_acampante = $result_cuota->fetch_assoc()['valor_cuota'] ?? 0;

// Base de la consulta para obtener los acampantes y su total abonado
$sql_acampantes = "
    SELECT 
        e.id, 
        e.nombre, 
        e.cedula,
        (
            SELECT COALESCE(SUM(
                            CASE 
                                WHEN p.metodo_pago = 4 THEN p.transaccion_monto 
                                ELSE p.transaccion_monto / p.tasa_bcv 
                            END
                        ), 0) 
            FROM pre_contabilidad p 
            WHERE p.acampante_id = e.id
        ) AS monto_abonado
    FROM 
        empleados e

        WHERE e.pagado = 0
    -- Se ha eliminado la condición 'WHERE e.pagado = 0' para mostrar a todos
";

// Modifica la consulta si el usuario no es un administrador
if ($rol !== 'admin') {
    $sql_acampantes .= " WHERE e.creado_por = " . intval($usuario_id);
}

$result_acampantes = $conexion->query($sql_acampantes);

$acampantes = [];
if ($result_acampantes->num_rows > 0) {
    while ($row = $result_acampantes->fetch_assoc()) {
        $monto_abonado_usd = $row['monto_abonado'] ?? 0;
        
        $pagado_total = (round($monto_abonado_usd,2) >= $cuota_acampante);
        $monto_restante = max(0, $cuota_acampante - $monto_abonado_usd);
        
        $acampantes[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'cedula' => $row['cedula'],
            'monto_abonado' => number_format($monto_abonado_usd, 2, '.', ''),
            'monto_restante' => number_format($monto_restante, 2, '.', ''),
            'pagado_total' => $pagado_total // Nuevo campo para la lógica del front-end
        ];
    }
}

echo json_encode([
    'success' => true,
    'acampantes' => $acampantes,
    'cuota' => number_format($cuota_acampante, 2, '.', '')
]);
?>