<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="TabLogistica" class="suite-tab-content" style="display: none;">
	
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0;">
        <h2 style="margin: 0; color: #0f172a; font-size: 22px; display: flex; align-items: center; gap: 10px;">
            📦 <span>Centro de Logística y Despacho</span>
        </h2>
        <button type="button" id="btn-refresh-logistics" class="btn-save-big" style="background: #3b82f6; border: none; padding: 10px 20px; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);">
            🔄 Refrescar Tabla
        </button>
    </div>

    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <table id="logisticsTable" class="suite-table" style="width: 100%; text-align: left; border-collapse: collapse; white-space: nowrap;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 15px; color: #475569; font-size: 13px;">Orden y Fecha</th>
                    <th style="padding: 15px; color: #475569; font-size: 13px;">Cliente</th>
                    <th style="padding: 15px; color: #475569; font-size: 13px;">⚖️ Condiciones Fiscales</th>
                    <th style="padding: 15px; color: #475569; font-size: 13px;">🚚 Detalles de Entrega</th>
                    <th style="padding: 15px; color: #475569; font-size: 13px; text-align: center;">⚙️ Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $pedidos_logistica ) ) : ?>
                    <?php foreach ( $pedidos_logistica as $pedido ) : ?>
                        <?php 
                            // Lógica PHP visual: Determinar variables y URLs
                            $is_fiscal = ( isset( $pedido->requiere_factura ) && $pedido->requiere_factura == 1 ) ? 1 : 0;
                            $is_retencion = ( isset( $pedido->agente_retencion ) && $pedido->agente_retencion == 1 ) ? 1 : 0;
                            
                            $comprobante_url = ! empty( $pedido->comprobante_pago_url ) ? $pedido->comprobante_pago_url : ( ! empty( $pedido->url_captura_pago ) ? $pedido->url_captura_pago : '#' );
                            $has_comprobante = ( $comprobante_url !== '#' );
                            
                            $tipo_envio = ! empty( $pedido->tipo_envio ) ? $pedido->tipo_envio : 'No especificado';
                            $direccion = ! empty( $pedido->direccion_envio ) ? $pedido->direccion_envio : ( ! empty( $pedido->direccion_entrega ) ? $pedido->direccion_entrega : 'Sin dirección' );
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
							
							
							
							
                            <td style="padding: 15px;">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <strong style="color: #0f172a; font-size: 15px;">#<?php echo esc_html( $pedido->codigo_cotizacion ); ?></strong>
                                    <?php if ( isset( $pedido->prioridad ) && $pedido->prioridad == '1' ) : ?>
                                        <span style="background:#fee2e2; color:#dc2626; border: 1px solid #fca5a5; font-size:10px; font-weight:900; padding:2px 6px; border-radius:4px; box-shadow: 0 0 5px rgba(220, 38, 38, 0.4);">🚨 URGENTE</span>
                                    <?php endif; ?>
                                </div>
                                <span style="color: #64748b; font-size: 12px;"><?php echo date( 'd/m/Y', strtotime( $pedido->fecha_emision ) ); ?></span>
                            </td>
							
							
							
							
							
                            
                            <td style="padding: 15px; font-weight: 500; color: #334155; max-width: 200px; white-space: normal; word-wrap: break-word;">
                                👤 <?php echo esc_html( $pedido->cliente_nombre ); ?>
                            </td>
                            
                            <td style="padding: 15px;">
                                <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
                                    <?php if ( $is_fiscal ) : ?>
                                        <span style="background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; border: 1px solid #fca5a5;">
                                            🧾 FACTURA FISCAL
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ( $is_retencion ) : ?>
                                        <span style="background: #ffedd5; color: #c2410c; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; border: 1px solid #fdba74;">
                                            ✂️ AGENTE RETENCIÓN
                                        </span>
                                    <?php endif; ?>

                                    <?php if ( ! $is_fiscal && ! $is_retencion ) : ?>
                                        <span style="color: #94a3b8; font-size: 12px; font-style: italic;">Estándar (Sin requisitos)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td style="padding: 15px; max-width: 250px; white-space: normal;">
                                <strong style="color: #059669; font-size: 13px;">[<?php echo esc_html( strtoupper( $tipo_envio ) ); ?>]</strong><br>
                                <span style="color: #475569; font-size: 12px; line-height: 1.4; display: inline-block; margin-top: 4px;">
                                    <?php echo nl2br(esc_html( $direccion )); ?>
                                </span>
                            </td>
                            
                            <td style="padding: 15px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=suite_print_quote&id=' . $pedido->id . '&nonce=' . wp_create_nonce('suite_quote_nonce') ) ); ?>" target="_blank" class="btn-modern-action" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Imprimir Orden">
                                        🖨️ Orden
                                    </a>
                                    
                                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=suite_print_picking&id=' . $pedido->id . '&nonce=' . wp_create_nonce('suite_quote_nonce') ) ); ?>" target="_blank" class="btn-modern-action" style="background: #f59e0b; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);" title="Generar Hoja de Picking">
                                        📋 Picking
                                    </a>
                                    
                                    <?php if ( $has_comprobante ) : ?>
                                        <a href="<?php echo esc_url( $comprobante_url ); ?>" target="_blank" class="btn-modern-action" style="background: #dbeafe; color: #2563eb; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Ver Comprobante de Pago">
                                            💳 Pago
                                        </a>
                                    <?php else : ?>
                                        <span style="background: #f1f5f9; color: #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight:bold; cursor: not-allowed;" title="Sin comprobante adjunto">💳 Pago</span>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn-modern-action trigger-dispatch" 
                                        data-id="<?php echo esc_attr( $pedido->id ); ?>" 
                                        data-code="<?php echo esc_attr( $pedido->codigo_cotizacion ); ?>" 
                                        data-fiscal="<?php echo esc_attr( $is_fiscal ); ?>"
                                        style="background: #10b981; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);">
                                        📦 Despachar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" style="padding: 30px; text-align: center; color: #64748b; font-size: 15px;">
                            No hay órdenes pendientes de despacho en este momento. ✅
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="modal-confirm-dispatch" class="suite-modal" style="display: none;">
        <div class="suite-modal-content" style="max-width: 550px; background: #f8fafc; border-radius: 12px; padding: 30px; position: relative;">
            <span class="close-modal" id="close-modal-dispatch" style="position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #64748b;">&times;</span>
            
            <h2 style="margin-top: 0; color: #0f172a; display: flex; align-items: center; gap: 10px; font-size: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                📦 Confirmación de Despacho
            </h2>
            
            <div id="dispatch-info-box" style="margin-bottom:15px; color:#334155; font-size:15px;">
                </div>
            
            <form id="form-confirm-dispatch">
                <input type="hidden" id="disp-quote-id" value="">
                
                <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">
                            🧾 N° Recibo Loyverse / Factura Interna <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" id="disp-loyverse" class="suite-input" placeholder="Ej: 1-1054" style="width: 100%; padding: 10px; box-sizing: border-box; border: 2px solid #3b82f6;" required>
                        <small style="color: #94a3b8; font-size: 11px;">Este número activará el pago de comisiones al vendedor.</small>
                    </div>

                    <div id="box-factura-fiscal" style="margin-bottom: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; padding: 10px; border-radius: 5px;">
                        <label id="label-factura-fiscal" style="font-size: 13px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">
                            📸 Adjuntar Factura Fiscal Física (Opcional)
                        </label>
                        <input type="file" id="disp-factura-file" class="suite-input" accept=".jpg,.jpeg,.png,.pdf" style="width: 100%; padding: 8px; box-sizing: border-box;">
                    </div>

                    <div style="padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                        <label style="font-size: 13px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">
                            📦 Guía de Encomienda / Comprobante de Entrega (POD)
                        </label>
                        <input type="file" id="disp-pod-file" class="suite-input" accept=".jpg,.jpeg,.png,.pdf" style="width: 100%; padding: 8px; box-sizing: border-box;">
                    </div>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" id="btn-cancel-dispatch" class="btn-save-big" style="background: #cbd5e1; color: #334155; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-save-big" style="background: #059669; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.3);">
                        ✅ Confirmar y Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>