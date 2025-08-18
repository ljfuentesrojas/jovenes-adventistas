<?php

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
echo obtenerTasaBCV();

?>