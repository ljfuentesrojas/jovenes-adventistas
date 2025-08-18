<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'conexion.php';
if (!isset($_SESSION['usuario_id'])) {
    $response['message'] = 'No autenticado.';
    echo json_encode($response);
    exit;
}

// --- CONFIGURACIÓN DE ENVÍO DE CORREO ---
$metodo_envio = 'gmail';

if ($metodo_envio === 'gmail') {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Redimensiona y recorta una imagen para ajustarla a un tamaño cuadrado.
 *
 * @param string $ruta_origen Ruta del archivo de imagen original.
 * @param string $ruta_destino Ruta donde se guardará la nueva imagen.
 * @param int $nuevo_ancho Ancho deseado para la imagen (ej: 250).
 * @param int $nuevo_alto Alto deseado para la imagen (ej: 250).
 * @return bool Retorna true si la operación fue exitosa, false en caso contrario.
 */
function redimensionar_imagen($ruta_origen, $ruta_destino, $nuevo_ancho, $nuevo_alto) {
    list($ancho_orig, $alto_orig, $tipo_imagen) = getimagesize($ruta_origen);

    switch ($tipo_imagen) {
        case IMAGETYPE_JPEG:
            $imagen = imagecreatefromjpeg($ruta_origen);
            break;
        case IMAGETYPE_PNG:
            $imagen = imagecreatefrompng($ruta_origen);
            break;
        case IMAGETYPE_GIF:
            $imagen = imagecreatefromgif($ruta_origen);
            break;
        default:
            return false;
    }

    $lienzo = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

    // Calcular proporciones para el recorte
    $proporcion_origen = $ancho_orig / $alto_orig;
    $proporcion_nueva = $nuevo_ancho / $nuevo_alto;
    
    // Variables de offset inicializadas a 0
    $offset_x = 0;
    $offset_y = 0;
    
    if ($proporcion_origen > $proporcion_nueva) {
        $nuevo_alto_temp = $nuevo_alto;
        $nuevo_ancho_temp = $nuevo_alto * $proporcion_origen;
        // LÍNEA 70 (CORREGIDA): Se usa round() para convertir explícitamente a entero
        $offset_x = round(($nuevo_ancho_temp - $nuevo_ancho) / 2);
    } else {
        $nuevo_ancho_temp = $nuevo_ancho;
        $nuevo_alto_temp = $nuevo_ancho / $proporcion_origen;
        // LÍNEA 72 (CORREGIDA): Se usa round() para convertir explícitamente a entero
        $offset_y = round(($nuevo_alto_temp - $nuevo_alto) / 2);
    }
    
    // imagecopyresampled con los nuevos offsets
    imagecopyresampled($lienzo, $imagen, 0, 0, $offset_x, $offset_y, $nuevo_ancho, $nuevo_alto, $ancho_orig, $alto_orig);

    // Guardar la imagen redimensionada
    switch ($tipo_imagen) {
        case IMAGETYPE_JPEG:
            return imagejpeg($lienzo, $ruta_destino);
        case IMAGETYPE_PNG:
            return imagepng($lienzo, $ruta_destino);
        case IMAGETYPE_GIF:
            return imagegif($lienzo, $ruta_destino);
    }
    
    imagedestroy($imagen);
    imagedestroy($lienzo);
    return true;
}

/**
 * Función para enviar un correo de notificación de acampante registrado.
 */
function enviarCorreoEmpleadoRegistrado($destinatario, $datos_empleado, $metodo_envio) {
    $asunto = 'Nuevo Acampante Registrado';
    $cuerpo_correo = "Se ha registrado un nuevo Acampante en el sistema:\n\n" .
                     "Nombre: {$datos_empleado['nombre']}\n" .
                     "Teléfono: {$datos_empleado['telefono']}\n" .
                     "Fecha de Nacimiento: {$datos_empleado['fecha_nacimiento']}\n" .
                     "Edad: {$datos_empleado['edad']}\n" .
                     "Cédula: {$datos_empleado['cedula']}\n" .
                     "Tipo de Acampante: {$datos_empleado['cargo']}\n" .
                     "Iglesia: {$datos_empleado['departamento']}\n" .
                     "Distrito: {$datos_empleado['tienda']}\n" .
                     "Zona: {$datos_empleado['region']}\n" .
                     "Club: {$datos_empleado['club']}\n";

    if ($metodo_envio === 'local') {
        $cabeceras = 'From: campamento.araguita@gmail.com' . "\r\n" .
                     'Reply-To: campamento.araguita@gmail.com' . "\r\n" .
                     'X-Mailer: PHP/' . phpversion();
        return mail($destinatario, $asunto, $cuerpo_correo, $cabeceras);
    } elseif ($metodo_envio === 'gmail') {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'campamento.araguita@gmail.com';
            $mail->Password   = 'ofub llyn wlvi xacf';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('campamento.araguita@gmail.com', 'Sistema de Acampantes Araguita');
            $mail->addAddress($destinatario);

            $mail->isHTML(false);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpo_correo;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("El correo no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}");
            return false;
        }
    }
    return false;
}

