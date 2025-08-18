<?php

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'tasa_bcv' => 0];

if (!isset($_GET['fecha'])) {
    $response['message'] = 'Falta el parámetro de fecha.';
    echo json_encode($response);
    exit;
}

$fecha = $_GET['fecha'];

try {
    $date = new DateTime($fecha);
    $dia_semana = $date->format('N'); // 1 = Lunes, 7 = Domingo
    
    $fecha_a_buscar = $fecha;

    // Si es Sábado (6) o Domingo (7), buscar la tasa del Viernes anterior
    if ($dia_semana == 6) {
        $date->modify('-1 day');
        $fecha_a_buscar = $date->format('Y-m-d');
    } elseif ($dia_semana == 7) {
        $date->modify('-2 day');
        $fecha_a_buscar = $date->format('Y-m-d');
    }

    // Construir la URL del endpoint con los parámetros de fecha
    $apiUrl = "https://api.dolarvzla.com/public/exchange-rate/list?from={$fecha_a_buscar}&to={$fecha_a_buscar}";

    // Realizar la llamada a la API
    $json = @file_get_contents($apiUrl);

    if ($json === false) {
        $response['message'] = 'Error al conectar con la API de tasas de cambio.';
    } else {
        $data = json_decode($json, true);

        // Verificar si la respuesta es válida y contiene datos
        if ($data && !empty($data['rates'])) {
            $tasa = $data['rates'][0]['usd'];
            $response['success'] = true;
            $response['tasa_bcv'] = (float)$tasa;
        } else {
            $response['message'] = 'No existe un valor de tasa_bcv para la fecha seleccionada. Por favor, contacte a un administrador.';
        }
    }

} catch (Exception $e) {
    $response['message'] = 'Error en el formato de la fecha o al procesar la API.';
}

echo json_encode($response);

// include 'conexion.php';

// header('Content-Type: application/json');

// $response = ['success' => false, 'message' => '', 'tasa_bcv' => 0];

// if (!isset($_GET['fecha'])) {
//     $response['message'] = 'Falta el parámetro de fecha.';
//     echo json_encode($response);
//     exit;
// }

// $fecha = $_GET['fecha'];

// try {
//     $date = new DateTime($fecha);
//     $dia_semana = $date->format('N'); // 1 = Lunes, 7 = Domingo
    
//     $fecha_a_buscar = $fecha;

//     // Si es Sábado (6) o Domingo (7), buscar la tasa del Viernes anterior
//     if ($dia_semana == 6) {
//         $date->modify('-1 day');
//         $fecha_a_buscar = $date->format('Y-m-d');
//     } elseif ($dia_semana == 7) {
//         $date->modify('-2 day');
//         $fecha_a_buscar = $date->format('Y-m-d');
//     }

//     $sql = "SELECT tasa_bcv FROM historico_tasa_bcv WHERE fecha = ?";
//     $stmt = $conexion->prepare($sql);
//     $stmt->bind_param("s", $fecha_a_buscar);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     if ($result->num_rows > 0) {
//         $tasa = $result->fetch_assoc()['tasa_bcv'];
//         $response['success'] = true;
//         $response['tasa_bcv'] = (float)$tasa;
//     } else {
//         $response['message'] = 'No existe un valor de tasa_bcv para la fecha seleccionada. Por favor, contacte a un administrador.';
//     }

// } catch (Exception $e) {
//     $response['message'] = 'Error en el formato de la fecha.';
// }

// echo json_encode($response);
// $conexion->close();
?>