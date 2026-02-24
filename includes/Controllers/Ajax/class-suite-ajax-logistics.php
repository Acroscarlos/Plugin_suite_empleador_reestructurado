<?php
/**
 * Controlador AJAX: Logística y Despacho (Módulo 3)
 *
 * Maneja la subida del comprobante de entrega (Proof of Delivery) 
 * y la impresión blindada de Hojas de Picking para el almacén.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Endpoint 1: Subida de Guía / Foto de Entrega (Proof of Delivery)
 */
class Suite_Ajax_Upload_POD extends Suite_AJAX_Controller {

    protected $action_name = 'suite_upload_pod';
    protected $required_capability = 'read';

    protected function process() {
        $quote_id = isset( $_POST['quote_id'] ) ? intval( $_POST['quote_id'] ) : 0;
        
        if ( ! $quote_id || empty( $_FILES['pod_file'] ) ) {
            $this->send_error( 'Faltan datos o no se adjuntó la imagen del comprobante.' );
        }

        // Instanciar Modelo
        $quote_model = new Suite_Model_Quote();
        $order = $quote_model->get( $quote_id );

        if ( ! $order ) {
            $this->send_error( 'Pedido no encontrado.', 404 );
        }

        // 1. Cargar la librería nativa de WordPress para el manejo seguro de archivos
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $uploaded_file = $_FILES['pod_file'];
        $upload_overrides = array( 'test_form' => false );
        
        // 2. Ejecutar la subida (WordPress valida tipo de archivo, peso, y lo ubica en uploads/)
        $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $pod_url = esc_url_raw( $movefile['url'] );
            
            // 3. Guardar la URL en la base de datos
            $quote_model->update( $quote_id, [ 'pod_url' => $pod_url ] );
            
            // 4. Cambiar Estado a Despachado (Esto pasa por el Candado de Inmutabilidad)
            $result = $quote_model->update_order_status( $quote_id, 'despachado' );
            
            if ( is_wp_error( $result ) ) {
                $this->send_error( $result->get_error_message(), 500 );
            }
            
            $this->send_success( [ 
                'message' => 'Comprobante de entrega registrado. Pedido DESPACHADO.', 
                'url'     => $pod_url 
            ] );
            
        } else {
            $this->send_error( 'Error al subir el archivo: ' . $movefile['error'], 500 );
        }
    }
}

/**
 * Endpoint 2: Impresión de Hoja de Picking (Sin Precios)
 */
class Suite_Ajax_Print_Picking extends Suite_AJAX_Controller {

    protected $action_name = 'suite_print_picking';
    protected $required_capability = 'read';

    /**
     * Sobrescribimos el handle_request porque este endpoint no devuelve JSON,
     * sino que imprime HTML directo para la ventana de impresión del navegador.
     */
    public function handle_request() {
        // Validar Nonce vía GET
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'suite_quote_nonce' ) ) {
            wp_die( 'Enlace caducado o inválido por seguridad (CSRF).', 'Acceso Denegado', [ 'response' => 403 ] );
        }

        // Validar Permisos
        if ( ! current_user_can( $this->required_capability ) ) {
            wp_die( 'Privilegios insuficientes.', 'Acceso Denegado', [ 'response' => 401 ] );
        }

        $this->process();
    }

    protected function process() {
        global $wpdb;
        $quote_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        
        if ( ! $quote_id ) wp_die( 'ID de pedido inválido.' );

        $quote_model = new Suite_Model_Quote();
        $cot = $quote_model->get( $quote_id );
        
        if ( ! $cot ) wp_die( 'Pedido no encontrado en la base de datos.' );

        // Obtener Items (Detalles)
        $table_items = $wpdb->prefix . 'suite_cotizaciones_items';
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_items} WHERE cotizacion_id = %d", $quote_id ) );

        // =========================================================
        // RENDER DE VISTA DE IMPRESIÓN (SOLO HTML)
        // =========================================================
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
                
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                }
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