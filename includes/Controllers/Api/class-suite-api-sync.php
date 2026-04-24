<?php
/**
 * Controlador API REST: Sincronización Segura de Archivos (FASE 1)
 *
 * Endpoint que recibe y valida los archivos CSV generados por el servidor
 * externo de procesamiento de datos, asegurando la integridad del ecosistema WordPress.
 *
 * @package SuiteEmpleados\Controllers\Api
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_API_Sync {

    /**
     * Constructor: Registra la inicialización de la API REST
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Define el namespace y el endpoint
     */
    public function register_routes() {
        register_rest_route( 'suite/v1', '/sync-csv', [
            'methods'             => WP_REST_Server::CREATABLE, // Equivalente a POST
            'callback'            => [ $this, 'process_sync' ],
            'permission_callback' => [ $this, 'check_permissions' ]
        ] );
    }

    /**
     * Middleware de Seguridad: Validación de Token
     * Protege el endpoint validando un header personalizado (Zero-Trust)
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        // La API de WordPress normaliza los headers a minúsculas
        $token_enviado = $request->get_header( 'x-suite-sync-token' );

        if ( ! defined( 'SUITE_SYNC_SECRET' ) ) {
            return new WP_Error( 
                'rest_forbidden', 
                'Configuración incompleta: La constante SUITE_SYNC_SECRET no está definida en el servidor wp-config.php.', 
                [ 'status' => 500 ] 
            );
        }

        if ( empty( $token_enviado ) ) {
            return new WP_Error( 
                'rest_unauthorized', 
                'Acceso Denegado: Header X-Suite-Sync-Token ausente.', 
                [ 'status' => 401 ] 
            );
        }

        // Validación blindada contra ataques de tiempo (Timing Attacks)
        if ( ! hash_equals( SUITE_SYNC_SECRET, $token_enviado ) ) {
            // Logueamos la intrusión para auditoría
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'violacion_api', 'Intento de acceso al endpoint sync-csv con token inválido desde IP: ' . $_SERVER['REMOTE_ADDR'] );
            }

            return new WP_Error( 
                'rest_unauthorized', 
                'Acceso Denegado: Token de sincronización inválido.', 
                [ 'status' => 401 ] 
            );
        }

        return true;
    }

    /**
     * Lógica de Procesamiento: Validación estricta y guardado de archivos
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function process_sync( WP_REST_Request $request ) {
        $files = $request->get_file_params();

        // 1. Validar presencia del único archivo unificado
        if ( empty( $files['matriz_unificada'] ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Falta el archivo requerido. Se espera el campo "matriz_unificada".'
            ], 400 );
        }

        $file_matriz = $files['matriz_unificada'];

        // 2. Validar estructura, mime type y extensiones
        $val_matriz = $this->validate_csv( $file_matriz );
        if ( is_wp_error( $val_matriz ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Error en matriz_unificada: ' . $val_matriz->get_error_message() ], 400 );
        }

        // 3. Preparar el directorio de destino
        $output_dir = SUITE_PATH . 'output/';
        if ( ! file_exists( $output_dir ) ) {
            wp_mkdir_p( $output_dir );
            
            // Prevención de ejecución (Defense in Depth)
            file_put_contents( $output_dir . 'index.php', '<?php // Silence is golden.' );
            file_put_contents( $output_dir . '.htaccess', 'Deny from all' );
        }

        // 4. Guardar archivo (Sobrescribiendo nombre original para seguridad)
        $path_matriz = $output_dir . 'Matriz_unificada_Woocommerce.csv';
        $move_matriz = move_uploaded_file( $file_matriz['tmp_name'], $path_matriz );

        if ( $move_matriz ) {
            return new WP_REST_Response( [
                'success' => true,
                'message' => 'Sincronización de CSV unificado ejecutada correctamente.'
            ], 200 );
        } else {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error de sistema de archivos al intentar guardar el CSV.'
            ], 500 );
        }
    }

    /**
     * Validador estricto de archivos CSV
     * Previene subida de código malicioso enmascarado
     *
     * @param array $file Array del archivo desde $_FILES
     * @return true|WP_Error
     */
    private function validate_csv( $file ) {
        // Chequeo de error de subida de PHP
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Código de error interno: ' . $file['error'] );
        }

        // Validación de Extensión
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            return new WP_Error( 'invalid_ext', 'La extensión del archivo debe ser .csv' );
        }

        // Validación de Tipo MIME Real (Leyendo los bytes del archivo, no la cabecera HTTP)
        if ( function_exists( 'finfo_open' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime_real = finfo_file( $finfo, $file['tmp_name'] );
            finfo_close( $finfo );

            $mimes_permitidos = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ];
            if ( ! in_array( $mime_real, $mimes_permitidos, true ) ) {
                return new WP_Error( 'invalid_mime', 'Fallo de seguridad: El contenido real del archivo no coincide con texto plano o CSV. (Detectado: ' . $mime_real . ')' );
            }
        }

        return true;
    }
}



