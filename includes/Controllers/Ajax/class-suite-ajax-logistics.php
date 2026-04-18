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
            'message' => "✅ Despacho procesado (Loyverse: $recibo_loyverse). Comisiones en espera de auditoría."
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