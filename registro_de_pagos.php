<?php
include 'conexion.php';
session_start();

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    header('Location: login.php');
    exit;
}

// Obtener la cuota de acampante
$cuota_query = $conexion->query("SELECT valor_cuota FROM cuota_acampante ORDER BY fecha_establecida DESC LIMIT 1");
$cuota_acampante = $cuota_query->fetch_assoc()['valor_cuota'] ?? 0;

// Consulta para obtener el saldo a favor en Bs
$saldo_a_favor_usuario_bs = 0;
$saldo_query_bs = $conexion->query("SELECT SUM(pc.transaccion_monto) AS saldo_total FROM pre_contabilidad AS pc JOIN registro_pagos AS rp ON rp.id = pc.id_registro_pago WHERE rp.creado_por = '$usuario_id' AND pc.tipo_pago = 'saldo a favor' AND pc.saldo_favor_activo = 1 AND pc.metodo_pago <> 4 ");

if ($saldo_query_bs) {
    $result_bs = $saldo_query_bs->fetch_assoc();
    $saldo_a_favor_usuario_bs = $result_bs['saldo_total'] ?? 0;
}

// Consulta para obtener el saldo a favor en USD
$saldo_a_favor_usuario_usd = 0;
$saldo_query_usd = $conexion->query("SELECT SUM(pc.transaccion_monto) AS saldo_total FROM pre_contabilidad AS pc JOIN registro_pagos AS rp ON rp.id = pc.id_registro_pago WHERE rp.creado_por = '$usuario_id' AND pc.tipo_pago = 'saldo a favor' AND pc.saldo_favor_activo = 1 AND pc.metodo_pago =4");

if ($saldo_query_usd) {
    $result_usd = $saldo_query_usd->fetch_assoc();
    $saldo_a_favor_usuario_usd = $result_usd['saldo_total'] ?? 0;
}

