<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');

// --- CONFIGURACIÓN DE ENVÍO DE CORREO ---
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Función para enviar un correo de confirmación de pago al usuario que lo registra.
 * @param string $destinatario Correo electrónico del usuario que realizó el registro.
 * @param array $datos_correo Un array con todos los datos necesarios para el correo.
 * @return bool Devuelve true si el correo se envió con éxito, false en caso contrario.
 */
function enviarCorreoConfirmacion($destinatario, $datos_correo) {
    $asunto = 'Confirmación de Registro de Pago';
    $cuerpo_correo = "¡Hola! \n\n" .
                     "Se ha registrado un pago exitosamente con los siguientes detalles:\n\n" .
                     "Tipo de Pago: {$datos_correo['tipo_pago_form']}\n" .
                     "Método de Pago: {$datos_correo['metodo_pago']}\n" .
                     "Monto Total de la Transacción: {$datos_correo['monto_transaccion_display']}\n" .
                     "Fecha de la Transacción: {$datos_correo['fecha_transaccion']}\n" .
                     "Tasa BCV: {$datos_correo['tasa_bcv']}\n";

    if ($datos_correo['incluir_codigo_transaccion']) {
        $cuerpo_correo .= "Código de Transacción: {$datos_correo['transaccion_codigo']}\n";
    }

    if (isset($datos_correo['comprobante_ruta']) && !empty($datos_correo['comprobante_ruta'])) {
        $cuerpo_correo .= "Comprobante: {$datos_correo['comprobante_ruta']}\n";
    }
    
    if (isset($datos_correo['observaciones']) && !empty($datos_correo['observaciones'])) {
        $cuerpo_correo .= "Observaciones: {$datos_correo['observaciones']}\n";
    }

    $cuerpo_correo .= "\nAcampantes asociados y monto aplicado:\n";
    foreach ($datos_correo['acampantes_aplicados'] as $acampante) {
        $cuerpo_correo .= "- {$acampante['nombre']}: {$acampante['monto_aplicado_display']} ({$acampante['tipo_pago_acampante']})\n";
    }
    
    $cuerpo_correo .= "\nAtentamente,\n" .
                     "El equipo de Acampantes Araguita.";

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host      = 'smtp.gmail.com';
        $mail->SMTPAuth  = true;
        $mail->Username  = 'campamento.araguita@gmail.com';
        $mail->Password  = 'ofub llyn wlvi xacf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port      = 465;

        $mail->setFrom('campamento.araguita@gmail.com', 'Sistema de Acampantes Araguita');
        $mail->addAddress($destinatario);

        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo_correo;
        
        if (!empty($datos_correo['comprobante_adjunto']) && file_exists($datos_correo['comprobante_adjunto'])) {
            $mail->addAttachment($datos_correo['comprobante_adjunto']);
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("El correo no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Redimensiona una imagen y la guarda en una nueva ruta.
 * @param string $source_path Ruta del archivo temporal.
 * @param string $dest_path Ruta donde se guardará la imagen final.
 * @param int $max_size Tamaño máximo (ancho o alto).
 * @param int $quality Calidad de la imagen para JPEG (1-100).
 * @return bool Retorna true si la operación fue exitosa, false en caso contrario.
 */
function redimensionarYGuardarImagen($source_path, $dest_path, $max_size = 450, $quality = 80) {
    list($width, $height, $image_type) = getimagesize($source_path);

    // Determinar el nuevo tamaño manteniendo la proporción
    if ($width > $height) {
        $new_width = $max_size;
        $new_height = ($height / $width) * $max_size;
    } else {
        $new_height = $max_size;
        $new_width = ($width / $height) * $max_size;
    }

    // Crear un nuevo lienzo para la imagen redimensionada
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            $success = imagejpeg($new_image, $dest_path, $quality);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            // Mantener transparencia para PNG
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            $success = imagepng($new_image, $dest_path, 9); // Calidad de PNG 0-9
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            $success = imagegif($new_image, $dest_path);
            break;
        default:
            // Tipo de imagen no soportado
            return false;
    }

    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $success;
}


// 1. Verificación de sesión y rol
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo los usuarios registrados pueden realizar pagos.']);
    exit;
}

$creado_por_id = $_SESSION['usuario_id'];

// 2. Extracción y validación de datos del formulario
$tipo_pago_form = $_POST['tipo_pago'] ?? null;
$acampantes_ids = json_decode($_POST['acampantes']) ?? [];

if (empty($tipo_pago_form)) {
    echo json_encode(['success' => false, 'message' => 'Falta el tipo de pago.']);
    exit;
}

if (($tipo_pago_form === 'total' || $tipo_pago_form === 'abono') && empty($acampantes_ids)) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar al menos un acampante para un pago normal.']);
    exit;
}

