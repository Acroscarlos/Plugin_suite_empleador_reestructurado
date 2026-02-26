<?php
/**
 * Controlador AJAX: Clientes (CRM)
 *
 * Maneja las peticiones del frontend relacionadas con los clientes (Búsqueda, 
 * Creación, Importación, Perfiles y Eliminación).
 * Hereda las validaciones de seguridad de Suite_AJAX_Controller.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Búsqueda predictiva de clientes
 */
class Suite_Ajax_Client_Search extends Suite_AJAX_Controller {
    protected $action_name = 'suite_search_client_ajax';
    protected $required_capability = 'read';

    protected function process() {
        $term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';
        if ( empty( $term ) ) {
            $this->send_success( [] );
        }

        $clientModel = new Suite_Model_Client();
        $resultados = $clientModel->search_clients( $term );
        $this->send_success( $resultados );
    }
}

/**
 * 2. Creación manual de un cliente nuevo
 */
class Suite_Ajax_Client_Add extends Suite_AJAX_Controller {
    protected $action_name = 'suite_add_client_manual';
    protected $required_capability = 'read';

    protected function process() {
        $rif    = isset( $_POST['rif'] ) ? sanitize_text_field( $_POST['rif'] ) : '';
        $nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( $_POST['nombre'] ) : '';

        if ( empty( $rif ) || empty( $nombre ) ) {
            $this->send_error( 'El RIF y el Nombre son obligatorios.' );
        }

        $clientModel = new Suite_Model_Client();
        
        $data = [
            'rif_ci'       => strtoupper( preg_replace( '/[^A-Z0-9]/', '', $rif ) ),
            'nombre_razon' => $nombre,
            'direccion'    => isset( $_POST['direccion'] ) ? sanitize_textarea_field( $_POST['direccion'] ) : '',
            'telefono'     => isset( $_POST['telefono'] ) ? sanitize_text_field( $_POST['telefono'] ) : '',
            'email'        => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
            'ciudad'       => isset( $_POST['ciudad'] ) ? sanitize_text_field( $_POST['ciudad'] ) : '',
            'estado'       => isset( $_POST['estado'] ) ? sanitize_text_field( $_POST['estado'] ) : '',
        ];

        $inserted = $clientModel->insert( $data );

        if ( $inserted ) {
            $this->send_success( [ 'message' => 'Cliente creado exitosamente.', 'id' => $inserted ] );
        } else {
            $this->send_error( 'Fallo al crear el cliente. Es posible que el RIF ya exista.' );
        }
    }
}

/**
 * 3. Importación masiva de clientes vía CSV
 */
class Suite_Ajax_Client_Import extends Suite_AJAX_Controller {
    protected $action_name = 'suite_import_clients_csv';
    protected $required_capability = 'read';

    protected function process() {
        if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            $this->send_error( 'No se ha subido ningún archivo o el archivo es inválido.' );
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $clientModel = new Suite_Model_Client();
        $inserted_count = 0;
        $row = 0;

        if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
            while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
                $row++;
                // Ignorar la cabecera
                if ( $row === 1 ) continue;

                // Acceso seguro a los índices del array fgetcsv
                $nombre = sanitize_text_field( isset($data[0]) ? $data[0] : '' );
                $rif    = sanitize_text_field( isset($data[1]) ? $data[1] : '' );

                if ( empty( $nombre ) || empty( $rif ) ) continue;

                $insert_data = [
                    'nombre_razon' => $nombre,
                    'rif_ci'       => strtoupper( preg_replace( '/[^A-Z0-9]/', '', $rif ) ),
                    'telefono'     => sanitize_text_field( isset($data[2]) ? $data[2] : '' ),
                    'email'        => sanitize_email( isset($data[3]) ? $data[3] : '' ),
                    'direccion'    => sanitize_textarea_field( isset($data[4]) ? $data[4] : '' ),
                ];

                if ( $clientModel->insert( $insert_data ) ) {
                    $inserted_count++;
                }
            }
            fclose( $handle );
            $this->send_success( "Importación completada. Se añadieron {$inserted_count} clientes nuevos." );
        } else {
            $this->send_error( 'No se pudo leer el archivo CSV.' );
        }
    }
}

/**
 * 4. Eliminación de un cliente
 */
class Suite_Ajax_Client_Delete extends Suite_AJAX_Controller {
    protected $action_name = 'suite_delete_client';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        
        if ( ! $id ) {
            $this->send_error( 'ID de cliente inválido.' );
        }

