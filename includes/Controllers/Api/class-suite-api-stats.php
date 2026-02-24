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

        if ( in_array( 'administrator', $roles ) || in_array( 'suite_marketing', $roles ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', 'Acceso denegado. Se requiere rol de Marketing o Administrador.', [ 'status' => 403 ] );
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

        return rest_ensure_response( [
            'success' => true,
            'periodo' => 'Últimos 30 días',
            'data'    => $resultados
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