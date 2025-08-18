<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'conexion.php';
header('Content-Type: application/json');

// --- CONFIGURACIÓN DE ENVÍO DE CORREO ---
// Define el método de envío: 'local' para mail() o 'gmail' para PHPMailer
// Cambia esto a 'gmail' para usar la configuración de PHPMailer
$metodo_envio = 'gmail';
$activo_envio_correo = false;

if ($metodo_envio === 'gmail') {
    // Asegúrate de que las rutas a los archivos de PHPMailer sean correctas
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
    
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCorreoActivacion($destinatario, $nombre_usuario, $token, $metodo_envio) {
    $asunto = 'Activa tu cuenta';
    $enlace_activacion = 'https://araguita.unaux.com/activar.php?token=' . $token; // **IMPORTANTE: CAMBIA ESTO POR TU DOMINIO REAL**

    $mensaje = "Hola $nombre_usuario,<br><br>";
    $mensaje .= "Gracias por registrarte. Por favor, haz clic en el siguiente enlace para activar tu cuenta:<br><br>";
    $mensaje .= "<a href='$enlace_activacion'>$enlace_activacion</a><br><br>";
    $mensaje .= "Si no te registraste, puedes ignorar este correo.";

    if ($metodo_envio === 'local') {
        // Método de envío con la función nativa mail()
        $cabeceras = 'MIME-Version: 1.0' . "\r\n";
        $cabeceras .= 'Content-type: text/html; charset=utf-8' . "\r\n";
        $cabeceras .= 'From: no-responder@tudominio.com' . "\r\n";
        return mail($destinatario, $asunto, $mensaje, $cabeceras);
    } elseif ($metodo_envio === 'gmail') {
        // Método de envío con PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'campamento.araguita@gmail.com'; // **CAMBIA A TU CORREO DE GMAIL**
            $mail->Password   = 'ofub llyn wlvi xacf'; // **CAMBIA A TU CONTRASEÑA DE APLICACIÓN**
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('tucorreo@gmail.com', 'Sistema de Acampante Araguita');
            $mail->addAddress($destinatario, $nombre_usuario);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $mensaje;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("El correo no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}");
            return false;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $correo_confirmar = $_POST['correo_confirmar'] ?? '';
    $clave = $_POST['clave'] ?? '';
    $clave_confirmar = $_POST['clave_confirmar'] ?? '';
    $rol = 'usuario';

    if (!$nombre || !$correo || !$correo_confirmar || !$clave || !$clave_confirmar) {
        echo json_encode(['exito' => false, 'mensaje' => 'Todos los campos son obligatorios.']);
        exit;
    }

    if ($correo !== $correo_confirmar) {
        echo json_encode(['exito' => false, 'mensaje' => 'Los correos no coinciden.']);
        exit;
    }

    if ($clave !== $clave_confirmar) {
        echo json_encode(['exito' => false, 'mensaje' => 'Las claves no coinciden.']);
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(['exito' => false, 'mensaje' => 'El correo ya está registrado.']);
        exit;
    }
    
    // Iniciar transacción para asegurar que las operaciones son atómicas
    $conexion->begin_transaction();
    
    $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
    $stmt_usuario = $conexion->prepare("INSERT INTO usuarios (nombre, correo, clave, rol, estado) VALUES (?, ?, ?, ?, 'inactivo')");
    $stmt_usuario->bind_param("ssss", $nombre, $correo, $clave_segura, $rol);

    if ($stmt_usuario->execute()) {
        $usuario_id = $stmt_usuario->insert_id;
        $token = bin2hex(random_bytes(32));
        
        $stmt_token = $conexion->prepare("INSERT INTO activacion_tokens (usuario_id, token) VALUES (?, ?)");
        $stmt_token->bind_param("is", $usuario_id, $token);

        if ($stmt_token->execute()) {
            // Si el usuario y el token se guardaron correctamente, intentamos enviar el correo
            if($activo_envio_correo){
            if (enviarCorreoActivacion($correo, $nombre, $token, $metodo_envio)) {
                // Si el correo se envió, confirmamos la transacción
                $conexion->commit();
                echo json_encode(['exito' => true, 'mensaje' => 'Registro exitoso. Por favor, revisa tu correo electrónico para activar tu cuenta.']);
            } else {
                // Si el correo falló, revertimos todos los cambios
                $conexion->rollback();
                echo json_encode(['exito' => false, 'mensaje' => 'Error al enviar el correo de activación. Intenta nuevamente.']);
            }
        }else{
            $conexion->commit();
            echo json_encode(['exito' => true, 'mensaje' => 'Registro exitoso. Por favor, revisa tu correo electrónico para activar tu cuenta.']);
        }
        } else {
            // Si la inserción del token falló, revertimos
            $conexion->rollback();
            echo json_encode(['exito' => false, 'mensaje' => 'Error al registrar. Intenta nuevamente.']);
        }
    } else {
        // Si la inserción del usuario falló, revertimos
        $conexion->rollback();
        echo json_encode(['exito' => false, 'mensaje' => 'Error al registrar. Intenta nuevamente.']);
    }
    
    $stmt_usuario->close();
    $stmt_token->close();
    $conexion->close();
}
?>