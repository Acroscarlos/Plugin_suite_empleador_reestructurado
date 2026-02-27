<?php
/**
 * Controlador AJAX: Búsqueda de Productos (Cotizador)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Ajax_Get_Products extends Suite_AJAX_Controller {

    protected $action_name = 'suite_search_pos';
    protected $required_capability = 'read';

    protected function process() {
        $term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

        // Detenemos peticiones vacías por seguridad y rendimiento
        if ( empty( $term ) || strlen( $term ) < 3 ) {
            $this->send_success( [] );
        }

        // Importante: No es necesario requerir el archivo del modelo aquí si ya lo cargamos en suite-empleados.php
        $product_model = new Suite_Model_Product();
        $resultados = $product_model->get_products_with_csv_prices( $term );

        $this->send_success( $resultados );
    }
}