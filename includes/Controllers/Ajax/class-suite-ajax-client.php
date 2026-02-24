<?php
/**
 * Controlador AJAX: Clientes (CRM)
 *
 * Maneja las peticiones del frontend relacionadas con los clientes.
 * Hereda las validaciones de seguridad de Suite_AJAX_Controller.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Ajax_Client extends Suite_AJAX_Controller {

    /**
     * Definimos el nombre exacto de la acción para mantener
     * retrocompatibilidad con el JavaScript actual.
     * @var string
     */
    protected $action_name = 'suite_search_client_ajax';

    /**
     * Nivel de acceso requerido (read = cualquier empleado).
     * @var string
     */
    protected $required_capability = 'read';

    /**
     * Procesa la lógica de búsqueda de clientes.
     */
    protected function process() {
        // 1. Recibir y sanitizar la entrada
        $term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

        // Retornar vacío si no hay término, ahorrando consultas a la DB
        if ( empty( $term ) ) {
            $this->send_success( [] );
        }

        // 2. Instanciar la capa de datos (Modelo)
        $clientModel = new Suite_Model_Client();

        // 3. Ejecutar la búsqueda utilizando la lógica blindada del modelo
        $resultados = $clientModel->search_clients( $term );

        // 4. Devolver la respuesta al JavaScript
        $this->send_success( $resultados );
    }
}