/**
 * Integración Oficial: WooCommerce -> Suite ERP Kanban
 * Patrón: Event-Driven, Soporte HPOS, Telegram Webhook y Logística Dinámica
 */
class Suite_WooCommerce_Integration {

    public function __construct() {
        add_action( 'woocommerce_order_status_processing', [ $this, 'sync_order_to_kanban' ], 20, 2 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'sync_order_to_kanban' ], 20, 2 );
        add_action( 'wp_ajax_suite_test_woo_sync', [ $this, 'debug_sync_manually' ] );
    }

    public function debug_sync_manually() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die('No autorizado');
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $order = wc_get_order($order_id);
        if ( !$order ) wp_die("Orden #{$order_id} no encontrada.");

        echo "<h2>🔍 Diagnóstico de Sincronización - Orden #{$order_id}</h2>";
        $this->sync_order_to_kanban($order_id, $order, true); 
        wp_die("<br><br><b>✅ Prueba finalizada.</b>");
    }

    public function sync_order_to_kanban( $order_id, $order, $debug_mode = false ) {
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';
        $tabla_cli = $wpdb->prefix . 'suite_clientes';
        $tabla_items = $wpdb->prefix . 'suite_cotizaciones_items';

        $codigo_woo = 'WOO-' . $order_id;
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tabla_cot} WHERE codigo_cotizacion = %s", $codigo_woo ) );
        if ( $exists && !$debug_mode ) return; 

        // 1. EXTRAER DATOS BDV Y WOOCOMMERCE
        $ref_bdv   = $order->get_meta( '_pago_referencia' );
        $ced_bdv   = $order->get_meta( '_pago_cedula' );
        $monto_ves = $order->get_meta( '_pago_monto_ves' );
        $fecha_pag = $order->get_meta( '_pago_fecha' );
        $timestamp = $order->get_meta( '_pago_timestamp' );
        $tel_pagador = $order->get_meta( '_pago_telefono' ); // <--- NUEVO: Teléfono del BDV
        $info_logistica = $order->get_meta( '_detalles_entrega_logistica' );
        
        $total_usd = $order->get_total();
        
        // Prioridad Absoluta: 1. Cédula BDV | 2. Empresa Woo | 3. S/N
        $ced_bdv_clean = trim( (string) $ced_bdv );
        $company_woo   = trim( $order->get_billing_company() );
        
        // Si la cédula tiene solo números, le ponemos el prefijo V por defecto
        if ( !empty($ced_bdv_clean) ) {
            $rif_cliente = is_numeric($ced_bdv_clean) ? 'V' . $ced_bdv_clean : $ced_bdv_clean;
        } else {
            $rif_cliente = !empty($company_woo) ? $company_woo : 'S/N';
        }
		
		
		
        
        $email_cliente = trim( $order->get_billing_email() );
		
		
        $telefono_cliente = $order->get_billing_phone(); // <--- NUEVO: Teléfono de la Orden
        $nombre_cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        
        // 2. LÓGICA LOGÍSTICA DINÁMICA
        $shipping_methods = $order->get_shipping_methods();
        $tipo_envio = 'Encomienda Web'; 
        
        foreach ( $shipping_methods as $method ) {
            $name_lower = strtolower( $method->get_name() );
            if ( strpos($name_lower, 'colecci') !== false || strpos($name_lower, 'retiro') !== false || strpos($method->get_method_id(), 'pickup') !== false ) {
                $tipo_envio = 'Retiro en Tienda';
                break;
            } else {
                $tipo_envio = $method->get_name(); // Toma el nombre real (Ej: Zoom, Tealca)
            }
        }

        // 3. CONSTRUCCIÓN DEL MANIFIESTO LOGÍSTICO (Dirección + BDV + Teléfonos)
        $direccion_base = $order->get_shipping_address_1() . ', ' . $order->get_shipping_city();
        if ( trim($direccion_base, " ,") == "" ) {
            $direccion_base = "Datos de Facturación: " . $order->get_billing_address_1() . ', ' . $order->get_billing_city();
        }
        
        if ( $tipo_envio === 'Retiro en Tienda' ) {
            $direccion_base = "🏢 RETIRO EN TIENDA (El cliente pasará buscando su pedido)";
        }

        // Ensamblaje final para la columna "Detalles de Entrega"
        
		
		
		
		// Ensamblaje final para la columna "Detalles de Entrega"
        $direccion_final = $direccion_base . "\n\n=== CONTACTO ===";
        $direccion_final .= "\n📞 Teléfono Cliente: " . ($telefono_cliente ?: 'N/D');
        
        // Forzar siempre la muestra de los datos del pagador si existen
        if ( !empty($tel_pagador) ) {
            $direccion_final .= "\n📱 Teléfono Pagador: " . $tel_pagador;
        }
        if ( !empty($ced_bdv_clean) ) {
            $direccion_final .= "\n🪪 Cédula Pagador: " . $ced_bdv_clean;
        }
        
        $direccion_final .= "\n\n=== VERIFICACIÓN BDV ===\n" . $info_logistica;
		
		

        // Vendedor Directo
        $admin_user = get_user_by( 'email', 'carlosac@unitvenezuela.com' );
        if ( ! $admin_user ) $admin_user = get_user_by( 'email', 'respaldounit000@gmail.com' );
        $vendedor_id = $admin_user ? $admin_user->ID : 1;

        $cotizacion_id = 0;

        if ( !$debug_mode ) {
            
            
            
            // 4. TRANSACCIÓN ATÓMICA
            $wpdb->query('START TRANSACTION');
            try {
                // Búsqueda Inteligente: Ignora la validación por RIF si es 'S/N' para no mezclar clientes
                $query_crm = "SELECT id FROM {$tabla_cli} WHERE email = %s";
                if ( $rif_cliente !== 'S/N' && !empty($rif_cliente) ) {
                    $query_crm .= " OR (rif_ci = %s AND rif_ci != 'S/N')";
                    $cliente_id = $wpdb->get_var( $wpdb->prepare( $query_crm, $email_cliente, $rif_cliente ) );
                } else {
                    $cliente_id = $wpdb->get_var( $wpdb->prepare( $query_crm, $email_cliente ) );
                }

                if ( ! $cliente_id ) {
                    $wpdb->insert( $tabla_cli, [
						
						
						
                        'nombre_razon' => $nombre_cliente, 'rif_ci' => $rif_cliente, 'email' => $email_cliente,
                        'telefono' => $telefono_cliente, 'direccion' => $direccion_base, 'vendedor_id' => $vendedor_id
                    ]);
                    $cliente_id = $wpdb->insert_id;
                }

                // Generamos JSON para el recibo verde
                $datos_bdv_json = json_encode([
                    'is_bdv' => true,
                    'ref' => $ref_bdv,
                    'ves' => $monto_ves,
                    'ci'  => $ced_bdv,
                    'ts'  => $timestamp ?: $fecha_pag
                ]);

                $wpdb->insert( $tabla_cot, [
                    'codigo_cotizacion' => $codigo_woo,
                    'cliente_nombre'    => $nombre_cliente,
                    'cliente_rif'       => $rif_cliente,
                    'cliente_id'        => $cliente_id,
                    'direccion_entrega' => $direccion_final, // <--- Carga Destino + Teléfonos + BDV
                    'total_usd'         => $total_usd,
                    'vendedor_id'       => $vendedor_id,
                    'estado'            => 'pagado', 
                    'canal_venta'       => 'WooCommerce Web',
                    'forma_pago'        => 'BDV Conciliación',
                    'comprobante_pago_url' => $datos_bdv_json, 
                    'tipo_envio'        => $tipo_envio, // <--- Automáticamente dirá Retiro o Encomienda
                    'agente_retencion'  => 0,
                    'requiere_factura'  => 0 
                ]);
                $cotizacion_id = $wpdb->insert_id;

                foreach ( $order->get_items() as $item_id => $item ) {
                    $product = $item->get_product();
                    $wpdb->insert( $tabla_items, [
                        'cotizacion_id'       => $cotizacion_id,
                        'sku'                 => $product ? $product->get_sku() : 'WEB-ITEM',
                        'producto_nombre'     => $item->get_name(),
                        'cantidad'            => $item->get_quantity(),
                        'precio_unitario_usd' => $item->get_subtotal() / $item->get_quantity(),
                        'subtotal_usd'        => $item->get_total()
                    ]);
                }
                $wpdb->query('COMMIT');

            } catch ( Exception $e ) {
                $wpdb->query('ROLLBACK');
                error_log( 'Error integrando Woo-Kanban: ' . $e->getMessage() );
            }
        } else {
            $cotizacion_id = 9999;
        }

        // 5. TELEGRAM: ALERTAS LOGÍSTICAS Y DE TELÉFONO
        // Alerta visual de retiro para el equipo
        $alerta_logistica = ($tipo_envio === 'Retiro en Tienda') ? "🏢 <b>¡ATENCIÓN: EL CLIENTE RETIRA EN TIENDA!</b>\n" : "🚚 <b>Modalidad:</b> {$tipo_envio}\n";

        $msg = "🛒 <b>¡NUEVA VENTA WEB (BDV)!</b>\n\n";
        $msg .= "🛍️ <b>Orden Woo:</b> #{$order_id}\n";
        $msg .= "👤 <b>Cliente:</b> {$nombre_cliente}\n";
        $msg .= "📞 <b>Telf. Cliente:</b> {$telefono_cliente}\n";
        if ( $tel_pagador && $tel_pagador !== $telefono_cliente ) {
            $msg .= "📱 <b>Telf. Pagador:</b> {$tel_pagador}\n";
        }
        $msg .= "🪪 <b>C.I. Pago:</b> {$ced_bdv}\n";
        $msg .= "💰 <b>Total USD:</b> $ " . number_format((float)$total_usd, 2) . "\n";
        $msg .= "🇻🇪 <b>Total BS:</b> " . number_format((float)$monto_ves, 2) . " Bs.\n"; 
        $msg .= "💳 <b>Ref BDV:</b> <code>" . ($ref_bdv ?: 'N/D') . "</code>\n"; 
        $msg .= "⏱️ <b>Timestamp:</b> " . ($timestamp ?: $fecha_pag ?: 'N/D') . "\n\n";
        $msg .= $alerta_logistica . "\n";
        $msg .= "<i>Use los botones inferiores para Aprobar o Rechazar.</i>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Aprobar', 'callback_data' => 'approve_' . $cotizacion_id],
                    ['text' => '❌ Rechazar', 'callback_data' => 'reject_' . $cotizacion_id]
                ]
            ]
        ];

        if ( class_exists('Suite_Telegram_Bot') && !$debug_mode ) {
            $telegram = new Suite_Telegram_Bot();
            $telegram_url = "https://api.telegram.org/bot" . $this->get_bot_token($telegram) . "/sendMessage";
            wp_remote_post( $telegram_url, [
                'body' => [ 
                    'chat_id'      => '-5199565623', 
                    'text'         => $msg, 
                    'parse_mode'   => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ],
                'timeout' => 5, 'blocking' => false
            ]);
        } elseif ($debug_mode) {
            echo "<b>📱 Mensaje a Telegram:</b><br><pre>" . htmlspecialchars($msg) . "</pre>";
        }
    }

    private function get_bot_token($bot_instance) {
        $reflection = new ReflectionClass($bot_instance);
        $property = $reflection->getProperty('bot_token');
        $property->setAccessible(true);
        return $property->getValue($bot_instance);
    }
}