        // BARRERA DE INTEGRIDAD REFERENCIAL
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';
        $tiene_compras = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$tabla_cot} WHERE cliente_id = %d", $id ) );

        if ( $tiene_compras > 0 ) {
            $this->send_error( 'Protección Contable: No puede eliminar un cliente con historial de compras. Edite sus datos si es necesario.', 403 );
        }

        $clientModel = new Suite_Model_Client();
        $deleted = $clientModel->delete( $id );

        if ( $deleted ) {
            $this->send_success( 'Cliente eliminado correctamente.' );
        } else {
            $this->send_error( 'No se pudo eliminar el cliente.' );
        }
    }
}

/**
 * 5. Obtener el Perfil y KPIs de un cliente
 */
class Suite_Ajax_Client_Profile extends Suite_AJAX_Controller {
    protected $action_name = 'suite_get_client_profile';
    protected $required_capability = 'read';

    protected function process() {
        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        
        if ( ! $id ) {
            $this->send_error( 'ID de cliente inválido.' );
        }

        $clientModel = new Suite_Model_Client();
        
        $cliente = $clientModel->get( $id );
        if ( ! $cliente ) {
            $this->send_error( 'El cliente no existe.', 404 );
        }

        $stats = $clientModel->get_client_stats( $id );
        $history = $clientModel->get_client_history( $id );

        // Formateo de las fechas de KPIs (Blindado para retrocompatibilidad y objetos nulos)
        $stats_fmt = [
            'total' => isset( $stats->total ) ? number_format( floatval( $stats->total ), 2 ) : '0.00',
            'count' => isset( $stats->count ) ? intval( $stats->count ) : 0,
            'first' => !empty( $stats->first ) ? date( 'd/m/Y', strtotime( $stats->first ) ) : '-',
            'last'  => !empty( $stats->last ) ? date( 'd/m/Y', strtotime( $stats->last ) ) : '-',
        ];

        // Formateo visual del historial
        $history_fmt = [];
        if ( ! empty( $history ) ) {
            foreach ( $history as $h ) {
                $history_fmt[] = [
                    'id'     => $h->id,
                    'fecha'  => date( 'd/m/Y', strtotime( $h->fecha ) ),
                    'codigo' => $h->codigo,
                    'total'  => number_format( floatval( $h->total ), 2 ),
                    'estado' => $h->estado,
                ];
            }
        }

        $this->send_success( [
            'cliente' => $cliente,
            'stats'   => $stats_fmt,
            'history' => $history_fmt
        ] );
    }
}

/**
 * Controlador AJAX: Registro de Auditoría de Exportaciones
 * 
 * Registra en el log de la base de datos cada vez que un Administrador o Gerente
 * exporta información sensible en formato CSV o Excel.
 */
class Suite_Ajax_Log_Export extends Suite_AJAX_Controller {

    protected $action_name = 'suite_log_export';
    // Se requiere un nivel de acceso base, pero validaremos el rol exacto adentro
    protected $required_capability = 'read'; 

    protected function process() {
        // 1. DOBLE BARRERA DE SEGURIDAD (Zero-Trust)
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        
        // Comprobar si es Administrador o tiene el rol personalizado de gerente
        $is_admin = current_user_can( 'manage_options' );
        $is_gerente = in_array( 'suite_gerente', $roles ) || in_array( 'gerente', $roles );

        if ( ! $is_admin && ! $is_gerente ) {
            $this->send_error( 'Acceso Denegado. Violación de seguridad registrada.', 403 );
        }

        // 2. RECIBIR DATOS
        $tabla = isset( $_POST['tabla'] ) ? sanitize_text_field( $_POST['tabla'] ) : 'Desconocida';
        
        // 3. REGISTRAR EN LA TABLA DE AUDITORÍA
        global $wpdb;
        $table_logs = $wpdb->prefix . 'suite_logs';
        
        // La fecha y hora ('created_at') se insertan automáticamente por MySQL (CURRENT_TIMESTAMP) [2]
        $wpdb->insert(
            $table_logs,
            [
                'usuario_id' => get_current_user_id(),
                'accion'     => 'exportacion_datos',
                'detalle'    => "Exportó la tabla {$tabla} en formato CSV/Excel",
                'ip'         => $_SERVER['REMOTE_ADDR']
            ]
        );

        $this->send_success( 'Auditoría registrada.' );
    }
}