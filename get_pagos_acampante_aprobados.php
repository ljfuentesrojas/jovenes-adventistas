<?php
include 'conexion.php';
session_start();

// Verificar si el usuario es admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo '<div class="modal-header bg-danger text-white"><h5 class="modal-title">Error de Acceso</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body text-danger"><p>Acceso denegado.</p></div>';
    exit;
}

$acampante_id = $_GET['id'] ?? null;

if (empty($acampante_id)) {
    http_response_code(400);
    echo '<div class="modal-header bg-danger text-white"><h5 class="modal-title">Error</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body text-danger"><p>ID de acampante no proporcionado.</p></div>';
    exit;
}

try {
    // 1. Obtener la cuota de inscripción actual
    $sql_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY fecha_establecida DESC LIMIT 1";
    $result_cuota = $conexion->query($sql_cuota);
    $cuota_acampante_usd = $result_cuota->fetch_assoc()['valor_cuota'] ?? 0;

    // 2. Obtener la lista de pagos APROBADOS del acampante
    $sql_pagos = "
        SELECT
            pc.transaccion_monto,
            rp.transaccion_codigo,
            rp.transaccion_fecha,
            rp.tasa_bcv,
            mp.metodo_p AS metodo_pago_nombre,
            rp.metodo_pago AS metodo_pago_id
        FROM pre_contabilidad pc
        JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
        LEFT JOIN metodo_pago mp ON rp.metodo_pago = mp.id
        WHERE pc.acampante_id = ? AND rp.aprobar_pago = 1
        ORDER BY rp.transaccion_fecha DESC
    ";
    
    $stmt_pagos = $conexion->prepare($sql_pagos);
    if (!$stmt_pagos) {
        throw new Exception('Error en la preparación de la consulta de pagos: ' . $conexion->error);
    }
    $stmt_pagos->bind_param("i", $acampante_id);
    $stmt_pagos->execute();
    $resultado_pagos = $stmt_pagos->get_result();

    $pagos = [];
    $monto_abonado_total_usd = 0;
    while ($fila = $resultado_pagos->fetch_assoc()) {
        $monto_formateado = number_format($fila['transaccion_monto'], 2, ',', '.');
        if ($fila['metodo_pago_id'] == 4) {
            $fila['monto_display'] = "$ {$monto_formateado}";
            $monto_abonado_total_usd += $fila['transaccion_monto'];
        } else {
            $fila['monto_display'] = "Bs {$monto_formateado}";
            $monto_abonado_total_usd += $fila['transaccion_monto'] / $fila['tasa_bcv'];
        }
        $pagos[] = $fila;
    }
    $stmt_pagos->close();

    $monto_restante_usd = max(0, $cuota_acampante_usd - $monto_abonado_total_usd);

    // Obtener nombre del acampante
    $sql_acampante = "SELECT nombre FROM empleados WHERE id = ?";
    $stmt_acampante = $conexion->prepare($sql_acampante);
    $stmt_acampante->bind_param("i", $acampante_id);
    $stmt_acampante->execute();
    $nombre_acampante = $stmt_acampante->get_result()->fetch_assoc()['nombre'];
    $stmt_acampante->close();

    // Generar la respuesta HTML
?>
    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalVerPagosLabel">Pagos Aprobados del Acampante</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body">
        <h6 id="acampante-nombre">Acampante: <?= htmlspecialchars($nombre_acampante) ?></h6>
        <p class="mb-3">Monto de la cuota: <strong>$<?= htmlspecialchars(number_format($cuota_acampante_usd, 2)) ?></strong></p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Cód. Transacción</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Método de Pago</th>
                        <th>Tasa BCV</th> </tr>
                </thead>
                <tbody>
                    <?php if (count($pagos) > 0): ?>
                        <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td><?= htmlspecialchars($pago['transaccion_codigo'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pago['monto_display']) ?></td>
                                <td><?= htmlspecialchars($pago['transaccion_fecha']) ?></td>
                                <td><?= htmlspecialchars($pago['metodo_pago_nombre']) ?></td>
                                <td>
                                    <?php if ($pago['metodo_pago_id'] != 4): ?>
                                        <?= htmlspecialchars(number_format($pago['tasa_bcv'], 2, ',', '.')) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No se han registrado pagos aprobados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 p-2 border-top">
            <div class="fw-bold">
                Monto Abonado: <span id="monto-abonado">$<?= number_format($monto_abonado_total_usd, 2) ?></span><br>
                Monto Restante: <span id="monto-restante">$<?= number_format($monto_restante_usd, 2) ?></span>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    </div>
<?php

} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="modal-header bg-danger text-white"><h5 class="modal-title">Error</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body text-danger"><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}

$conexion->close();
?>