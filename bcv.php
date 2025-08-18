<?php

function obtenerPrecioBCVV2PorFecha($fecha) {
    // La fecha debe estar en formato YYYY-MM-DD
    $url = "https://pydolarve.org/api/v2/historical/bcv?date=" . urlencode($fecha);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($curl);
    
    if (curl_errno($curl)) {
        return ['error' => 'Error en la petición cURL: ' . curl_error($curl)];
    }
    
    curl_close($curl);
    
    $data = json_decode($response, true);
    
    if (isset($data['success']) && $data['success'] && isset($data['data'])) {
        return [
            'success' => true,
            'fecha' => $data['data']['date'],
            'usd' => $data['data']['usd'],
            'eur' => $data['data']['eur']
        ];
    } else {
        return ['error' => 'No se encontraron datos para la fecha: ' . $fecha];
    }
}

// Ejemplo de uso:
// Forma 1: Usar la fecha actual del sistema
// $fecha_deseada = date('Y-m-d'); 

// Forma 2: Usar una fecha específica del pasado (ejemplo)
$fecha_deseada = '30-07-2025';

$resultado = obtenerPrecioBCVV2PorFecha($fecha_deseada);

if (isset($resultado['success']) && $resultado['success']) {
    echo "<h1>Tipo de Cambio del BCV para el {$resultado['fecha']}</h1>";
    echo "<p>Dólar (USD): **{$resultado['usd']} Bs.**</p>";
    echo "<p>Euro (EUR): **{$resultado['eur']} Bs.**</p>";
} else {
    echo "<h1>Error</h1>";
    echo "<p>{$resultado['error']}</p>";
}
?>