if (($tipo_pago_form === 'saldo_favor_bs' || $tipo_pago_form === 'saldo_favor_usd') && empty($acampantes_ids)) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar al menos un acampante para aplicar el saldo a favor.']);
    exit;
}

// 3. Obtener la cuota de inscripción actual
$sql_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY fecha_establecida DESC LIMIT 1";
$result_cuota = $conexion->query($sql_cuota);
$cuota_acampante_usd = $result_cuota->fetch_assoc()['valor_cuota'] ?? 0;

// Variables para el correo
$metodo_pago_id = null;
$transaccion_codigo = null;
$monto_transaccion = null;
$fecha_transaccion = null;
$tasa_bcv = null;
$acampantes_aplicados = [];

// --- NUEVA LÓGICA PARA PROCESAR COMPROBANTE Y OBSERVACIÓN ---
$comprobante_ruta = null;
$observaciones = $_POST['observaciones'] ?? null;
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/comprobantes/'; 

// Iniciar una transacción para asegurar la integridad de los datos
$conexion->begin_transaction();

try {
    if ($tipo_pago_form === 'total' || $tipo_pago_form === 'abono') {
        // Lógica para el pago normal (total o abono)
        $metodo_pago_id = $_POST['metodo_pago'] ?? null;
        $transaccion_codigo = $_POST['transaccion_codigo'] ?? null;
        $monto_transaccion = round($_POST['monto_transaccion'],2) ?? null;
        $fecha_transaccion = $_POST['fecha_transaccion'] ?? null;
        $tasa_bcv = round($_POST['tasa_bcv'],2) ?? 1;

        if (empty($metodo_pago_id) || empty($monto_transaccion) || empty($fecha_transaccion)) {
            throw new Exception("Faltan datos obligatorios para el registro del pago.");
        }

        // --- Lógica de procesamiento de imagen actualizada ---
        if (($metodo_pago_id == 1 || $metodo_pago_id == 2) && isset($_FILES['comprobante_pago']) && $_FILES['comprobante_pago']['error'] == 0) {
            $tmp_name = $_FILES['comprobante_pago']['tmp_name'];
            
            // Validar que el archivo sea una imagen
            $image_info = getimagesize($tmp_name);
            if ($image_info === false) {
                throw new Exception("El archivo subido no es una imagen válida.");
            }

            // Crear directorio si no existe
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['comprobante_pago']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('comprobante_') . '_' . time() . '.' . $file_extension;
            $file_path_full = $upload_dir . $file_name;

            if (redimensionarYGuardarImagen($tmp_name, $file_path_full)) {
                $comprobante_ruta = $file_name;
            } else {
                throw new Exception("Error al procesar y guardar el comprobante.");
            }
        }
        // --- Fin de la lógica de procesamiento de imagen ---


        if ($metodo_pago_id == 4) { // Tipo 4 es pago en dólares
            $monto_transaccion_usd = round($monto_transaccion,2);
        } else {
            $monto_transaccion_usd = round($monto_transaccion,2) / round($tasa_bcv,2);
        }
        
        $monto_total_a_aplicar_usd = $monto_transaccion_usd;

        // 3.1. Insertar el registro del pago completo en registro_pagos
        $sql_insert_registro_pago = "INSERT INTO registro_pagos (metodo_pago, transaccion_codigo, transaccion_monto, tasa_bcv, transaccion_fecha, creado_por, comprobante, observacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_registro_pago = $conexion->prepare($sql_insert_registro_pago);
        if ($stmt_registro_pago === false) {
            throw new Exception("Error al preparar la consulta de registro de pago: " . $conexion->error);
        }
        $stmt_registro_pago->bind_param("isdssiss", $metodo_pago_id, $transaccion_codigo, $monto_transaccion, $tasa_bcv, $fecha_transaccion, $creado_por_id, $comprobante_ruta, $observaciones);
        if (!$stmt_registro_pago->execute()) {
            throw new Exception("Error al insertar el registro de pago: " . $stmt_registro_pago->error);
        }
        $id_registro_pago = $conexion->insert_id;
        $stmt_registro_pago->close();

        // 3.2. Aplicar el monto a cada acampante
        foreach ($acampantes_ids as $acampante_id) {
            if ($monto_total_a_aplicar_usd <= 0) {
                break;
            }

            $sql_abonado_actual = "SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto 
                    ELSE pc.transaccion_monto / pc.tasa_bcv 
                END
            ), 0) AS monto_abonado_usd
            FROM pre_contabilidad pc
            LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
            WHERE pc.acampante_id = ?";
            $stmt_abonado = $conexion->prepare($sql_abonado_actual);
            $stmt_abonado->bind_param("i", $acampante_id);
            $stmt_abonado->execute();
            $result_abonado = $stmt_abonado->get_result();
            $row_abonado = $result_abonado->fetch_assoc();
            $monto_abonado_actual = round($row_abonado['monto_abonado_usd'],2);
            $stmt_abonado->close();

            $monto_restante_acampante_usd = max(0, $cuota_acampante_usd - $monto_abonado_actual);

            if ($monto_restante_acampante_usd <= 0) {
                continue;
            }

            $monto_a_aplicar_usd = min($monto_restante_acampante_usd, $monto_total_a_aplicar_usd);
            
            // Lógica corregida para el tipo de pago, usando redondeo
            $monto_abonado_total = $monto_abonado_actual + $monto_a_aplicar_usd;
            
            $monto_abonado_redondeado = round($monto_abonado_total, 2);
            $cuota_acampante_redondeada = round($cuota_acampante_usd, 2);

            if ($monto_abonado_redondeado >= $cuota_acampante_redondeada) {
                $tipo_pago_acampante = 'pago total';
                $monto_a_aplicar_usd = $cuota_acampante_usd - $monto_abonado_actual;
            } else {
                $tipo_pago_acampante = 'abono';
            }

            if ($metodo_pago_id == 4) {
                $monto_a_aplicar_bs = $monto_a_aplicar_usd;
            } else {
                $monto_a_aplicar_bs = round(($monto_a_aplicar_usd * $tasa_bcv),2);
            }

            $sql_insert = "INSERT INTO pre_contabilidad (acampante_id, metodo_pago, transaccion_codigo, transaccion_monto, transaccion_fecha, tasa_bcv, tipo_pago, id_registro_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conexion->prepare($sql_insert);
            $stmt_insert->bind_param("iisdsdsi", $acampante_id, $metodo_pago_id, $transaccion_codigo, $monto_a_aplicar_bs, $fecha_transaccion, $tasa_bcv, $tipo_pago_acampante, $id_registro_pago);

            if (!$stmt_insert->execute()) {
                throw new Exception("Error al insertar el pago para el acampante con ID $acampante_id: " . $stmt_insert->error);
            }
            $stmt_insert->close();

            if ($tipo_pago_acampante === 'pago total') {
                $sql_update = "UPDATE empleados SET pagado = 1 WHERE id = ?";
                $stmt_update = $conexion->prepare($sql_update);
                $stmt_update->bind_param("i", $acampante_id);
                if (!$stmt_update->execute()) {
                    throw new Exception("Error al actualizar el estado de pago del acampante con ID $acampante_id: " . $stmt_update->error);
                }
                $stmt_update->close();
            }

            // Guardar detalles para el correo
            $sql_nombre = "SELECT nombre FROM empleados WHERE id = ?";
            $stmt_nombre = $conexion->prepare($sql_nombre);
            $stmt_nombre->bind_param("i", $acampante_id);
            $stmt_nombre->execute();
            $nombre_acampante = $stmt_nombre->get_result()->fetch_assoc()['nombre'] ?? 'Desconocido';
            $stmt_nombre->close();

            $monto_aplicado_display = ($metodo_pago_id == 4) ? "$ " . number_format($monto_a_aplicar_usd, 2, '.', ',') : "Bs " . number_format($monto_a_aplicar_bs, 2, ',', '.');
            $acampantes_aplicados[] = [
                'nombre' => $nombre_acampante,
                'monto_aplicado_display' => $monto_aplicado_display,
                'tipo_pago_acampante' => $tipo_pago_acampante
            ];

            $monto_total_a_aplicar_usd -= $monto_a_aplicar_usd;
        }

        // 3.3. Registrar el saldo a favor si hay un excedente
        if ($monto_total_a_aplicar_usd > 0) {
            if ($metodo_pago_id == 4) {
                $saldo_a_favor_bs = $monto_total_a_aplicar_usd;
            } else {
                $saldo_a_favor_bs = round(($monto_total_a_aplicar_usd * $tasa_bcv),2);
            }
            
            $sql_insert_saldo_favor = "INSERT INTO pre_contabilidad (tipo_pago, transaccion_monto, id_registro_pago, saldo_favor_activo, metodo_pago, transaccion_codigo, tasa_bcv, transaccion_fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_saldo_favor = $conexion->prepare($sql_insert_saldo_favor);
            $tipo_pago_saldo = 'saldo a favor';
            $saldo_activo = 1;
            $stmt_saldo_favor->bind_param("sdiisdss", $tipo_pago_saldo, $saldo_a_favor_bs, $id_registro_pago, $saldo_activo, $metodo_pago_id, $transaccion_codigo, $tasa_bcv, $fecha_transaccion);
            if (!$stmt_saldo_favor->execute()) {
                throw new Exception("Error al registrar el saldo a favor: " . $stmt_saldo_favor->error);
            }
            $stmt_saldo_favor->close();
        }

    } else {
        // Lógica para usar saldo a favor
        $metodo_pago_filtro = ($tipo_pago_form === 'saldo_favor_usd') ? '= 4' : "<> 4";
        $tipo_pago_filtro = 'saldo a favor';

        $sql_saldo_activo = "SELECT pc.id, pc.transaccion_monto, pc.metodo_pago, pc.transaccion_codigo, pc.tasa_bcv, rp.id as id_registro_pago FROM pre_contabilidad AS pc JOIN registro_pagos AS rp ON rp.id = pc.id_registro_pago WHERE rp.creado_por = ? AND pc.tipo_pago = ? AND pc.saldo_favor_activo = 1 AND pc.metodo_pago $metodo_pago_filtro ORDER BY pc.id DESC LIMIT 1";
        $stmt_saldo_activo = $conexion->prepare($sql_saldo_activo);
        $stmt_saldo_activo->bind_param("is", $creado_por_id, $tipo_pago_filtro);
        $stmt_saldo_activo->execute();
        $result_saldo_activo = $stmt_saldo_activo->get_result();
        $saldo_row = $result_saldo_activo->fetch_assoc();
        $stmt_saldo_activo->close();

        if (!$saldo_row) {
            throw new Exception("No se encontró saldo a favor activo de tipo $tipo_pago_form para aplicar.");
        }

        $id_pre_contabilidad_saldo_original = $saldo_row['id'];
        $id_registro_pago_original = $saldo_row['id_registro_pago'];
        $monto_saldo_transaccion_original = round($saldo_row['transaccion_monto'],2);
        $metodo_pago_id = $saldo_row['metodo_pago'];
        $transaccion_codigo = $saldo_row['transaccion_codigo'];
        $tasa_bcv_original = $saldo_row['tasa_bcv'];
        $fecha_transaccion = date('Y-m-d');
        
        $tasa_bcv_nueva = $_POST['tasa_bcv'] ?? $tasa_bcv_original;

        if ($tipo_pago_form === 'saldo_favor_usd') {
            $monto_saldo_usd = $monto_saldo_transaccion_original;
        } else {
            $monto_saldo_usd = $monto_saldo_transaccion_original / $tasa_bcv_original;
        }
        
        $monto_a_aplicar_en_usd = $monto_saldo_usd;
        $monto_saldo_bs_restante = $monto_saldo_transaccion_original;

        foreach ($acampantes_ids as $acampante_id) {
            if ($monto_a_aplicar_en_usd <= 0) {
                break;
            }
            
            $sql_abonado_actual = "SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto 
                    ELSE pc.transaccion_monto / pc.tasa_bcv 
                END
            ), 0) AS monto_abonado_usd
            FROM pre_contabilidad pc
            LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
            WHERE pc.acampante_id = ?";
            $stmt_abonado = $conexion->prepare($sql_abonado_actual);
            $stmt_abonado->bind_param("i", $acampante_id);
            $stmt_abonado->execute();
            $result_abonado = $stmt_abonado->get_result();
            $row_abonado = $result_abonado->fetch_assoc();
            $monto_abonado_actual = round($row_abonado['monto_abonado_usd'],2);
            $stmt_abonado->close();

            $monto_restante_acampante_usd = max(0, $cuota_acampante_usd - $monto_abonado_actual);

            if ($monto_restante_acampante_usd <= 0) {
                continue;
            }

            $monto_aplicado_acampante_usd = min($monto_restante_acampante_usd, $monto_a_aplicar_en_usd);

            $monto_aplicado_acampante_bs = ($tipo_pago_form === 'saldo_favor_usd') ? $monto_aplicado_acampante_usd : $monto_aplicado_acampante_usd * $tasa_bcv_nueva;

            $sql_insert = "INSERT INTO pre_contabilidad (acampante_id, transaccion_monto, tipo_pago, id_registro_pago, metodo_pago, transaccion_codigo, tasa_bcv, transaccion_fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conexion->prepare($sql_insert);
            
            $monto_abonado_total = $monto_abonado_actual + $monto_aplicado_acampante_usd;
            $monto_abonado_redondeado = round($monto_abonado_total, 2);
            $cuota_acampante_redondeada = round($cuota_acampante_usd, 2);
            $tipo_pago_acampante = ($monto_abonado_redondeado >= $cuota_acampante_redondeada) ? 'pago total' : 'abono';

            $stmt_insert->bind_param("idsiidss", $acampante_id, $monto_aplicado_acampante_bs, $tipo_pago_acampante, $id_registro_pago_original, $metodo_pago_id, $transaccion_codigo, $tasa_bcv_nueva, $fecha_transaccion);
            if (!$stmt_insert->execute()) {
                throw new Exception("Error al insertar el pago con saldo a favor para acampante con ID $acampante_id: " . $stmt_insert->error);
            }
            $stmt_insert->close();

            if ($tipo_pago_acampante === 'pago total') {
                $sql_update = "UPDATE empleados SET pagado = 1 WHERE id = ?";
                $stmt_update = $conexion->prepare($sql_update);
                $stmt_update->bind_param("i", $acampante_id);
                if (!$stmt_update->execute()) {
                    throw new Exception("Error al actualizar el estado de pago del acampante con ID $acampante_id: " . $stmt_update->error);
                }
                $stmt_update->close();
            }

            // Guardar detalles para el correo
            $sql_nombre = "SELECT nombre FROM empleados WHERE id = ?";
            $stmt_nombre = $conexion->prepare($sql_nombre);
            $stmt_nombre->bind_param("i", $acampante_id);
            $stmt_nombre->execute();
            $nombre_acampante = $stmt_nombre->get_result()->fetch_assoc()['nombre'] ?? 'Desconocido';
            $stmt_nombre->close();

            $monto_aplicado_display = ($tipo_pago_form === 'saldo_favor_usd') ? "$ " . number_format($monto_aplicado_acampante_usd, 2, '.', ',') : "Bs " . number_format($monto_aplicado_acampante_bs, 2, ',', '.');
            $acampantes_aplicados[] = [
                'nombre' => $nombre_acampante,
                'monto_aplicado_display' => $monto_aplicado_display,
                'tipo_pago_acampante' => $tipo_pago_acampante
            ];
            
            $monto_a_aplicar_en_usd -= $monto_aplicado_acampante_usd;
            $monto_saldo_bs_restante -= $monto_aplicado_acampante_bs;
        }
        
        if ($monto_a_aplicar_en_usd > 0) {
            $sql_update_saldo = "UPDATE pre_contabilidad SET transaccion_monto = ?, saldo_favor_activo = 1 WHERE id = ?";
            $stmt_update_saldo = $conexion->prepare($sql_update_saldo);
            $stmt_update_saldo->bind_param("di", $monto_saldo_bs_restante, $id_pre_contabilidad_saldo_original);
            if (!$stmt_update_saldo->execute()) {
                throw new Exception("Error al actualizar el monto del saldo a favor: " . $stmt_update_saldo->error);
            }
            $stmt_update_saldo->close();
        } else {
            $sql_desactivar_saldo = "UPDATE pre_contabilidad SET saldo_favor_activo = 0 WHERE id = ?";
            $stmt_desactivar = $conexion->prepare($sql_desactivar_saldo);
            $stmt_desactivar->bind_param("i", $id_pre_contabilidad_saldo_original);
            if (!$stmt_desactivar->execute()) {
                throw new Exception("Error al desactivar el saldo a favor original.");
            }
            $stmt_desactivar->close();
        }
    }

    // --- LÓGICA DE ENVÍO DE CORREO DESPUÉS DE LA TRANSACCIÓN EXITOSA ---
    $sql_correo_creador = "SELECT correo FROM usuarios WHERE id = ?";
    $stmt_correo_creador = $conexion->prepare($sql_correo_creador);
    $stmt_correo_creador->bind_param("i", $creado_por_id);
    $stmt_correo_creador->execute();
    $correo_destinatario = $stmt_correo_creador->get_result()->fetch_assoc()['correo'] ?? '';
    $stmt_correo_creador->close();

    $sql_metodo = "SELECT metodo_p as nombre FROM metodo_pago WHERE id = ?";
    $stmt_metodo = $conexion->prepare($sql_metodo);
    $stmt_metodo->bind_param("i", $metodo_pago_id);
    $stmt_metodo->execute();
    $metodo_pago_nombre = $stmt_metodo->get_result()->fetch_assoc()['nombre'] ?? 'Desconocido';
    $stmt_metodo->close();

    if (!empty($correo_destinatario) && !empty($acampantes_aplicados)) {
        $monto_total_display = ($metodo_pago_id == 4) ? "$ " . number_format($monto_transaccion, 2, '.', ',') : "Bs " . number_format($monto_transaccion, 2, ',', '.');
        $datos_correo = [
            'tipo_pago_form' => $tipo_pago_form,
            'metodo_pago' => $metodo_pago_nombre,
            'monto_transaccion_display' => $monto_total_display,
            'fecha_transaccion' => $fecha_transaccion,
            'tasa_bcv' => ($tipo_pago_form === 'saldo_favor_bs') ? $tasa_bcv_nueva : $tasa_bcv,
            'transaccion_codigo' => $transaccion_codigo,
            'incluir_codigo_transaccion' => in_array($metodo_pago_id, [1, 2]),
            'comprobante_ruta' => $comprobante_ruta,
            'observaciones' => $observaciones,
            'comprobante_adjunto' => $comprobante_ruta ? $upload_dir . $comprobante_ruta : null,
            'acampantes_aplicados' => $acampantes_aplicados
        ];
        enviarCorreoConfirmacion($correo_destinatario, $datos_correo);
    }
    // --- FIN DE LA LÓGICA DE ENVÍO DE CORREO ---


    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Pago(s) registrado(s) correctamente.']);

} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conexion->close();
?>