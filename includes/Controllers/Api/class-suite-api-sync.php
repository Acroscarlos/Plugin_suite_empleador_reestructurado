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

        // 1. Validar presencia de archivos
        if ( empty( $files['reporte_final'] ) || empty( $files['precios'] ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Faltan archivos requeridos. Se esperan los campos "reporte_final" y "precios".'
            ], 400 );
        }

        $file_reporte = $files['reporte_final'];
        $file_precios = $files['precios'];

        // 2. Validar estructura, mime type y extensiones
        $val_reporte = $this->validate_csv( $file_reporte );
        if ( is_wp_error( $val_reporte ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Error en reporte_final: ' . $val_reporte->get_error_message() ], 400 );
        }

        $val_precios = $this->validate_csv( $file_precios );
        if ( is_wp_error( $val_precios ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Error en precios: ' . $val_precios->get_error_message() ], 400 );
        }

        // 3. Preparar el directorio de destino
        $output_dir = SUITE_PATH . 'output/';
        if ( ! file_exists( $output_dir ) ) {
            wp_mkdir_p( $output_dir );
            
            // Prevención de ejecución (Defense in Depth)
            file_put_contents( $output_dir . 'index.php', '<?php // Silence is golden.' );
            file_put_contents( $output_dir . '.htaccess', 'Deny from all' );
        }

        // 4. Guardar archivos (Sobrescribiendo cualquier nombre original para evitar Directory Traversal)
        // Al forzar el nombre de destino ("reporte_final.csv"), anulamos inyecciones como "../../../script.php"
        $path_reporte = $output_dir . 'reporte_final.csv';
        $path_precios = $output_dir . 'precios.csv';

        $move_reporte = move_uploaded_file( $file_reporte['tmp_name'], $path_reporte );
        $move_precios = move_uploaded_file( $file_precios['tmp_name'], $path_precios );

        if ( $move_reporte && $move_precios ) {
            return new WP_REST_Response( [
                'success' => true,
                'message' => 'Sincronización de CSVs ejecutada correctamente.'
            ], 200 );
        } else {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error de sistema de archivos al intentar guardar los CSVs.'
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