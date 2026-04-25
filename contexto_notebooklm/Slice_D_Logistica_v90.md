# 🧩 MÓDULO LÓGICO: Slice_D_Logistica

### ARCHIVO: `assets/js/modules/logistics.js`
```js
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

		// 2. CERRAR MODAL Y LIMPIAR (Restaurado)
        $('#close-modal-dispatch, #btn-cancel-dispatch').off('click').on('click', function(e) {
            e.preventDefault();
            
            // Ocultar el modal
            $('#modal-confirm-dispatch').fadeOut('fast');
            
            // Limpiar el formulario y resetear estilos de la caja fiscal
            $('#form-confirm-dispatch')[0].reset();
            $('#box-factura-fiscal').css({'border-color': '#e2e8f0', 'background': '#ffffff'});
            $('#label-factura-fiscal').css('color', '#475569').text('📸 Adjuntar Factura Fiscal Física (Opcional)');
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
                            // Ocultar botón si es WooCommerce
                            let btnPago = (comprobanteUrl !== '#' && p.canal_venta !== 'WooCommerce Web')
                                ? `<a href="${comprobanteUrl}" target="_blank" class="btn-modern-action" style="background: #dbeafe; color: #2563eb; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Ver Comprobante de Pago">💳 Pago</a>`
                                : (p.canal_venta === 'WooCommerce Web' ? '' : `<span style="background: #f1f5f9; color: #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight:bold; cursor: not-allowed;" title="Sin comprobante adjunto">💳 Pago</span>`);

                            // NUEVO: Preparar info de pago para la columna de detalles
                            let infoPagoWoo = (p.canal_venta === 'WooCommerce Web' && p.comprobante_pago_url) 
                                ? `<div style="margin-top: 8px; padding: 5px; background: #f0fdf4; border-radius: 4px; border: 1px solid #bbf7d0; font-size: 11px; color: #166534;"><strong>💳 Pago:</strong> ${p.comprobante_pago_url}</div>` 
                                : '';
							
							
							
							

                            let printUrl = `${suite_vars.ajax_url}?action=suite_print_quote&id=${p.id}&nonce=${suite_vars.nonce}`;
                            let pickUrl = `${suite_vars.ajax_url}?action=suite_print_picking&id=${p.id}&nonce=${suite_vars.nonce}`;
							
							
							// NUEVO: Botón Global de WhatsApp
                            let waPhone = p.telefono_cliente || p.wa_phone || ''; // Intentamos extraer el teléfono
                            let btnWa = '';
                            if (waPhone) {
                                // Limpiamos el número para la API de WhatsApp
                                let cleanPhone = waPhone.replace(/[^0-9+]/g, '');
                                let msg = encodeURIComponent(`Hola ${p.cliente_nombre}, le contactamos del equipo de despachos de UNI-T respecto a su orden #${p.codigo_cotizacion}. `);
                                btnWa = `<a href="https://api.whatsapp.com/send?phone=${cleanPhone}&text=${msg}" target="_blank" class="btn-modern-action" style="background: #10b981; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);" title="Contactar por WhatsApp">📱 Chat</a>`;
                            }
							
							
							
							
							
							
							
							
							

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
                                    ${infoPagoWoo}
                                </td>



                                <td style="padding: 15px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                        ${btnWa}
                                        <a href="${printUrl}" target="_blank" class="btn-modern-action" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Imprimir Orden">🖨️ Orden</a>
                                        <a href="${pickUrl}" target="_blank" class="btn-modern-action" style="background: #f59e0b; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);" title="Generar Hoja de Picking">📋 Picking</a>
                                        ${btnPago}
                                        <button type="button" class="btn-modern-action trigger-dispatch" data-id="${p.id}" data-code="${p.codigo_cotizacion}" data-fiscal="${isFiscal}" style="background: #4f46e5; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);">📦 Despachar</button>
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
```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-logistics.php`
```php
<?php
/**
 * Controlador AJAX: Logística y Despacho (Módulo 3)
 *
 * Maneja la subida del comprobante de entrega (POD), facturas fiscales,
 * el registro del ID Loyverse y la impresión de Hojas de Picking.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Endpoint 1: Procesamiento de Despacho (Fase 5)
 * Nota: Se mantiene el nombre de la clase por retrocompatibilidad con el init principal,
 * pero la acción AJAX ahora es 'suite_process_dispatch'.
 */
class Suite_Ajax_Upload_POD extends Suite_AJAX_Controller {

    protected $action_name = 'suite_process_dispatch'; // <-- IMPORTANTE: Conectado con logistics.js
    protected $required_capability = 'read';

    protected function process() {
        // 1. SEGURIDAD: Control Estricto de Rol (Solo Logística y Admin)
        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
		$is_logistica = in_array( 'suite_logistica', (array) $user->roles ) || current_user_can( 'suite_view_logistics' );
        if ( ! $is_admin && ! $is_logistica ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'violacion_acceso', "Intento de escalada de privilegios en Despacho por el usuario " . get_current_user_id() );
            }
            $this->send_error( 'Acceso Denegado: Solo el personal de almacén/logística puede confirmar despachos.', 403 );
        }

        // 2. RECEPCIÓN DE DATOS BÁSICOS Y FORMATEO
        $quote_id = isset( $_POST['quote_id'] ) ? intval( $_POST['quote_id'] ) : 0;
        // Limpiamos espacios laterales desde el inicio
        $recibo_loyverse = isset( $_POST['recibo_loyverse'] ) ? trim( sanitize_text_field( $_POST['recibo_loyverse'] ) ) : '';
        
        if ( ! $quote_id ) {
            $this->send_error( 'ID de pedido inválido.', 400 );
        }

        if ( empty( $recibo_loyverse ) ) {
            $this->send_error( 'El N° de Recibo Loyverse es obligatorio para auditar la orden.', 400 );
        }

        // --- BARRERA DE SEGURIDAD: EXCLUSIVAMENTE NÚMEROS ---
        if ( ! preg_match( '/^[0-9]+$/', $recibo_loyverse ) ) {
            $this->send_error( 'Acceso Denegado: El N° de Recibo Loyverse debe contener ÚNICAMENTE números.', 400 );
        }

        // --- MAGIA FASE 5: Relleno de ceros a la izquierda (Exactamente 8 dígitos) ---
        $recibo_loyverse = str_pad( $recibo_loyverse, 8, '0', STR_PAD_LEFT );

		
		
        // 3. CANDADO DE INMUTABILIDAD
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';
        $quote_data = $wpdb->get_row( $wpdb->prepare( "SELECT estado, vendedor_id, total_usd, codigo_cotizacion FROM {$tabla_cot} WHERE id = %d", $quote_id ) );

		
		
		
        if ( ! $quote_data ) {
            $this->send_error( 'El pedido no existe.', 404 );
        }

        if ( strtolower( $quote_data->estado ) === 'despachado' ) {
            $this->send_error( 'El pedido ya se encuentra despachado.', 403 );
        }

        // 4. CONFIGURACIÓN DE ARCHIVOS
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        $upload_overrides = [ 'test_form' => false ];

        $factura_url = '';
        $pod_url = '';
        
        // Límite estricto de 3.5MB para evitar Timeouts con la API de Telegram
        $max_size_bytes = 3.5 * 1024 * 1024; 

        // Procesar Factura (Opcional)
        if ( ! empty( $_FILES['factura_file']['name'] ) ) {
            // 🛡️ BARRERA 1: Peso de Factura y Errores de Servidor
            if ( $_FILES['factura_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['factura_file']['size'] > $max_size_bytes ) {
                $this->send_error( 'La Factura Fiscal excede el límite de peso estricto (3.5MB).', 400 );
                return; // <--- 🛑 FRENO DE EMERGENCIA VITAL
            }
            
            $move_factura = wp_handle_upload( $_FILES['factura_file'], $upload_overrides );
            
            if ( $move_factura && ! isset( $move_factura['error'] ) ) {
                $factura_url = $move_factura['url'];
            } else {
                $this->send_error( 'Error al subir la Factura Fiscal: ' . $move_factura['error'], 500 );
                return;
            }
        }

        // Procesar POD (Opcional)
        if ( ! empty( $_FILES['pod_file']['name'] ) ) {
            // 🛡️ BARRERA 2: Peso del POD y Errores de Servidor
            if ( $_FILES['pod_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['pod_file']['size'] > $max_size_bytes ) {
                $this->send_error( 'La Guía de Encomienda (POD) excede el límite de peso estricto (3.5MB).', 400 );
                return; // <--- 🛑 FRENO DE EMERGENCIA VITAL
            }

            $move_pod = wp_handle_upload( $_FILES['pod_file'], $upload_overrides );
            
            if ( $move_pod && ! isset( $move_pod['error'] ) ) {
                $pod_url = $move_pod['url'];
            } else {
                $this->send_error( 'Error al subir la Guía de Encomienda: ' . $move_pod['error'], 500 );
                return;
            }
        }

        // 5. 💰 MAGIA FASE 5: BARRERA DE COMISIÓN (DEBE IR ANTES DE ACTUALIZAR LA ORDEN)
        if ( class_exists( 'Suite_Model_Commission' ) ) {
            $commission_model = new Suite_Model_Commission();
            
            if ( method_exists( $commission_model, 'registrar_comision_despacho' ) ) {
                $resultado_comision = $commission_model->registrar_comision_despacho( 
                    $quote_id, 
                    $quote_data->vendedor_id, 
                    $quote_data->total_usd, 
                    $recibo_loyverse 
                );

                // ESCUDO ACTIVADO: Si la comisión rebota (ej. Recibo duplicado), ABORTAMOS AQUÍ.
                if ( is_wp_error( $resultado_comision ) ) {
                    $this->send_error( 'Fallo de Auditoría: ' . $resultado_comision->get_error_message(), 400 );
                }
            } else {
                error_log("Suite INFO: El método registrar_comision_despacho aún no existe.");
            }
        }

        // 6. ACTUALIZACIÓN EN BASE DE DATOS (Solo se ejecuta si la comisión pasó limpia)
        $data_to_update = [
            'estado'          => 'despachado',
            'recibo_loyverse' => $recibo_loyverse
        ];
        $format = [ '%s', '%s' ];

        if ( ! empty( $factura_url ) ) {
            $data_to_update['factura_fiscal_url'] = esc_url_raw( $factura_url );
            $format[] = '%s';
        }

        if ( ! empty( $pod_url ) ) {
            $data_to_update['pod_url'] = esc_url_raw( $pod_url );
            $format[] = '%s';
        }

        $updated = $wpdb->update(
            $tabla_cot,
            $data_to_update,
            [ 'id' => $quote_id ],
            $format,
            [ '%d' ]
        );

        if ( $updated === false ) {
            $this->send_error( 'Ocurrió un error al actualizar la base de datos.', 500 );
        }

        // --- 📧 FASE B: NOTIFICACIÓN AUTOMÁTICA AL CLIENTE (CORREO) ---
        if ( class_exists('Suite_Email_Engine') ) {
            $email_engine = new Suite_Email_Engine();
            $email_engine->send_dispatch_notification($quote_id);
        }
        // --------------------------------------------------------------

        // --- 🚀 DISPARO A TELEGRAM (FACTURA FISCAL) ---
        // Si el despachador subió una Factura, le avisamos a Contabilidad
        if ( ! empty( $factura_url ) ) {
            if ( ! class_exists('Suite_Telegram_Bot') ) {
                require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-quotes.php';
            }
            if ( class_exists('Suite_Telegram_Bot') ) {
                $telegram = new Suite_Telegram_Bot();
                $telegram->send_fiscal_document( $quote_id, $quote_data->codigo_cotizacion, $quote_data->vendedor_id, 'Factura Fiscal Logística', $factura_url );
            }
        }
        // ----------------------------------------------

        $this->send_success( [
            'message' => "✅ Despacho procesado (Loyverse: $recibo_loyverse). El cliente ha sido notificado por correo."
        ] );
		
		
		
		
		
		
		
		
    }
}

/**
 * Endpoint 2: Impresión de Hoja de Picking (Sin Precios)
 * Nota: Mantenido intacto por solicitud de arquitectura.
 */
class Suite_Ajax_Print_Picking extends Suite_AJAX_Controller {

    protected $action_name = 'suite_print_picking';
    protected $required_capability = 'read';

    public function handle_request() {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'suite_quote_nonce' ) ) {
            wp_die( 'Enlace caducado o inválido por seguridad (CSRF).', 'Acceso Denegado', [ 'response' => 403 ] );
        }
        
        $this->process();
    }

    protected function process() {
        // SEGURIDAD: Control Estricto de Rol para Logística
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', $roles ) || current_user_can( 'suite_view_logistics' );

        if ( ! $is_admin && ! $is_logistica ) {
            wp_die( 'Privilegios insuficientes. Se requiere el rol de Logística para generar hojas de picking.', 'Acceso Denegado', [ 'response' => 403 ] );
        }

        global $wpdb;
        $quote_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        
        if ( ! $quote_id ) wp_die( 'ID de pedido inválido.' );

        $quote_model = new Suite_Model_Quote();
        $cot = $quote_model->get( $quote_id );
        
        if ( ! $cot ) wp_die( 'Pedido no encontrado en la base de datos.' );

        $table_items = $wpdb->prefix . 'suite_cotizaciones_items';
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_items} WHERE cotizacion_id = %d", $quote_id ) );

        // RENDER DE VISTA DE IMPRESIÓN
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Picking_#<?php echo esc_attr( $cot->codigo_cotizacion ); ?></title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #111827; }
                .header { text-align: center; border-bottom: 3px solid #0f172a; padding-bottom: 15px; margin-bottom: 25px; }
                .header h2 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
                .header h3 { margin: 5px 0 0 0; font-size: 18px; color: #475569; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
                .info-box { border: 2px solid #e2e8f0; padding: 15px; border-radius: 8px; }
                .info-box p { margin: 5px 0; font-size: 14px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #cbd5e1; padding: 12px; text-align: left; font-size: 14px; }
                th { background-color: #f8fafc; text-transform: uppercase; font-size: 12px; color: #64748b; }
                .qty-box { font-size: 18px; font-weight: bold; text-align: center; }
                .check-box { width: 40px; text-align: center; }
                .footer-signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; }
                .sign-line { border-top: 1px dashed #94a3b8; padding-top: 10px; text-align: center; font-size: 13px; font-weight: bold; color: #475569; }
                @media print { body { -webkit-print-color-adjust: exact; } }
            </style>
        </head>
        <body onload="window.print()">
            <div class="header">
                <h2>📦 HOJA DE PICKING Y DESPACHO</h2>
                <h3>Orden / Pedido: #<?php echo esc_html( $cot->codigo_cotizacion ); ?></h3>
            </div>
            
            <div class="info-grid">
                <div class="info-box">
                    <p><strong>Destinatario:</strong> <?php echo esc_html( $cot->cliente_nombre ); ?></p>
                    <p><strong>RIF / DNI:</strong> <?php echo esc_html( $cot->cliente_rif ); ?></p>
                    <p><strong>Método Entrega:</strong> <?php echo esc_html( $cot->metodo_entrega ? $cot->metodo_entrega : 'N/A' ); ?></p>
                </div>
                <div class="info-box">
                    <p><strong>Fecha Generación:</strong> <?php echo date('d/m/Y h:i A'); ?></p>
                    <p><strong>Dirección:</strong></p>
                    <p style="margin-top: 2px; color: #334155; font-style: italic;">
                        <?php echo nl2br( esc_html( $cot->direccion_entrega ) ); ?>
                    </p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">SKU</th>
                        <th style="width: 60%;">Descripción del Producto (Ubicación)</th>
                        <th style="width: 15%; text-align: center;">Cantidad</th>
                        <th class="check-box">Verif.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td style="font-family: monospace; font-size: 15px;"><strong><?php echo esc_html( $item->sku ); ?></strong></td>
                        <td><?php echo esc_html( $item->producto_nombre ); ?></td>
                        <td class="qty-box"><?php echo intval( $item->cantidad ); ?></td>
                        <td class="check-box"><div style="width:20px; height:20px; border: 2px solid #ccc; margin: 0 auto;"></div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer-signatures">
                <div class="sign-line">Preparado y Embalado por (Firma)</div>
                <div class="sign-line">Despachado / Entregado por (Firma)</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
```

