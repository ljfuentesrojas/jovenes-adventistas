<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['usuario_id'])) {
    $response['message'] = 'No autenticado.';
    echo json_encode($response);
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    $response['message'] = 'ID de acampante no válido.';
    echo json_encode($response);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos existentes del acampante para verificar permisos y el nombre de la foto
$check_acampante = $conexion->prepare("SELECT creado_por, foto FROM acampantes WHERE id = ?");
if (!$check_acampante) {
    $response['message'] = 'Error de preparación de consulta inicial: ' . $conexion->error;
    echo json_encode($response);
    exit;
}
$check_acampante->bind_param("i", $id);
$check_acampante->execute();
$acampante_res = $check_acampante->get_result();
$acampante_existente = $acampante_res->fetch_assoc();
$check_acampante->close();

if (!$acampante_existente) {
    $response['message'] = 'Acampante no encontrado.';
    echo json_encode($response);
    exit;
}

if ($_SESSION['rol'] !== 'admin' && $acampante_existente['creado_por'] != $usuario_id) {
    $response['message'] = 'No tienes permiso para editar este acampante.';
    echo json_encode($response);
    exit;
}

$foto_antigua = $acampante_existente['foto'];

// 1. Recoger y sanitizar datos del formulario
$nombre = $_POST['nombre'] ?? null;
$telefono = $_POST['telefono'] ?? null;
$cedula = $_POST['cedula'] ?? null;
$cargo_id = $_POST['cargo_id'] ?? null;
$tienda_id = $_POST['tienda_id'] ?? null;
$departamento_id = $_POST['departamento_id'] ?? null;
$region_id = $_POST['region_id'] ?? null;

// Iniciar una transacción
$conexion->begin_transaction();

try {
    // 2. Manejo de foto
    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $nombreFoto = uniqid() . "_" . basename($_FILES['foto']['name']);
        $ruta_destino = $_SERVER['DOCUMENT_ROOT'] . "/fotos/" . $nombreFoto;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_destino)) {
            $foto = $nombreFoto;
            
            // Eliminar la foto antigua si existe
            if ($foto_antigua) {
                $ruta_antigua = $_SERVER['DOCUMENT_ROOT'] . "/fotos/" . $foto_antigua;
                if (file_exists($ruta_antigua)) {
                    unlink($ruta_antigua);
                }
            }
        }
    }

    // 3. Actualizar la tabla de acampantes con los nuevos datos
    $sql_acampante = "UPDATE acampantes SET nombre = ?, telefono = ?, cedula = ?, cargo_id = ?, tienda_id = ?, departamento_id = ?, region_id = ?";
    $types = "ssiiii";
    $params = [$nombre, $telefono, $cedula, $cargo_id, $tienda_id, $departamento_id, $region_id];
    
    if ($foto) {
        $sql_acampante .= ", foto = ?";
        $types .= "s";
        $params[] = $foto;
    }
    
    $sql_acampante .= " WHERE id = ?";
    $types .= "i";
    $params[] = $id;

    $stmt_acampante = $conexion->prepare($sql_acampante);
    if (!$stmt_acampante) {
        throw new Exception('Error en la preparación de la consulta de acampantes: ' . $conexion->error);
    }
    
    $stmt_acampante->bind_param($types, ...$params);

    if (!$stmt_acampante->execute()) {
        throw new Exception('Error al actualizar acampante: ' . $stmt_acampante->error);
    }
    $stmt_acampante->close();

    // Confirmar la transacción
    $conexion->commit();

    // 4. Obtener los datos actualizados para la respuesta JSON
    $query_final = "
        SELECT 
            a.id, 
            a.nombre, 
            a.cedula, 
            a.inscrito,
            a.foto,
            d.nombre AS iglesia
        FROM acampantes a 
        LEFT JOIN departamentos d ON a.departamento_id = d.id 
        WHERE a.id = ?
    ";
    $stmt_final = $conexion->prepare($query_final);
    $stmt_final->bind_param("i", $id);
    $stmt_final->execute();
    $res_final = $stmt_final->get_result();
    $row_final = $res_final->fetch_assoc();
    $stmt_final->close();

    $response['success'] = true;
    $response['message'] = 'Registro guardado con éxito.';
    $response['acampante'] = [
        'id' => $row_final['id'],
        'cedula' => $row_final['cedula'],
        'nombre' => $row_final['nombre'],
        'iglesia' => $row_final['iglesia'] ?? null,
        'inscrito' => $row_final['inscrito'] == 0 ? 'No' : 'Sí',
        'foto' => $row_final['foto']
    ];

} catch (Exception $e) {
    $conexion->rollback();
    $response['message'] = 'Error al actualizar: ' . $e->getMessage();
}

$conexion->close();
echo json_encode($response);
?>