<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// --- CONFIGURACIÓN DE ENVÍO DE CORREO ---
// Define el método de envío: 'local' para mail() o 'gmail' para PHPMailer
$metodo_envio = 'gmail';

// Asegúrate de que las rutas a los archivos de PHPMailer sean correctas
if ($metodo_envio === 'gmail') {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Función para enviar un correo de notificación de pago aprobado al usuario registrador.
 * @param string $destinatario Correo electrónico del usuario que realizó el registro.
 * @param array $datos_correo Un array con todos los datos necesarios para el correo.
 * @return bool Devuelve true si el correo se envió con éxito, false en caso contrario.
 */
function enviarCorreoAprobacionPago($destinatario, $datos_correo) {
    $asunto = 'Confirmación de Pago Aprobado y Registro';

    $nombres_acampantes = implode(', ', $datos_correo['nombres_aprobados']);
    $cuerpo_correo = "¡Hola! \n\n" .
                     "Te informamos que tu pago ha sido aprobado exitosamente.\n\n" .
                     "Monto del pago: {$datos_correo['monto_display']}\n" .
                     "Fecha de la transacción: {$datos_correo['fecha']}\n";

    // Condición para incluir el código de transacción
    if ($datos_correo['incluir_codigo_transaccion']) {
        $cuerpo_correo .= "Código de transacción: {$datos_correo['transaccion_codigo']}\n\n";
    } else {
        $cuerpo_correo .= "\n";
    }

    $cuerpo_correo .= "Los siguientes acampantes asociados a este pago han sido marcados como inscritos:\n" .
                      "{$nombres_acampantes}\n\n" .
                      "¡Gracias por tu registro!\n\n" .
                      "Atentamente,\n" .
                      "El equipo de Acampantes Araguita.";

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'campamento.araguita@gmail.com'; // **CAMBIA A TU CORREO DE GMAIL**
        $mail->Password   = 'ofub llyn wlvi xacf'; // **CAMBIA A TU CONTRASEÑA DE APLICACIÓN**
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

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit;
}

$registro_pago_id = $_POST['registro_pago_id'] ?? null;

if (empty($registro_pago_id)) {
    $response['message'] = 'ID del registro de pago no proporcionado.';
    echo json_encode($response);
    exit;
}

$conexion->begin_transaction();

try {
    // 1. APROBAR EL PAGO ESPECÍFICO
    $sql_aprobar_pago = "UPDATE registro_pagos SET aprobar_pago = 1 WHERE id = ?";
    $stmt_aprobar_pago = $conexion->prepare($sql_aprobar_pago);
    if (!$stmt_aprobar_pago) {
        throw new Exception('Error en la preparación de la consulta de aprobación de pago: ' . $conexion->error);
    }
    $stmt_aprobar_pago->bind_param("i", $registro_pago_id);
    if (!$stmt_aprobar_pago->execute()) {
        throw new Exception('Error al ejecutar la consulta de aprobación de pago: ' . $stmt_aprobar_pago->error);
    }
    $stmt_aprobar_pago->close();

    // 2. OBTENER INFORMACIÓN COMPLETA DEL PAGO
    $sql_pago_info = "SELECT transaccion_codigo, metodo_pago, transaccion_monto, transaccion_fecha FROM registro_pagos WHERE id = ?";
    $stmt_pago_info = $conexion->prepare($sql_pago_info);
    $stmt_pago_info->bind_param("i", $registro_pago_id);
    $stmt_pago_info->execute();
    $pago_info = $stmt_pago_info->get_result()->fetch_assoc();
    $transaccion_codigo = $pago_info['transaccion_codigo'] ?? '';
    $metodo_pago_id = $pago_info['metodo_pago'] ?? 0;
    $transaccion_monto = $pago_info['transaccion_monto'] ?? 0;
    $transaccion_fecha = $pago_info['transaccion_fecha'] ?? '';
    $stmt_pago_info->close();

    // 3. OBTENER LOS ACAMPANTES ASOCIADOS A ESTE PAGO
    $sql_acampantes_ids = "SELECT acampante_id FROM pre_contabilidad WHERE id_registro_pago = ?";
    $stmt_acampantes_ids = $conexion->prepare($sql_acampantes_ids);
    if (!$stmt_acampantes_ids) {
        throw new Exception('Error en la preparación de la consulta de acampantes: ' . $conexion->error);
    }
    $stmt_acampantes_ids->bind_param("i", $registro_pago_id);
    $stmt_acampantes_ids->execute();
    $resultado_acampantes = $stmt_acampantes_ids->get_result();

    $nombres_acampantes_aprobados = [];
    $acampantes_inscritos_ids = [];
    $correo_destinatario = '';

    if ($resultado_acampantes->num_rows > 0) {
        // 4. OBTENER LA CUOTA DE ACAMPANTE VIGENTE
        $sql_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY fecha_establecida DESC LIMIT 1";
        $result_cuota = $conexion->query($sql_cuota);
        $cuota_acampante_usd = $result_cuota->fetch_assoc()['valor_cuota'] ?? 0;

        $primer_acampante_id = null;
        while ($fila_acampante = $resultado_acampantes->fetch_assoc()) {
            $acampante_id = $fila_acampante['acampante_id'];
            if (!$primer_acampante_id) {
                $primer_acampante_id = $acampante_id;
            }

            // 5. CALCULAR EL MONTO TOTAL ABONADO (SOLO PAGOS APROBADOS) PARA CADA ACAMPANTE
            $sql_pagos = "
                SELECT
                    pc.transaccion_monto,
                    rp.tasa_bcv,
                    rp.metodo_pago
                FROM pre_contabilidad pc
                JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
                WHERE pc.acampante_id = ? AND rp.aprobar_pago = 1
            ";
            $stmt_pagos = $conexion->prepare($sql_pagos);
            if (!$stmt_pagos) {
                throw new Exception('Error en la preparación de la consulta de pagos: ' . $conexion->error);
            }
            $stmt_pagos->bind_param("i", $acampante_id);
            $stmt_pagos->execute();
            $resultado_pagos = $stmt_pagos->get_result();

            $monto_abonado_total_usd = 0;
            while ($fila_pago = $resultado_pagos->fetch_assoc()) {
                if ($fila_pago['metodo_pago'] == 4) { // Asumimos 4 es USD
                    $monto_abonado_total_usd += $fila_pago['transaccion_monto'];
                } else {
                    if ($fila_pago['tasa_bcv'] > 0) {
                        $monto_abonado_total_usd += $fila_pago['transaccion_monto'] / $fila_pago['tasa_bcv'];
                    }
                }
            }
            $stmt_pagos->close();

            // 6. VERIFICAR SI EL MONTO ABONADO CUBRE LA CUOTA
            if ($monto_abonado_total_usd >= $cuota_acampante_usd) {
                // 7. ACTUALIZAR EL ESTADO DEL ACAMPANTE A INSCRITO
                $sql_inscribir = "UPDATE empleados SET inscrito = 1 WHERE id = ?";
                $stmt_inscribir = $conexion->prepare($sql_inscribir);
                if (!$stmt_inscribir) {
                    throw new Exception('Error en la preparación de la consulta de inscripción: ' . $conexion->error);
                }
                $stmt_inscribir->bind_param("i", $acampante_id);
                if (!$stmt_inscribir->execute()) {
                    throw new Exception('Error al ejecutar la consulta de inscripción: ' . $stmt_inscribir->error);
                }
                $stmt_inscribir->close();

                // Obtener el nombre del acampante para el mensaje
                $sql_nombre = "SELECT nombre FROM empleados WHERE id = ?";
                $stmt_nombre = $conexion->prepare($sql_nombre);
                $stmt_nombre->bind_param("i", $acampante_id);
                $stmt_nombre->execute();
                $nombre_acampante = $stmt_nombre->get_result()->fetch_assoc()['nombre'] ?? 'Desconocido';
                $stmt_nombre->close();

                $nombres_acampantes_aprobados[] = $nombre_acampante;
                $acampantes_inscritos_ids[] = $acampante_id;
            }
        }
    } else {
        throw new Exception('No se encontraron acampantes asociados a este registro de pago.');
    }
    
    $stmt_acampantes_ids->close();

    // 8. OBTENER EL CORREO DEL USUARIO QUE REGISTRÓ A ESTE ACAMPANTE
    if (!empty($acampantes_inscritos_ids)) {
        // Obtenemos el creador_por del primer acampante para usarlo como referencia
        $sql_creador_id = "SELECT creado_por FROM empleados WHERE id = ?";
        $stmt_creador_id = $conexion->prepare($sql_creador_id);
        $stmt_creador_id->bind_param("i", $primer_acampante_id);
        $stmt_creador_id->execute();
        $creador_id = $stmt_creador_id->get_result()->fetch_assoc()['creado_por'] ?? null;
        $stmt_creador_id->close();

        if ($creador_id) {
            // Obtenemos el correo del usuario a partir de su ID
            $sql_correo_creador = "SELECT correo FROM usuarios WHERE id = ?";
            $stmt_correo_creador = $conexion->prepare($sql_correo_creador);
            $stmt_correo_creador->bind_param("i", $creador_id);
            $stmt_correo_creador->execute();
            $correo_destinatario = $stmt_correo_creador->get_result()->fetch_assoc()['correo'] ?? '';
            $stmt_correo_creador->close();
        }
    }

    // 9. ENVÍO DE CORREO
    if (!empty($nombres_acampantes_aprobados) && !empty($correo_destinatario)) {
        $monto_display = ($metodo_pago_id == 4) ? "$ {$transaccion_monto}" : "Bs " . number_format($transaccion_monto, 2, ',', '.');
        
        $datos_correo = [
            'nombres_aprobados' => $nombres_acampantes_aprobados,
            'monto_display' => $monto_display,
            'fecha' => $transaccion_fecha,
            'transaccion_codigo' => $transaccion_codigo,
            'incluir_codigo_transaccion' => in_array($metodo_pago_id, [1, 2])
        ];

        enviarCorreoAprobacionPago($correo_destinatario, $datos_correo);
    }

    // 10. Si todo va bien, confirmar los cambios en la base de datos
    $conexion->commit();
    $response['success'] = true;

    // Generar un mensaje de respuesta detallado con los nombres
    $base_message = 'El pago ha sido aprobado exitosamente.';
    if (!empty($nombres_acampantes_aprobados)) {
        $nombres_list = implode(', ', $nombres_acampantes_aprobados);
        $inscrito_message = 'Los acampantes ' . $nombres_list . ' han sido marcados como inscritos.';
        $response['message'] = $base_message . ' ' . $inscrito_message;
    } else {
        $response['message'] = $base_message . ' Ningún acampante alcanzó la cuota de inscripción con este pago.';
    }

} catch (Exception $e) {
    // Si algo falla, revertir todos los cambios
    $conexion->rollback();
    $response['message'] = $e->getMessage();
}

$conexion->close();
echo json_encode($response);
?>