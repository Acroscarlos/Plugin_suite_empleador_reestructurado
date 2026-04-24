<?php
/**
 * Controlador API REST: Estadísticas, Marketing e Inteligencia Artificial
 *
 * Expone endpoints estandarizados para integraciones con PowerBI, Python (Pandas/Prophet)
 * y herramientas de análisis de Marketing.
 *
 * @package SuiteEmpleados\Controllers\Api
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_API_Stats {

    /**
     * Registra las rutas REST al inicializar la API
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Define el namespace y los endpoints
     */
    public function register_routes() {
        $namespace = 'suite/v1';

        // Endpoint 1: Rendimiento de Canales de Venta (Marketing)
        register_rest_route( $namespace, '/ventas-vs-alcance', [
            'methods'             => WP_REST_Server::READABLE, // GET
            'callback'            => [ $this, 'get_ventas_vs_alcance' ],
            'permission_callback' => [ $this, 'check_permissions' ]
        ] );

        // Endpoint 2: Exportación de Series de Tiempo para Machine Learning (Prophet)
        register_rest_route( $namespace, '/export-prophet', [
            'methods'             => WP_REST_Server::READABLE, // GET
            'callback'            => [ $this, 'export_prophet_data' ],
            'permission_callback' => [ $this, 'check_permissions' ]
        ] );
    }

    /**
     * Middleware de Seguridad: Control de Accesos
     * Solo permite a Administradores o al nuevo rol 'suite_marketing'
     *
     * @return bool|WP_Error
     */
	public function check_permissions() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_unauthorized', 'Debe iniciar sesión para acceder a la API.', [ 'status' => 401 ] );
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;

		
		
		
        // VERIFICACIÓN CORREGIDA: Usa current_user_can() para validar la bandera universal
        if ( current_user_can( 'manage_options' ) || current_user_can( 'suite_view_marketing' ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', 'Acceso denegado. Se requiere nivel de Marketing o Administrador.', [ 'status' => 403 ] );
    }
	
	
	

    /**
     * Endpoint: Agrupa las ventas cerradas por Fecha y Canal de Venta (Últimos 30 días)
     * Ideal para graficar en Chart.js o cruzar con inversión publicitaria (CPA/ROAS).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_ventas_vs_alcance( $request ) {
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';

        // Consulta SQL para agrupar ventas efectivas
        $sql = "
            SELECT 
                DATE(fecha_emision) as fecha, 
                canal_venta, 
                COUNT(id) as cantidad_operaciones, 
                SUM(total_usd) as volumen_usd
            FROM {$tabla_cot}
            WHERE estado IN ('pagado', 'despachado') 
            AND fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(fecha_emision), canal_venta
            ORDER BY fecha ASC
        ";

        $resultados = $wpdb->get_results( $sql );

		
		
        // Formatear nulos a canales "No Definidos" y castear tipos para JSON
        foreach ( $resultados as $r ) {
            $r->canal_venta = empty( $r->canal_venta ) ? 'Orgánico / No definido' : $r->canal_venta;
            $r->cantidad_operaciones = intval( $r->cantidad_operaciones );
            $r->volumen_usd = floatval( $r->volumen_usd );
        }
		
		
		
		
		// --- INYECCIÓN DE INTELIGENCIA TÁCTICA (V8.6) ---
        $tabla_items = $wpdb->prefix . 'suite_cotizaciones_items';
        $tabla_inv = $wpdb->prefix . 'suite_inventario_cache';

        // 1. Producto Estrella (Top Ventas por VOLUMEN, no por dinero) para combos lógicos
        $estrella = $wpdb->get_row("
            SELECT i.sku, i.producto_nombre, SUM(i.cantidad) as qty
            FROM {$tabla_items} i
            JOIN {$tabla_cot} c ON i.cotizacion_id = c.id
            WHERE c.estado IN ('pagado', 'despachado', 'completado') 
            AND c.fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY i.sku
            ORDER BY qty DESC LIMIT 1
        ");

        // 2. Top 5 de Enfoque para Pauta Publicitaria
        // [AGREGADO: i.sku para poder cruzar los datos]
        $top5 = $wpdb->get_results("
            SELECT i.sku, i.producto_nombre, SUM(i.cantidad) as qty, SUM(i.subtotal_usd) as ingresos
            FROM {$tabla_items} i
            JOIN {$tabla_cot} c ON i.cotizacion_id = c.id
            WHERE c.estado IN ('pagado', 'despachado', 'completado') 
            AND c.fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY i.sku
            ORDER BY ingresos DESC, qty DESC LIMIT 5
        ");

        // 3. LECTURA DEL CSV (La Fuente de la Verdad Absoluta para Velocidad)
        $csv_path = SUITE_PATH . 'output/Matriz_unificada_Woocommerce.csv';
        $sobrestock = null;
        $diccionario_csv = [];

        if ( file_exists( $csv_path ) && ( $handle = fopen( $csv_path, 'r' ) ) !== false ) {
            $headers = fgetcsv( $handle, 2000, ',' );
            $map = [];
            
            // Mapeo inteligente de las columnas del CSV
            foreach ( $headers as $index => $header ) {
                $h_clean = strtolower( trim( $header ) );
                if ( strpos( $h_clean, 'sku' ) !== false ) $map['sku'] = $index;
                elseif ( strpos( $h_clean, 'nombre' ) !== false ) $map['nombre'] = $index;
                elseif ( strpos( $h_clean, 'velocidad' ) !== false ) $map['velocidad'] = $index;
                elseif ( strpos( $h_clean, 'status' ) !== false ) $map['status'] = $index;
                elseif ( strpos( $h_clean, 'gale' ) !== false ) $map['gale'] = $index;
                elseif ( strpos( $h_clean, 'mille' ) !== false ) $map['mille'] = $index;
            }

            // Escanear el CSV fila por fila
            while ( ( $row = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
                $sku = isset($map['sku'], $row[$map['sku']]) ? trim($row[$map['sku']]) : '';
                if ( $sku ) {
                    // Extraer métricas clave
                    $vel = isset($map['velocidad'], $row[$map['velocidad']]) ? floatval($row[$map['velocidad']]) : 0;
                    $gale = isset($map['gale'], $row[$map['gale']]) ? floatval($row[$map['gale']]) : 0;
                    $mille = isset($map['mille'], $row[$map['mille']]) ? floatval($row[$map['mille']]) : 0;
                    $stock_total = $gale + $mille;
                    $status = isset($map['status'], $row[$map['status']]) ? trim($row[$map['status']]) : '';
                    
                    // A) Guardar en memoria para dárselo luego al Top 5
                    $diccionario_csv[$sku] = [
                        'velocidad' => $vel,
                        'stock' => $stock_total
                    ];

                    // B) Capturar el producto con MAYOR SOBRESTOCK real para el Combo
                    if ( $status === 'SOBRESTOCK' && $stock_total > 0 ) {
                        if ( is_null($sobrestock) || $stock_total > $sobrestock->stock_total ) {
                            $sobrestock = (object) [
                                'nombre_producto' => isset($map['nombre'], $row[$map['nombre']]) ? trim($row[$map['nombre']]) : 'N/D',
                                'stock_total' => $stock_total
                            ];
                        }
                    }
                }
            }
            fclose($handle);
        }

        // 4. Cruzar la base de datos de Ventas con el Diccionario extraído del CSV
        foreach ( $top5 as $item ) {
            $sku = $item->sku;
            if ( isset($diccionario_csv[$sku]) ) {
                // Si el producto está en el Excel, inyectar velocidad y calcular la Autonomía (Runway)
                $item->velocidad_venta = $diccionario_csv[$sku]['velocidad'];
                $stock_actual = $diccionario_csv[$sku]['stock'];
                
                // Matemática Predictiva de Supervivencia
                $item->runway_dias = ($item->velocidad_venta > 0) ? round($stock_actual / $item->velocidad_venta) : 999;
            } else {
                // Failsafe: Si no está en el Excel por alguna razón
                $item->velocidad_venta = 0;
                $item->runway_dias = 999; 
            }
        }

        // 5. Algoritmo Sugeridor de Combos (Limpio)
        $combo = null;
        if ( $estrella && $sobrestock ) {
            $combo = [
                'gancho'  => $estrella->producto_nombre,
                'impulso' => $sobrestock->nombre_producto
            ];
        }

        // 6. ¡Despegue! Enviar datos combinados a JavaScript
        return rest_ensure_response( [
            'success' => true,
            'periodo' => 'Últimos 30 días',
            'data'    => $resultados,
            'tactics' => [
                'estrella'   => $estrella,
                'sobrestock' => $sobrestock,
                'combo'      => $combo,
                'top5'       => $top5
            ]
        ] );
    }

    /**
     * Endpoint: Extrae el Data Lake para Pandas / Facebook Prophet
     * Usa la nomenclatura estricta 'ds' (Datestamp) e 'y' (Variable objetivo)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function export_prophet_data( $request ) {
        global $wpdb;
        $tabla_historico = $wpdb->prefix . 'suite_inventario_historico';

        // Facebook Prophet requiere obligatoriamente que la fecha se llame 'ds'
        // y la métrica a predecir se llame 'y'. En este caso, predeciremos la caída de stock (demanda).
        $sql = "
            SELECT 
                fecha_snapshot as ds, 
                stock_disponible as y, 
                sku, 
                precio, 
                categoria
            FROM {$tabla_historico}
            ORDER BY ds ASC, sku ASC
        ";

        $resultados = $wpdb->get_results( $sql );

        // Casteo estricto para que Pandas no falle al procesar JSON
        foreach ( $resultados as $r ) {
            $r->y = intval( $r->y );
            $r->precio = floatval( $r->precio );
        }

        return rest_ensure_response( [
            'success' => true,
            'model'   => 'Facebook Prophet Compatible',
            'data'    => $resultados
        ] );
    }
}