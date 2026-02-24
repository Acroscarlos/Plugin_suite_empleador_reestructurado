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
        // 1. SEGURIDAD: Control Estricto de Rol (Solo Logística y Admin)
        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', (array) $user->roles );

        if ( ! $is_admin && ! $is_logistica ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'violacion_acceso', "Intento de escalada de privilegios en POD por el usuario " . get_current_user_id() );
            }
            $this->send_error( 'Acceso Denegado: Solo el personal de almacén/logística puede confirmar despachos.', 403 );
        }

        $quote_id = isset( $_POST['quote_id'] ) ? intval( $_POST['quote_id'] ) : 0;
        
        if ( ! $quote_id ) {
            $this->send_error( 'ID de pedido inválido.', 400 );
        }

        // Validar que se envió un archivo
        if ( empty( $_FILES['pod_file'] ) ) {
            $this->send_error( 'No se recibió ningún archivo.', 400 );
        }

        $file = $_FILES['pod_file'];

        // 2. CANDADO DE INMUTABILIDAD
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';
        $current_status = $wpdb->get_var( $wpdb->prepare( "SELECT estado FROM {$tabla_cot} WHERE id = %d", $quote_id ) );

        if ( strtolower( $current_status ) === 'despachado' ) {
            $this->send_error( 'El pedido ya se encuentra despachado.', 403 );
        }

        // Configurar el entorno para la subida nativa de WP
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $upload_overrides = [ 'test_form' => false ];
        $movefile = wp_handle_upload( $file, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $file_url = $movefile['url'];

            // Actualizar la base de datos
            $wpdb->update(
                $tabla_cot,
                [ 
                    'pod_url' => escapeshellurl( $file_url ), // sanitizamos URL
                    'estado'  => 'despachado' 
                ],
                [ 'id' => $quote_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            // DESCUENTO DE INVENTARIO LOGÍSTICO (Si aplica en tu flujo)
            $quote_model = new Suite_Model_Quote();
            $quote_model->process_inventory_discount( $quote_id );

            $this->send_success( [
                'message' => 'Comprobante subido y pedido despachado correctamente.',
                'url'     => $file_url
            ] );
        } else {
            // PREVENCIÓN INFO DISCLOSURE: Loguear internamente, pero mostrar un mensaje genérico al usuario
            error_log( 'Suite ERP Upload Error: ' . $movefile['error'] );
            $this->send_error( 'Error de servidor al procesar el archivo. Contacte a soporte técnico.', 500 );
        }
    }
}

/**
 * Endpoint 2: Impresión de Hoja de Picking (Sin Precios)
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
        $is_logistica = in_array( 'suite_logistica', $roles );

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

        // RENDER DE VISTA DE IMPRESIÓN (SOLO HTML CORREGIDO)
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