// Consulta para obtener los métodos de pago
$sql_metodos = "SELECT id, metodo_p FROM metodo_pago";
$result_metodos = $conexion->query($sql_metodos);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de Pagos</title>
    <?php include 'links-recursos.php'; ?>
    <style>
        /* Estilos del formulario de registro de pagos */
        .acampantes-list {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #ccc;
            border-radius: 5px;
            position: relative;
        }
        .acampantes-header {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
            border-bottom: 2px solid #ccc;
        }
        .acampante-row {
            display: flex;
            align-items: center;
            padding: 8px 10px;
        }
        .acampante-row:nth-child(even) {
            background-color: #f2f2f2;
        }
        .acampante-col {
            padding: 0 5px;
            border-right: 1px solid #dee2e6;
        }
        .acampante-col:last-child {
            border-right: none;
        }
        .acampante-pagado {
            color: green;
            font-weight: bold;
        }
        .form-check-input {
            border: 1px solid #000;
            margin-top: 0;
        }
        .form-check-input:checked {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        /* Estilos para el overlay de carga */
        .loader-overlay {
            --bs-backdrop-zindex: 1050;
            --bs-backdrop-bg: #000;
            --bs-backdrop-opacity: 0.5;
            position: fixed;
            top: 0;
            left: 0;
            z-index: var(--bs-backdrop-zindex);
            width: 100vw;
            height: 100vh;
            background-color: var(--bs-backdrop-bg);
            opacity: var(--bs-backdrop-opacity);
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        .loader-spinner {
            color: #fff;
            width: 4rem;
            height: 4rem;
        }
    </style>
</head>
<body style="padding-top: 70px;">
    <?php include 'dashboard.php'; ?>

    <div class="container mt-5">
        <h2 class="text-center mb-4">Registro de Pagos</h2>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Formulario de Pago
                    </div>
                    <div class="card-body">
                        
                        <form action="#" method="POST" id="registro-pago-form" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="metodo_pago" class="form-label"><b>Método de Pago</b></label>
                                        <select class="form-select" id="metodo_pago" name="metodo_pago">
                                            <option value="">Seleccione un método de pago</option>
                                            <?php while ($row = $result_metodos->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['metodo_p']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="transaccion_codigo" class="form-label"><b>Código de Transacción</b></label>
                                        <input type="text" class="form-control" id="transaccion_codigo" name="transaccion_codigo" disabled>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="monto_transaccion" class="form-label" id="monto-label"><b>Monto de la Transacción (Bs)</b></label>
                                        <input type="number" class="form-control" id="monto_transaccion" name="monto_transaccion" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fecha_transaccion" class="form-label"><b>Fecha de Transacción</b></label>
                                        <input type="date" class="form-control" id="fecha_transaccion" name="fecha_transaccion">
                                    </div>
                                </div>
                            </div>
                            <div id="campos-condicionales"></div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><b>Tipo de Pago</b></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_pago" id="pago_total" value="total">
                                            <label class="form-check-label" for="pago_total">
                                                Pago Total
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_pago" id="pago_abono" value="abono">
                                            <label class="form-check-label" for="pago_abono">
                                                Abono
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_pago" id="pago_saldo_favor_bs" value="saldo_favor_bs" <?= ($saldo_a_favor_usuario_bs <= 0) ? 'disabled' : '' ?>>
                                            <label class="form-check-label" for="pago_saldo_favor_bs">
                                                Usar Saldo a Favor (Bs: <?= number_format($saldo_a_favor_usuario_bs, 2) ?>)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_pago" id="pago_saldo_favor_usd" value="saldo_favor_usd" <?= ($saldo_a_favor_usuario_usd <= 0) ? 'disabled' : '' ?>>
                                            <label class="form-check-label" for="pago_saldo_favor_usd">
                                                Usar Saldo a Favor ($: <?= number_format($saldo_a_favor_usuario_usd, 2) ?>)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <p class="mb-1"><b>Tasa BCV del día:</b> <span id="tasa_bcv_display" class="text-danger">Cargando...</span></p>
                                        <p class="mb-1"><b>Cuota por acampante (USD):</b> <span id="cuota_acampante_display"><?= number_format($cuota_acampante, 2) ?></span></p>
                                        <input type="hidden" id="tasa_bcv_input" name="tasa_bcv" value="">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><b>Selecciona el, o los acampantes a pagar</b></label>
                                <div class="acampantes-list" id="lista-acampantes-container">
                                </div>
                                <p class="mt-2"><b>Monto total a pagar (USD):</b> <span id="monto-total-usd">0.00</span></p>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" id="btn-submit-pago">Registrar Pago</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="confirmacionModal" tabindex="-1" aria-labelledby="confirmacionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmacionModalLabel">Estado del Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-body-content">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="loader-overlay" id="loader-overlay">
        <div class="spinner-border loader-spinner" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            var cuotaAcampante = parseFloat($('#cuota_acampante_display').text());
            var tasaBcv = 0;
            var acampantesData = {};
            var saldoFavorUsuarioBs = parseFloat('<?= $saldo_a_favor_usuario_bs ?>');
            var saldoFavorUsuarioUSD = parseFloat('<?= $saldo_a_favor_usuario_usd ?>');
            var monedaSeleccionada = 'Bs';
            var isTasaValid = false;

            // Lógica del loader
            let loaderTimeout;
            const MIN_LOADER_TIME = 2000;

            function showLoader() {
                $('#loader-overlay').css('display', 'flex');
                loaderTimeout = Date.now();
            }

            function hideLoader(callback) {
                const timeElapsed = Date.now() - loaderTimeout;
                const delay = Math.max(0, MIN_LOADER_TIME - timeElapsed);
                
                setTimeout(() => {
                    $('#loader-overlay').css('display', 'none');
                    if (callback) {
                        callback();
                    }
                }, delay);
            }

            function showModal(message, isSuccess) {
                var modalTitle = isSuccess ? 'Éxito' : 'Error';
                var alertClass = isSuccess ? 'alert-success' : 'alert-danger';
                
                $('#confirmacionModalLabel').text(modalTitle);
                $('#modal-body-content').html('<div class="alert ' + alertClass + '" role="alert">' + message + '</div>');
                
                var myModal = new bootstrap.Modal(document.getElementById('confirmacionModal'));
                myModal.show();
            }

            function getTasaBcvActual() {
                showLoader();
                $.ajax({
                    url: 'https://pydolarve.org/api/v2/dollar?page=bcv',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.monitors && response.monitors.usd && response.monitors.usd.price) {
                            tasaBcv = parseFloat(response.monitors.usd.price);
                            $('#tasa_bcv_display').text(tasaBcv.toFixed(2));
                            $('#tasa_bcv_input').val(tasaBcv);
                            isTasaValid = true;
                            actualizarMontoTotal();
                            $('#btn-submit-pago').prop('disabled', false);
                        } else {
                            $('#tasa_bcv_display').text('Error al obtener la tasa');
                            isTasaValid = false;
                            $('#btn-submit-pago').prop('disabled', true);
                        }
                    },
                    error: function() {
                        $('#tasa_bcv_display').text('Error al conectar con la API');
                        isTasaValid = false;
                        $('#btn-submit-pago').prop('disabled', true);
                    },
                    complete: function() {
                        hideLoader();
                    }
                });
            }
            
            function getTasaBcvPorFecha(fecha) {
                if (!fecha) {
                    $('#tasa_bcv_display').text('Cargando...');
                    isTasaValid = false;
                    $('#btn-submit-pago').prop('disabled', true);
                    return;
                }
                
                showLoader();
                $.ajax({
                    url: 'get_tasa_bcv.php',
                    type: 'GET',
                    data: { fecha: fecha },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            tasaBcv = response.tasa_bcv;
                            $('#tasa_bcv_display').text(tasaBcv.toFixed(2));
                            $('#tasa_bcv_input').val(tasaBcv);
                            isTasaValid = true;
                            actualizarMontoTotal();
                            $('#btn-submit-pago').prop('disabled', false);
                        } else {
                            showModal(response.message, false);
                            $('#tasa_bcv_display').text('No disponible');
                            $('#tasa_bcv_input').val('');
                            tasaBcv = 0;
                            isTasaValid = false;
                            $('#btn-submit-pago').prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        showModal('Error al conectar con el servidor para obtener la tasa. Por favor, intente de nuevo.', false);
                        $('#tasa_bcv_display').text('Error');
                        isTasaValid = false;
                        $('#btn-submit-pago').prop('disabled', true);
                    },
                    complete: function() {
                        hideLoader();
                    }
                });
            }

            function actualizarMontoTotal() {
                var montoTotalUSD = 0;
                $('.acampante-check:checked').each(function() {
                    var id = $(this).val();
                    if (acampantesData[id]) {
                        montoTotalUSD += parseFloat(acampantesData[id].monto_restante);
                    }
                });
                $('#monto-total-usd').text(montoTotalUSD.toFixed(2));

                var tipoPago = $('input[name="tipo_pago"]:checked').val();
                var metodoPago = $('#metodo_pago').val();
                
                if (tipoPago === 'total') {
                    if (monedaSeleccionada === 'USD' || metodoPago == 4) {
                        $('#monto_transaccion').val(montoTotalUSD.toFixed(2));
                    } else if (monedaSeleccionada === 'Bs' && tasaBcv > 0) {
                        var montoTotalBs = montoTotalUSD * tasaBcv.toFixed(2);
                        $('#monto_transaccion').val(montoTotalBs.toFixed(2));
                    } else {
                        $('#monto_transaccion').val('');
                    }
                }
            }
            
            function limpiarYRecargarFormulario() {
                $('#registro-pago-form')[0].reset();
                $('#transaccion_codigo').prop('disabled', true);
                $('#monto_transaccion').prop('readonly', false);
                $('#monto_transaccion').prop('disabled', false);
                $('#metodo_pago').prop('disabled', false);
                $('#campos-condicionales').empty();
                
                $('#monto-total-usd').text('0.00');

                cargarAcampantes();
                getSaldosFavorUsuario();
                $('#monto-label').html('<b>Monto de la Transacción (Bs)</b>');
                
                getTasaBcvActual();
            }

            function getSaldosFavorUsuario() {
                $.ajax({
                    url: 'obtener_saldos_favor.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            saldoFavorUsuarioBs = parseFloat(response.saldo_bs);
                            saldoFavorUsuarioUSD = parseFloat(response.saldo_usd);
                            
                            var saldoBsText = 'Usar Saldo a Favor (Bs: ' + saldoFavorUsuarioBs.toFixed(2) + ')';
                            var saldoUsdText = 'Usar Saldo a Favor ($: ' + saldoFavorUsuarioUSD.toFixed(2) + ')';
                            
                            $('#pago_saldo_favor_bs').prop('disabled', saldoFavorUsuarioBs <= 0);
                            $('label[for="pago_saldo_favor_bs"]').text(saldoBsText);

                            $('#pago_saldo_favor_usd').prop('disabled', saldoFavorUsuarioUSD <= 0);
                            $('label[for="pago_saldo_favor_usd"]').text(saldoUsdText);
                        } else {
                            console.error('Error al obtener los saldos a favor: ' + response.message);
                        }
                    }
                });
            }

            function cargarAcampantes() {
                showLoader();
                $.ajax({
                    url: 'obtener_acampantes.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        var container = $('#lista-acampantes-container');
                        container.empty();
                        acampantesData = {};
                        if (response.success && response.acampantes.length > 0) {
                            var headerHtml = '<div class="row fw-bold text-muted acampantes-header">' +
                                '<div class="col-1 text-center acampante-col"></div>' +
                                '<div class="col-6 acampante-col"><b>Nombre (C.I.)</b></div>' +
                                '<div class="col-2 text-end acampante-col"><b>Abonado</b></div>' +
                                '<div class="col-2 text-end acampante-col"><b>Resta</b></div>' +
                                '</div>';
                            container.append(headerHtml);

                            response.acampantes.forEach(function(acampante) {
                                acampantesData[acampante.id] = acampante;

                                var acampanteHtml = '<div class="row acampante-row align-items-center">' +
                                    '<div class="col-1 text-center acampante-col">';

                                if (acampante.pagado_total) {
                                    acampanteHtml += '<i class="bi bi-check-circle-fill text-success"></i>';
                                } else {
                                    acampanteHtml += '<input class="form-check-input acampante-check" type="checkbox" name="acampantes[]" value="' + acampante.id + '" id="acampante-' + acampante.id + '">';
                                }

                                acampanteHtml += '</div>' +
                                    '<div class="col-6 acampante-col">' +
                                    '<label class="form-check-label" for="acampante-' + acampante.id + '">' + acampante.nombre + ' (' + acampante.cedula + ')</label>' +
                                    '</div>' +
                                    '<div class="col-2 text-end acampante-col">';

                                acampanteHtml += '$' + acampante.monto_abonado + '</div>' +
                                    '<div class="col-2 text-end acampante-col">';

                                if (acampante.pagado_total) {
                                    acampanteHtml += '$0.00';
                                } else {
                                    acampanteHtml += '$' + acampante.monto_restante;
                                }

                                acampanteHtml += '</div></div>';
                                container.append(acampanteHtml);
                            });

                            if (response.acampantes.every(a => a.pagado_total)) {
                                container.append('<p class="text-muted text-center mt-3">No hay acampantes pendientes de pago.</p>');
                            }
                        } else {
                            container.html('<p class="text-muted text-center">No hay acampantes registrados o pendientes de pago.</p>');
                        }
                    },
                    error: function() {
                        container.html('<p class="text-danger text-center">Error al cargar los acampantes. Intente de nuevo.</p>');
                    },
                    complete: function() {
                        hideLoader();
                    }
                });
            }

            $('#fecha_transaccion').on('change', function() {
                var selectedDate = $(this).val();
                getTasaBcvPorFecha(selectedDate);
            });
            
            $('#metodo_pago').on('change', function() {
                var selectedMetodoId = $(this).val();
                var isTransferenciaPagoMovil = (selectedMetodoId === '1' || selectedMetodoId === '2');
                var camposCondicionales = $('#campos-condicionales');
                
                camposCondicionales.empty();
                
                if (selectedMetodoId === '1' || selectedMetodoId === '2') {
                    camposCondicionales.html('<div class="mb-3"><label for="comprobante_pago" class="form-label"><b>Comprobante de Pago (Foto)</b></label><input type="file" class="form-control" id="comprobante_pago" name="comprobante_pago"></div>');
                } else if (selectedMetodoId === '3' || selectedMetodoId === '4') {
                    camposCondicionales.html('<div class="mb-3"><label for="observaciones" class="form-label"><b>Observaciones</b></label><input type="text" class="form-control" id="observaciones" name="observaciones"></div>');
                }

                if (selectedMetodoId == '4') {
                    $('#monto-label').html('<b>Monto de la Transacción ($)</b>');
                    monedaSeleccionada = 'USD';
                } else {
                    $('#monto-label').html('<b>Monto de la Transacción (Bs)</b>');
                    monedaSeleccionada = 'Bs';
                }

                if ($('input[name="tipo_pago"]:checked').val() === 'total') {
                    actualizarMontoTotal();
                }

                $('#transaccion_codigo').prop('disabled', !isTransferenciaPagoMovil);
                if (!isTransferenciaPagoMovil) {
                    $('#transaccion_codigo').val('');
                }
            });

            getTasaBcvActual();
            cargarAcampantes();

            $(document).on('change', '.acampante-check', function() {
                actualizarMontoTotal();
            });

            $('input[name="tipo_pago"]').on('change', function() {
                var tipoPago = $(this).val();
                var metodoPago = $('#metodo_pago').val();
                
                $('.acampante-check').prop('disabled', false);

                if (tipoPago === 'saldo_favor_bs' || tipoPago === 'saldo_favor_usd') {
                    $('#metodo_pago, #transaccion_codigo, #monto_transaccion, #fecha_transaccion').prop('disabled', true).val('');
                    $('#campos-condicionales').empty();
                    getTasaBcvActual();
                } else {
                    $('#metodo_pago, #monto_transaccion, #fecha_transaccion').prop('disabled', false);
                    var isTransferenciaPagoMovil = (metodoPago === '1' || metodoPago === '2');
                    $('#transaccion_codigo').prop('disabled', !isTransferenciaPagoMovil);
                    
                    $('#metodo_pago').trigger('change');
                    
                    if (tipoPago === 'total') {
                        $('#monto_transaccion').prop('readonly', true);
                        actualizarMontoTotal();
                    } else if (tipoPago === 'abono') {
                        $('#monto_transaccion').prop('readonly', false);
                        $('#monto_transaccion').val('');
                    }
                }
            });

            $('#registro-pago-form').on('submit', function(e) {
                e.preventDefault();
                $('#btn-submit-pago').prop('disabled', true).text('Procesando...');

                if (!isTasaValid) {
                    showModal('No se puede procesar el pago porque la tasa de cambio no es válida para la fecha seleccionada.', false);
                    $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                    return false;
                }

                var tipoPago = $('input[name="tipo_pago"]:checked').val();
                var acampantesSeleccionados = $('.acampante-check:checked');

                if (!tipoPago) {
                    showModal('Debes seleccionar un tipo de pago (Total, Abono o Saldo a Favor).', false);
                    $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                    return false;
                }
                
                if (acampantesSeleccionados.length === 0) {
                    showModal('Debes seleccionar al menos un acampante para registrar el pago.', false);
                    $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                    return false;
                }

                var acampantesIds = acampantesSeleccionados.map(function() {
                    return this.value;
                }).get();

                var formData = new FormData(this);
                formData.append('acampantes', JSON.stringify(acampantesIds));
                formData.append('tipo_pago_real', tipoPago);
                formData.append('moneda_seleccionada', monedaSeleccionada);
                formData.append('tasa_bcv', tasaBcv);

                if (tipoPago === 'saldo_favor_bs' || tipoPago === 'saldo_favor_usd') {
                    formData.delete('metodo_pago');
                    formData.delete('transaccion_codigo');
                    formData.delete('monto_transaccion');
                    formData.delete('fecha_transaccion');
                } else {
                    if (!$('#metodo_pago').val()) {
                        showModal('Debes seleccionar un método de pago.', false);
                        $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                        return false;
                    }
                    if (!$('#monto_transaccion').val() || parseFloat($('#monto_transaccion').val()) <= 0) {
                        showModal('Debes ingresar un monto de transacción válido.', false);
                        $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                        return false;
                    }
                    if (!$('#fecha_transaccion').val()) {
                        showModal('La fecha de transacción es obligatoria.', false);
                        $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                        return false;
                    }
                    var selectedMetodoId = $('#metodo_pago').val();
                    if ((selectedMetodoId === '1' || selectedMetodoId === '2') && !$('#transaccion_codigo').val()) {
                        showModal('El código de transacción es obligatorio para este método de pago.', false);
                        $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                        return false;
                    }
                }
                
                showLoader();
                $.ajax({
                    url: 'procesar_pago.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        hideLoader(() => {
                            showModal(response.message, response.success);
                            if (response.success) {
                                limpiarYRecargarFormulario();
                            }
                        });
                    },
                    error: function(xhr, status, error) {
                        hideLoader(() => {
                            showModal('Ha ocurrido un error al procesar la solicitud.', false);
                        });
                    },
                    complete: function() {
                        // El hideLoader ya está en success y error. No es necesario aquí.
                        $('#btn-submit-pago').prop('disabled', false).text('Registrar Pago');
                    }
                });
            });
        });
    </script>
</body>
</html>