/**
 * Obtiene el precio actual del dólar BCV de la API pydolarve.org.
 */
function obtenerTasaBCV() {
    $url = 'https://pydolarve.org/api/v2/dollar';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        error_log("Error al obtener la tasa del BCV. Código de estado HTTP: {$http_code} - Error cURL: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data === null || !isset($data['monitors']['bcv']['price'])) {
        error_log("Respuesta de la API inválida o sin la tasa del BCV.");
        return null;
    }
    return (float)$data['monitors']['bcv']['price'];
}

/**
 * Función para registrar pagos y contabilidad para acampantes exonerados.
 * Se activa si el acampante tiene un cargo_id de 6 o 3.
 *
 * @param int $acampante_id El ID del acampante recién registrado.
 * @param int $cargo_id El ID del cargo del acampante.
 * @param int $creado_por El ID del usuario que creó el registro.
 * @param object $conexion La conexión a la base de datos.
 * @return bool Devuelve true si los registros se crearon con éxito, false en caso contrario.
 * @throws Exception Si falla alguna de las inserciones.
 */
function manejar_pagos_exonerados($acampante_id, $cargo_id, $creado_por, $conexion) {
    if ($cargo_id == 6 || $cargo_id == 3) {
        // ** INICIO: CÓDIGO MODIFICADO PARA OBTENER EL MONTO DE LA BASE DE DATOS **
        // ------------------------------------------------------------------------------------
        $sql_monto = "SELECT valor_cuota FROM cuota_acampante ORDER BY id DESC LIMIT 1";
        $stmt_monto = $conexion->prepare($sql_monto);
        if (!$stmt_monto) {
            throw new Exception('Error al preparar la consulta del monto: ' . $conexion->error);
        }
        $stmt_monto->execute();
        $result_monto = $stmt_monto->get_result();
        $monto = ($result_monto->num_rows > 0) ? $result_monto->fetch_assoc()['valor_cuota'] : 0.0;
        $stmt_monto->close();
        // ------------------------------------------------------------------------------------
        // ** FIN: CÓDIGO MODIFICADO **
        
        $tasa_bcv = obtenerTasaBCV();
        if ($tasa_bcv === null) {
            $tasa_bcv = 0.0;
            error_log("No se pudo obtener la tasa BCV de la API. Usando valor predeterminado.");
        }
        $metodo_pago = 4;
        $transaccion_codigo = 'Exonerado';
        $transaccion_fecha = date('Y-m-d');
        $tipo_pago = 'exonerado';
        $aprobar_pagos = 1;

        // 1. Insertar en registro_pagos
        $sql_pagos = "INSERT INTO registro_pagos (metodo_pago, transaccion_codigo, transaccion_monto, tasa_bcv, transaccion_fecha, creado_por, aprobar_pago) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_pagos = $conexion->prepare($sql_pagos);
        if (!$stmt_pagos) {
            throw new Exception('Error en la preparación de la consulta de pagos: ' . $conexion->error);
        }
        $stmt_pagos->bind_param("isdssii", $metodo_pago, $transaccion_codigo, $monto, $tasa_bcv, $transaccion_fecha, $creado_por, $aprobar_pagos);
        if (!$stmt_pagos->execute()) {
            throw new Exception('Error al insertar en la tabla registro_pagos: ' . $stmt_pagos->error);
        }
        $id_registro_pago = $conexion->insert_id;
        $stmt_pagos->close();

        // 2. Insertar en pre_contabilidad
        $sql_contabilidad = "INSERT INTO pre_contabilidad (acampante_id, metodo_pago, transaccion_codigo, transaccion_monto, transaccion_fecha, tasa_bcv, tipo_pago, id_registro_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_contabilidad = $conexion->prepare($sql_contabilidad);
        if (!$stmt_contabilidad) {
            throw new Exception('Error en la preparación de la consulta de contabilidad: ' . $conexion->error);
        }
        $stmt_contabilidad->bind_param("iisdsdsi", $acampante_id, $metodo_pago, $transaccion_codigo, $monto, $transaccion_fecha, $tasa_bcv, $tipo_pago, $id_registro_pago);
        if (!$stmt_contabilidad->execute()) {
            throw new Exception('Error al insertar en la tabla pre_contabilidad: ' . $stmt_contabilidad->error);
        }
        $stmt_contabilidad->close();

        // 3. Actualizar la tabla empleados
        $sql_update = "UPDATE empleados SET inscrito = 1, pagado = 1 WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception('Error en la preparación de la consulta de actualización de empleados: ' . $conexion->error);
        }
        $stmt_update->bind_param("i", $acampante_id);
        if (!$stmt_update->execute()) {
            throw new Exception('Error al actualizar la tabla empleados: ' . $stmt_update->error);
        }
        $stmt_update->close();

        return true;
    }
    return false;
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['usuario_id'])) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método de solicitud no permitido.';
    echo json_encode($response);
    exit;
}

