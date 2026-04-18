/**
 * SuiteLogistics - Módulo del Panel de Almacén y Despacho
 * * Gestiona la apertura de modales de logística y la subida segura
 * de comprobantes (POD), Facturas y Recibos Loyverse mediante FormData.
 */
const SuiteLogistics = (function($) {
    'use strict';

    // ==========================================
    // MÉTODOS PRIVADOS Y EVENT LISTENERS
    // ==========================================

    const bindEvents = function() {
		
		// --- UX FASE 5: Restricción numérica y formateo automático ---
        // 1. Bloquear letras y caracteres especiales en tiempo real
        $(document).on('input', '#disp-loyverse', function() {
            // Reemplaza cualquier cosa que NO sea un número (0-9) por nada ('')
            this.value = this.value.replace(/[^0-9]/g, ''); 
        });

        // 2. Formatear a 8 dígitos con ceros a la izquierda al salir del campo
        $(document).on('blur', '#disp-loyverse', function() {
            let val = $(this).val().trim();
            if (val.length > 0) {
                $(this).val(val.padStart(8, '0'));
            }
        });
        // --------------------------------------------------------------
        
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
        // 1. ABRIR MODAL Y CONFIGURAR UX FISCAL (Blindado contra doble clic)
        $(document).off('click', '.trigger-dispatch').on('click', '.trigger-dispatch', function(e) {
            e.preventDefault();
            $('#form-confirm-dispatch')[0].reset();
            
            const quoteId = $(this).data('id');
            const quoteCode = $(this).data('code');
            const isFiscal = $(this).data('fiscal');
            
            $('#disp-quote-id').val(quoteId);
            $('#dispatch-info-box').html(`<strong>Despachando Orden:</strong> #${quoteCode}`);
            
            const labelFactura = $('#label-factura-fiscal');
            const boxFactura = $('#box-factura-fiscal');
            
            if (isFiscal == 1 || isFiscal === true || isFiscal === '1') {
                boxFactura.css({'border-color': '#dc2626', 'background': '#fef2f2'});
                labelFactura.css('color', '#dc2626').text('📸 Subir Factura Fiscal (¡REQUERIDA!)');
                $('#disp-factura-file').prop('required', true); 
            } else {
                boxFactura.css({'border-color': '#e2e8f0', 'background': '#ffffff'});
                labelFactura.css('color', '#475569').text('📸 Adjuntar Factura Fiscal Física (Opcional)');
                $('#disp-factura-file').prop('required', false);
            }
            $('#modal-confirm-dispatch').fadeIn();
        });

        // 3. REFRESCAR TABLA (SPA - Sincronización Viva con Kanban)
        $('#btn-refresh-logistics').off('click').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const originalText = $btn.html();

            // UI Feedback
            $btn.html('⏳ Sincronizando...').prop('disabled', true);

            // Consumimos el endpoint general del Kanban para obtener la data fresca
            SuiteAPI.post('suite_get_kanban_data', {}).then(res => {
                if (res.success && res.data) {
                    let pedidos = res.data.por_enviar || [];

                    // Motor de Ordenamiento JS (Urgencia + FIFO)
                    pedidos.sort((a, b) => {
                        let prioA = parseInt(a.prioridad) || 0;
                        let prioB = parseInt(b.prioridad) || 0;
                        if (prioA !== prioB) return prioB - prioA; // Urgente va primero
                        return parseInt(a.id) - parseInt(b.id);    // FIFO
                    });

                    let html = '';
                    if (pedidos.length > 0) {
                        pedidos.forEach(p => {
                            let isFiscal = (p.requiere_factura == '1') ? 1 : 0;
                            let isRetencion = (p.agente_retencion == '1') ? 1 : 0;
                            let isUrgente = (p.prioridad == '1');
                            let comprobanteUrl = p.comprobante_pago_url || p.url_captura_pago || '#';
                            let tipoEnvio = p.tipo_envio || 'No especificado';
                            let direccion = p.direccion_envio || p.direccion_entrega || 'Sin dirección';

                            // Formatear fecha (YYYY-MM-DD HH:MM:SS a DD/MM/YYYY)
                            let fechaArr = p.fecha_emision ? p.fecha_emision.split(' ')[0].split('-') : ['','',''];
                            let fechaFmt = fechaArr.length === 3 ? `${fechaArr[2]}/${fechaArr[1]}/${fechaArr[0]}` : '';

                            // Badges Visuales
                            let urgenteBadge = isUrgente ? `<span style="background:#fee2e2; color:#dc2626; border: 1px solid #fca5a5; font-size:10px; font-weight:900; padding:2px 6px; border-radius:4px; box-shadow: 0 0 5px rgba(220, 38, 38, 0.4); margin-left:8px;">🚨 URGENTE</span>` : '';
                            
                            let fiscalTags = '';
                            if (isFiscal) fiscalTags += `<span style="background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; border: 1px solid #fca5a5; margin-bottom:5px; display:inline-block;">🧾 FACTURA FISCAL</span><br>`;
                            if (isRetencion) fiscalTags += `<span style="background: #ffedd5; color: #c2410c; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; border: 1px solid #fdba74; display:inline-block;">✂️ AGENTE RETENCIÓN</span>`;
                            if (!isFiscal && !isRetencion) fiscalTags = `<span style="color: #94a3b8; font-size: 12px; font-style: italic;">Estándar (Sin requisitos)</span>`;

                            // Botones de Acción Mantenidos Exactos
                            let btnPago = comprobanteUrl !== '#'
                                ? `<a href="${comprobanteUrl}" target="_blank" class="btn-modern-action" style="background: #dbeafe; color: #2563eb; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Ver Comprobante de Pago">💳 Pago</a>`
                                : `<span style="background: #f1f5f9; color: #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight:bold; cursor: not-allowed;" title="Sin comprobante adjunto">💳 Pago</span>`;

                            let printUrl = `${suite_vars.ajax_url}?action=suite_print_quote&id=${p.id}&nonce=${suite_vars.nonce}`;
                            let pickUrl = `${suite_vars.ajax_url}?action=suite_print_picking&id=${p.id}&nonce=${suite_vars.nonce}`;

                            html += `
                            <tr style="border-bottom: 1px solid #f1f5f9;" id="log-row-${p.id}">
                                <td style="padding: 15px;">
                                    <div style="display:flex; align-items:center; margin-bottom:4px;">
                                        <strong style="color: #0f172a; font-size: 15px;">#${p.codigo_cotizacion}</strong>
                                        ${urgenteBadge}
                                    </div>
                                    <span style="color: #64748b; font-size: 12px;">${fechaFmt}</span>
                                </td>
                                <td style="padding: 15px; font-weight: 500; color: #334155; max-width: 200px; white-space: normal; word-wrap: break-word;">
                                    👤 ${p.cliente_nombre || 'Sin Nombre'}
                                </td>
                                <td style="padding: 15px;">
                                    <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                        ${fiscalTags}
                                    </div>
                                </td>
                                <td style="padding: 15px; max-width: 250px; white-space: normal;">
                                    <strong style="color: #059669; font-size: 13px;">[${tipoEnvio.toUpperCase()}]</strong><br>
                                    <span style="color: #475569; font-size: 12px; line-height: 1.4; display: inline-block; margin-top: 4px;">
                                        ${direccion.replace(/\n/g, '<br>')}
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                        <a href="${printUrl}" target="_blank" class="btn-modern-action" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Imprimir Orden">🖨️ Orden</a>
                                        <a href="${pickUrl}" target="_blank" class="btn-modern-action" style="background: #f59e0b; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);" title="Generar Hoja de Picking">📋 Picking</a>
                                        ${btnPago}
                                        <button type="button" class="btn-modern-action trigger-dispatch" data-id="${p.id}" data-code="${p.codigo_cotizacion}" data-fiscal="${isFiscal}" style="background: #10b981; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);">📦 Despachar</button>
                                    </div>
                                </td>
                            </tr>`;
                        });
                    } else {
                        html = `<tr><td colspan="5" style="padding: 30px; text-align: center; color: #64748b; font-size: 15px;">No hay órdenes pendientes de despacho en este momento. ✅</td></tr>`;
                    }

                    $('#logisticsTable tbody').hide().html(html).fadeIn('fast');
                    $btn.html('✅ Tabla Actualizada');

                    setTimeout(() => { $btn.html(originalText).prop('disabled', false); }, 2000);

                } else {
                    throw new Error(res.data || 'Respuesta inválida del servidor');
                }
            }).catch(err => {
                alert('❌ Ocurrió un error al intentar sincronizar el almacén.');
                $btn.html(originalText).prop('disabled', false);
            });
        });
		
		
		
		
		
		
		
		
		
		
		
        // 4. PROCESAR SUBIDA (Integrando tu SuiteAPI y el UX de desvanecimiento)
        $('#form-confirm-dispatch').off('submit').on('submit', function(e) {
            e.preventDefault();

            // Bloquear botón para evitar múltiples envíos
            const btnSubmit = $(this).find('button[type="submit"]');
            const originalText = btnSubmit.html();
            btnSubmit.prop('disabled', true).text('⏳ Cifrando y Subiendo...');

            const quoteId = $('#disp-quote-id').val();
            
            // Instanciar FormData para envío de archivos (Multipart)
            const fd = new FormData();
            
            // Agregamos los campos de texto
            fd.append('quote_id', quoteId);
            fd.append('recibo_loyverse', $('#disp-loyverse').val());

            // Agregamos Archivos solo si fueron seleccionados
            // --- BARRERA DE PESO: Límite de 3.5MB para Telegram ---
            const maxSizeBytes = 3.5 * 1024 * 1024; 

            // Agregamos Archivos solo si fueron seleccionados y pesan menos de 3.5MB
            const facturaInput = $('#disp-factura-file')[0].files;
            if (facturaInput && facturaInput.length > 0) {
                if (facturaInput[0].size > maxSizeBytes) {
                    btnSubmit.prop('disabled', false).html(originalText); // Desbloqueamos el botón
                    return alert('❌ Error: La Factura Fiscal excede el límite de 3.5MB para Telegram. Por favor, comprima la imagen o el PDF e intente de nuevo.');
                }
                fd.append('factura_file', facturaInput[0]);
            }

            const podInput = $('#disp-pod-file')[0].files;
            if (podInput && podInput.length > 0) {
                if (podInput[0].size > maxSizeBytes) {
                    btnSubmit.prop('disabled', false).html(originalText); // Desbloqueamos el botón
                    return alert('❌ Error: La Guía de Encomienda (POD) excede el límite de 3.5MB para Telegram. Por favor, comprímala e intente de nuevo.');
                }
                fd.append('pod_file', podInput[0]);
            }

            // Usamos tu API unificada (El action ahora es 'suite_process_dispatch')
            SuiteAPI.postForm('suite_process_dispatch', fd).then(res => {
                if (res.success) {
                    
                    $('#modal-confirm-dispatch').fadeOut();
                    alert('✅ ' + (res.data.message || 'Pedido despachado exitosamente. Comisiones liberadas.'));
                    
                    // UX: Efecto visual de tu código original (Eliminar fila suavemente)
                    $('#log-row-' + quoteId).fadeOut('slow', function() { 
                        $(this).remove(); 
                        
                        // Sincronizar Kanban en background si la función existe
                        if (typeof SuiteKanban !== 'undefined' && typeof SuiteKanban.loadBoard === 'function') {
                            SuiteKanban.loadBoard();
                        }
                    });

                } else {
                    alert('❌ Error: ' + (res.data.message || res.data || 'Error desconocido.'));
                }
            }).catch(err => {
                console.error(err);
                alert('❌ Error de red o conexión al subir los archivos.');
            }).finally(() => {
                btnSubmit.prop('disabled', false).html(originalText);
            });
        });
    };

    // ==========================================
    // API PÚBLICA
    // ==========================================
    return {
        init: function() {
            bindEvents();
        }
    };

})(jQuery);

// Inicializar al cargar el DOM (Modo seguro de WordPress)
jQuery(document).ready(function($) {
    SuiteLogistics.init();
});