### ARCHIVO: `views/app/tab-logistica.php`
```php
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
                <?php 
                // 🧠 MOTOR DE ORDENAMIENTO ESTRICTO (Urgentes primero, luego FIFO)
                if ( ! empty( $pedidos_logistica ) ) {
                    usort( $pedidos_logistica, function( $a, $b ) {
                        $prio_a = isset( $a->prioridad ) ? intval( $a->prioridad ) : 0;
                        $prio_b = isset( $b->prioridad ) ? intval( $b->prioridad ) : 0;

                        // 1. Regla de Urgencia (Prioridad 1 va primero)
                        if ( $prio_a !== $prio_b ) {
                            return $prio_b - $prio_a;
                        }
                        // 2. Regla FIFO (ID menor/más viejo va primero)
                        return intval( $a->id ) - intval( $b->id );
                    });
                }
                ?>

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
								<?php if ( $pedido->canal_venta === 'WooCommerce Web' && ! empty( $pedido->comprobante_pago_url ) ) : ?>
									<div style="margin-top: 8px; padding: 5px; background: #f0fdf4; border-radius: 4px; border: 1px solid #bbf7d0; font-size: 11px; color: #166534;">
										<strong>💳 Pago:</strong> <?php echo esc_html( $pedido->comprobante_pago_url ); ?>
									</div>
								<?php endif; ?>
							</td>
							
							
							
                            
                            <td style="padding: 15px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=suite_print_quote&id=' . $pedido->id . '&nonce=' . wp_create_nonce('suite_quote_nonce') ) ); ?>" target="_blank" class="btn-modern-action" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;" title="Imprimir Orden">
                                        🖨️ Orden
                                    </a>
                                    
                                    <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=suite_print_picking&id=' . $pedido->id . '&nonce=' . wp_create_nonce('suite_quote_nonce') ) ); ?>" target="_blank" class="btn-modern-action" style="background: #f59e0b; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);" title="Generar Hoja de Picking">
                                        📋 Picking
                                    </a>
                                    
									
									
									
                                    <?php if ( $has_comprobante && $pedido->canal_venta !== 'WooCommerce Web' ) : ?>
                                        <a href="<?php 
echo esc_url( $comprobante_url ); ?>" target="_blank" class="btn-modern-action" style="background: #dbeafe; color: #2563eb; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight:bold;"
title="Ver Comprobante de Pago">
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
```

