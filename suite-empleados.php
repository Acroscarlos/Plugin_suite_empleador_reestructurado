<?php
/**
 * Plugin Name: Suite de Empleados (ERP Intranet)
 * Plugin URI: https://mitiendaunit.com/intranet_1
 * Description: Sistema modular V8 (Arquitectura OOP/MVC) para gestión de Inventario, CRM, Cotizaciones y Logística.
 * Version: 8.0.0
 * Author: RV Automation Technology (DevOps Team)
 * Text Domain: suite-empleados
 *
 * @package SuiteEmpleados
 */

// 1. SEGURIDAD: Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. CONSTANTES DEL SISTEMA
define( 'SUITE_VERSION', '8.0.0' );
define( 'SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SUITE_URL', plugin_dir_url( __FILE__ ) );

/**
 * 3. AUTOLOADER BASADO EN ESTÁNDARES DE WORDPRESS
 * 
 * Carga automáticamente las clases bajo demanda. 
 * Si instanciamos "new Suite_Model_Client()", buscará el archivo "class-suite-model-client.php".
 */
spl_autoload_register( function ( $class_name ) {
    // Solo procesar clases que pertenezcan a nuestro plugin (Prefijo 'Suite_')
    if ( strpos( $class_name, 'Suite_' ) !== 0 ) {
        return;
    }

    // Convertir nombre de clase a formato de archivo WP (Ej: Suite_Model_Base -> class-suite-model-base.php)
    $file_name = strtolower( str_replace( '_', '-', $class_name ) );
    $file_name = 'class-' . $file_name . '.php';

    // Rutas donde el sistema buscará las clases
    $directories = [
        'includes/Core/',
        'includes/Models/',
        'includes/Controllers/',
        'includes/Controllers/Ajax/',
        'includes/Controllers/Api/',
        'includes/Controllers/Admin/',
        'includes/Helpers/'
    ];

    // Buscar y requerir el archivo
    foreach ( $directories as $directory ) {
        $full_path = SUITE_PATH . $directory . $file_name;
        if ( file_exists( $full_path ) ) {
            require_once $full_path;
            return;
        }
    }
});

/**
 * 4. INICIALIZADOR (BOOTSTRAP)
 * 
 * En el futuro, aquí instanciamos una clase "Suite_Core" u "Orquestador"
 * que dispare los hooks principales.
 */
function suite_empleados_init() {
    // Ejemplo futuro: 
    // $app = new Suite_Core();
    // $app->run();
}
add_action( 'plugins_loaded', 'suite_empleados_init' );

/**
 * 5. ACTIVACIÓN DE BASE DE DATOS
 */
register_activation_hook( __FILE__, 'suite_plugin_activate' );
function suite_plugin_activate() {
    // Requerimos el instalador manualmente solo al activar para no sobrecargar el autoloader diario
    require_once SUITE_PATH . 'includes/Core/class-suite-activator.php';
    Suite_Activator::activate();
}