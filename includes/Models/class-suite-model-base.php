<?php
/**
 * Clase Abstracta: Modelo Base de Base de Datos
 *
 * Centraliza la interacción con $wpdb, proporcionando métodos CRUD
 * blindados contra inyecciones SQL y estandarizados para todos los módulos.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Suite_Model_Base {

    /**
     * Instancia global de WordPress DB
     * @var wpdb
     */
    protected $wpdb;

    /**
     * Nombre completo de la tabla (incluyendo el prefijo wp_)
     * @var string
     */
    protected $table_name;

    /**
     * Llave primaria de la tabla (por defecto 'id')
     * @var string
     */
    protected $primary_key = 'id';

    /**
     * Constructor. Inicializa la conexión a DB y define el nombre de la tabla.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // $this->set_table_name() debe ser definido obligatoriamente por la clase hija
        $this->table_name = $this->wpdb->prefix . $this->set_table_name();
    }

    /**
     * Define el nombre de la tabla sin el prefijo de WordPress.
     * Ej: return 'suite_clientes';
     *
     * @return string
     */
    abstract protected function set_table_name();

    /**
     * Obtiene un registro específico por su ID.
     *
     * @param int $id El ID del registro.
     * @return object|null Objeto con los datos o null si no existe.
     */
    public function get( $id ) {
        $sql = $this->wpdb->prepare( 
            "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %d", 
            intval( $id ) 
        );
        return $this->wpdb->get_row( $sql );
    }

    /**
     * Obtiene una lista de registros con límite y offset (paginación básica).
     *
     * @param int $limit Límite de resultados.
     * @param int $offset Punto de inicio.
     * @return array Array de objetos.
     */
    public function get_all( $limit = 100, $offset = 0 ) {
        $sql = $this->wpdb->prepare( 
            "SELECT * FROM {$this->table_name} ORDER BY {$this->primary_key} DESC LIMIT %d OFFSET %d", 
            intval( $limit ), 
            intval( $offset ) 
        );
        return $this->wpdb->get_results( $sql );
    }

    /**
     * Inserta un nuevo registro de forma segura.
     *
     * @param array $data Array asociativo [ 'columna' => 'valor' ].
     * @return int|false El ID insertado o false en caso de error.
     */
    public function insert( $data ) {
        $inserted = $this->wpdb->insert( $this->table_name, $data );
        if ( $inserted ) {
            return $this->wpdb->insert_id;
        }
        return false;
    }

    /**
     * Actualiza un registro existente de forma segura.
     *
     * @param int   $id   El ID del registro a actualizar.
     * @param array $data Array asociativo [ 'columna' => 'valor' ].
     * @return bool True si se actualizó, false en caso de error.
     */
    public function update( $id, $data ) {
        $updated = $this->wpdb->update( 
            $this->table_name, 
            $data, 
            [ $this->primary_key => intval( $id ) ] 
        );
        
        // $updated devuelve false en error, o el número de filas afectadas (puede ser 0 si los datos eran idénticos)
        return $updated !== false;
    }

    /**
     * Elimina un registro por su ID de forma segura.
     *
     * @param int $id El ID del registro a eliminar.
     * @return bool True en éxito, false en error.
     */
    public function delete( $id ) {
        $deleted = $this->wpdb->delete( 
            $this->table_name, 
            [ $this->primary_key => intval( $id ) ] 
        );
        return $deleted !== false;
    }

}