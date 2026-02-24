<?php
/**
 * Vista: Panel de Logística y Despacho (Módulo 3)
 * 
 * Muestra únicamente los pedidos con estado 'pagado' listos para ser embalados y despachados.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="TabLogistica" class="suite-tab-content" style="display: none;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h2 style="margin:0; color:#0f172a;">🚚 Panel de Logística y Despacho</h2>
        <p style="color:#64748b; margin:0;">Pedidos facturados esperando recolección o envío.</p>
    </div>

    <!-- Tabla de Pedidos Pendientes de Despacho -->
    <div class="suite-table-responsive">
        <table class="suite-modern-table" id="logisticsTable">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Método Entrega</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $pedidos_logistica ) ) : ?>
                    <?php foreach ( $pedidos_logistica as $p ) : ?>
                    <tr id="log-row-<?php echo intval( $p->id ); ?>">
                        <td><strong style="font-family: monospace; font-size: 14px;">#<?php echo esc_html( $p->codigo_cotizacion ); ?></strong></td>
                        <td><?php echo date( 'd/m/Y', strtotime( $p->fecha_emision ) ); ?></td>
                        <td><?php echo esc_html( $p->cliente_nombre ); ?></td>
                        <td>
                            <span class="pill-neutral" style="background: #e0f2fe; color: #0369a1;">
                                <?php echo esc_html( ! empty( $p->metodo_entrega ) ? $p->metodo_entrega : 'Por definir' ); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <!-- Botón Hoja de Picking (Impresión protegida) -->
                                <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=suite_print_picking&id=' . $p->id . '&nonce=' . wp_create_nonce('suite_quote_nonce') ) ); ?>" 
                                   target="_blank" class="btn-modern-action small" style="color: #475569;">
                                   🖨️ Hoja de Picking
                                </a>
                                
                                <!-- Botón Confirmar Despacho (Abre Modal) -->
                                <button class="btn-modern-action small" style="background: #ecfdf5; border-color: #a7f3d0; color: #059669;" 
                                        onclick="SuiteLogistics.openModal(<?php echo intval( $p->id ); ?>)">
                                    📦 Confirmar Despacho
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 40px 0; color: #94a3b8;">
                            🎉 No hay pedidos pendientes por despachar en este momento.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: CONFIRMACIÓN DE DESPACHO / SUBIDA DE POD           -->
<!-- ========================================================= -->
<div id="modal-confirmar-despacho" class="suite-modal">
    <div class="suite-modal-content">
        <span class="close-modal" onclick="jQuery('#modal-confirmar-despacho').fadeOut();">×</span>
        
        <h3 style="margin-top:0; color:#0f172a;">📦 Confirmar Despacho</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:20px;">
            Sube la foto de la guía de encomienda o el comprobante de entrega firmado (Proof of Delivery).
        </p>

        <input type="file" id="log-pod-file" class="widefat" accept="image/*,.pdf" style="padding: 15px; background: #f8fafc;">
        
        <button id="btn-procesar-despacho" class="btn-save-big" style="margin-top:15px; background-color: #3b82f6;">
            Subir y Marcar Despachado
        </button>
    </div>
</div>