$required_fields = ['nombre', 'telefono', 'fecha_nacimiento', 'sexo_id', 'cargo_id', 'departamento_id', 'tienda_id', 'region_id'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $response['message'] = "El campo '{$field}' es obligatorio.";
        echo json_encode($response);
        exit;
    }
}

$conexion->begin_transaction();
$exoneracion_activa = true;

try {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $edad = (int)$_POST['edad'];
    $cedula = $_POST['cedula'] ?? '';
    $sexo_id = (int)$_POST['sexo_id'];
    $cargo_id = (int)$_POST['cargo_id'];
    $departamento_id = (int)$_POST['departamento_id'];
    $tienda_id = (int)$_POST['tienda_id'];
    $region_id = (int)$_POST['region_id'];
    $club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : null;
    $creado_por = $_SESSION['usuario_id'] ?? 0;
    
    // --- LÓGICA DE VALIDACIÓN DE CARGOS ESPECIALES (Director y Sub-director) ---
    $crear_exoneracion = true;
    if ($cargo_id == 3 || $cargo_id == 6) {
        $sql_check = "SELECT COUNT(*) FROM empleados 
                      WHERE cargo_id = ? AND region_id = ? AND tienda_id = ? AND departamento_id = ? AND club_id = ?";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) {
            throw new Exception('Error al preparar la consulta de validación de director: ' . $conexion->error);
        }
        $stmt_check->bind_param("iiiii", $cargo_id, $region_id, $tienda_id, $departamento_id, $club_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row = $result_check->fetch_row();
        $count = $row[0];
        $stmt_check->close();

        if ($count > 0) {
            if ($cargo_id == 3) {
                // Si es director, abortar la transacción y mostrar un error
                throw new Exception("Ya existe un Director para la misma iglesia y club, por favor seleccione otro tipo de acampante para este registro.");
            } elseif ($cargo_id == 6) {
                // Si es sub-director, no se crea la exoneración pero se continúa el registro
                $crear_exoneracion = false;
            }
        }
    }
    // --- FIN DE LÓGICA DE VALIDACIÓN ---

    $ruta_foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $directorio_subidas = $_SERVER['DOCUMENT_ROOT'] . '/fotos/';
        if (!is_dir($directorio_subidas)) {
            mkdir($directorio_subidas, 0777, true);
        }
        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nombre_unico = uniqid('foto_') . '.' . $extension;
        $ruta_destino_absoluta = $directorio_subidas . $nombre_unico;

        // Llamar a la función para redimensionar y guardar la imagen
        if (redimensionar_imagen($_FILES['foto']['tmp_name'], $ruta_destino_absoluta, 250, 250)) {
            $ruta_foto = $nombre_unico;
        } else {
            throw new Exception('Error al redimensionar y subir la foto.');
        }
    }
    
    $inscrito = 0;
    $pagado = 0;
    $sql_empleado = "INSERT INTO empleados
                     (nombre, telefono, cedula, fecha_nacimiento, edad, id_sexo, foto, cargo_id, departamento_id, tienda_id, region_id, club_id, inscrito, pagado, creado_por)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_empleado = $conexion->prepare($sql_empleado);
    if (!$stmt_empleado) {
        throw new Exception('Error en la preparación de la consulta de acampantes: ' . $conexion->error);
    }
    $stmt_empleado->bind_param("ssssiisiiiiiiii",
        $nombre,
        $telefono,
        $cedula,
        $fecha_nacimiento,
        $edad,
        $sexo_id,
        $ruta_foto,
        $cargo_id,
        $departamento_id,
        $tienda_id,
        $region_id,
        $club_id,
        $inscrito,
        $pagado,
        $creado_por
    );

    if (!$stmt_empleado->execute()) {
        throw new Exception('Error al insertar en la tabla empleados: ' . $stmt_empleado->error);
    }
    $acampante_id = $conexion->insert_id;
    $stmt_empleado->close();
    
    if ($exoneracion_activa && $crear_exoneracion) {
        manejar_pagos_exonerados($acampante_id, $cargo_id, $creado_por, $conexion);
    }
    
    $conexion->commit();
    $response['success'] = true;
    $response['message'] = 'Acampante registrado con éxito.';
    if (!$crear_exoneracion && ($cargo_id == 6)) {
        $response['message'] .= " Se ha registrado un Sub-director, pero no se ha aplicado la exoneración ya que ya existe un registro con este cargo en el club.";
    }

    $sql_info = "SELECT c.nombre as cargo, d.nombre as departamento, t.nombre as nombre_tienda, r.nombre as region, cl.nombre_club as club
                 FROM cargos c, departamentos d, tiendas t, regiones r
                 LEFT JOIN clubes cl ON cl.id = ?
                 WHERE c.id = ? AND d.id = ? AND t.id = ? AND r.id = ?";
    $stmt_info = $conexion->prepare($sql_info);
    $stmt_info->bind_param("iiiii", $club_id, $cargo_id, $departamento_id, $tienda_id, $region_id);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    $datos_empleado = [
        'nombre' => $nombre,
        'cedula' => $cedula,
        'telefono' => $telefono,
        'fecha_nacimiento' => $fecha_nacimiento,
        'edad' => $edad,
        'cargo' => $result_info['cargo'] ?? 'Desconocido',
        'departamento' => $result_info['departamento'] ?? 'Desconocido',
        'tienda' => $result_info['nombre_tienda'] ?? 'Desconocido',
        'region' => $result_info['region'] ?? 'Desconocido',
        'club' => $result_info['club'] ?? 'N/A'
    ];

    if (!enviarCorreoEmpleadoRegistrado($_SESSION['usuario']['correo'], $datos_empleado, $metodo_envio)) {
        error_log("Error al enviar el correo de notificación para el acampante: {$nombre}");
    }

} catch (Exception $e) {
    $conexion->rollback();
    $response['message'] = $e->getMessage();
}

$conexion->close();
echo json_encode($response);
?>