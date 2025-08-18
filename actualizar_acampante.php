<?php
session_start();
include 'conexion.php';

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
$check_acampante = $conexion->prepare("SELECT creado_por, foto FROM empleados WHERE id = ?");
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

// 1. Recoger y sanitizar datos del formulario, incluyendo los nuevos campos
$nombre = $_POST['nombre'] ?? null;
$telefono = $_POST['telefono'] ?? null;
$cedula = $_POST['cedula'] ?? null;
$cargo_id = $_POST['cargo_id'] ?? null;
$tienda_id = $_POST['tienda_id'] ?? null;
$departamento_id = $_POST['departamento_id'] ?? null;
$region_id = $_POST['region_id'] ?? null;
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
$club_id = $_POST['club_id'] ?? null;
$id_sexo = $_POST['id_sexo'] ?? null; // Nuevo campo

// Iniciar una transacción
$conexion->begin_transaction();

try {
    // Función para redimensionar y recortar la imagen
    function resizeAndCropImage($sourcePath, $destinationPath, $targetWidth = 250, $targetHeight = 250) {
        $imageInfo = getimagesize($sourcePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceType = $imageInfo[2];

        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            // La imagen es más ancha que la proporción objetivo
            $tempHeight = $targetHeight;
            $tempWidth = (int) ($targetHeight * $sourceRatio);
            $cropX = (int) (($tempWidth - $targetWidth) / 2);
            $cropY = 0;
        } else {
            // La imagen es más alta que la proporción objetivo
            $tempWidth = $targetWidth;
            $tempHeight = (int) ($targetWidth / $sourceRatio);
            $cropX = 0;
            $cropY = (int) (($tempHeight - $targetHeight) / 2);
        }

        $tempImage = imagecreatetruecolor($tempWidth, $tempHeight);
        imagecopyresampled($tempImage, $sourceImage, 0, 0, 0, 0, $tempWidth, $tempHeight, $sourceWidth, $sourceHeight);

        $destinationImage = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopy($destinationImage, $tempImage, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight);

        imagejpeg($destinationImage, $destinationPath, 90); // Calidad de 90
        imagedestroy($sourceImage);
        imagedestroy($tempImage);
        imagedestroy($destinationImage);

        return true;
    }

    // 2. Manejo de foto y redimensionamiento
    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $nombreFoto = uniqid() . "_" . basename($_FILES['foto']['name']);
        $ruta_destino = $_SERVER['DOCUMENT_ROOT'] . "/fotos/" . $nombreFoto;
        $ruta_temp = $_FILES['foto']['tmp_name'];

        if (resizeAndCropImage($ruta_temp, $ruta_destino)) {
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
    $sql_acampante = "UPDATE empleados SET nombre = ?, telefono = ?, cedula = ?, cargo_id = ?, tienda_id = ?, departamento_id = ?, region_id = ?, fecha_nacimiento = ?, club_id = ?, id_sexo = ?";
    $types = "ssiiiiisii";
    $params = [$nombre, $telefono, $cedula, $cargo_id, $tienda_id, $departamento_id, $region_id, $fecha_nacimiento, $club_id, $id_sexo];
    
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
            d.nombre AS iglesia,
            c.nombre_club AS club
        FROM empleados a 
        LEFT JOIN departamentos d ON a.departamento_id = d.id 
        LEFT JOIN clubes c ON a.club_id = c.id
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
        'club' => $row_final['club'] ?? null,
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