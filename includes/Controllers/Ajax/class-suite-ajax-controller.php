<?php
/**
 * Clase Abstracta: Controlador AJAX Base
 *
 * Centraliza la seguridad, el registro de hooks y las respuestas estandarizadas
 * para todas las peticiones AJAX del sistema.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Suite_AJAX_Controller {

    /**
     * El nombre de la acción AJAX (Ej. 'suite_search_client_ajax')
     * @var string
     */
    protected $action_name;

    /**
     * El permiso mínimo requerido para ejecutar esta acción.
     * @var string
     */
    protected $required_capability = 'read'; // Por defecto, cualquier usuario logueado en la intranet

    /**
     * Constructor.
     * Registra dinámicamente el hook wp_ajax basándose en $action_name.
     */
    public function __construct() {
        if ( empty( $this->action_name ) ) {
            // Evitar registro si la clase hija no definió el nombre de la acción
            return;
        }

        // Registrar el endpoint (Solo para usuarios logueados)
        add_action( 'wp_ajax_' . $this->action_name, [ $this, 'handle_request' ] );
    }

    /**
     * Manejador principal de la petición.
     * Ejecuta las barreras de seguridad antes de procesar la lógica.
     */
    public function handle_request() {
        // 1. Barrera CSRF: Validación de Nonce Estricto (Retrocompatibilidad)
        if ( ! check_ajax_referer( 'suite_quote_nonce', 'nonce', false ) ) {
            $this->send_error( 'Fallo de seguridad CSRF o sesión caducada.', 403 );
        }

        // 2. Barrera de Permisos: Verificación del Rol del Usuario
        if ( ! current_user_can( $this->required_capability ) ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.', 401 );
        }

        // 3. Ejecutar la lógica de negocio específica de la clase hija
        $this->process();
    }

    /**
     * Método abstracto que las clases hijas DEBEN implementar.
     * Aquí es donde irá la lógica real (ej. buscar cliente, guardar cotización).
     */
    abstract protected function process();

    /**
     * Helper para enviar una respuesta exitosa estandarizada.
     *
     * @param mixed $data Los datos a devolver (Array, Objeto, String).
     */
    protected function send_success( $data = [] ) {
        wp_send_json_success( $data );
    }

    /**
     * Helper para enviar una respuesta de error estandarizada.
     *
     * @param string $message Mensaje de error para el frontend.
     * @param int    $code    Código HTTP simulado.
     */
    protected function send_error( $message, $code = 400 ) {
        wp_send_json_error( [
            'message' => $message,
            'code'    => $code
        ] );
    }
}