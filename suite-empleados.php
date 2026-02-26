<?php
/**
 * Plugin Name: Suite de Empleados (ERP Intranet)
 * Plugin URI: https://mitiendaunit.com/intranet_1
 * Description: Sistema modular V8.0 para gestión de Inventario, CRM, Logística, Gamificación y Cerebro de Demanda (IA). Arquitectura MVC.
 * Version: 8.0.0
 * Author: DevOps Team & RV Automation Technology
 * Text Domain: suite-empleados
 * License: GPLv2 or later
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
 * 3. INICIALIZADOR DEL SISTEMA (Orquestador MVC)
 * Se engancha a 'plugins_loaded' para asegurar que el core de WP esté listo.
 */
function suite_empleados_init() {
    
    // --- A. CARGA DE DEPENDENCIAS (REQUIRE_ONCE) ---
    // [Error 1.1 Corregido]: Rutas apuntando a los nombres reales de los archivos.

    // Core
    require_once SUITE_PATH . 'includes/Core/class-activator.php';
    require_once SUITE_PATH . 'includes/Core/class-suite-cron-jobs.php';

    // Modelos (Capa de Base de Datos y Lógica de Negocio)
    require_once SUITE_PATH . 'includes/Models/class-suite-model-base.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-client.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-quote.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-inventory.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-commission.php';
	require_once SUITE_PATH . 'includes/Models/class-suite-model-roles.php';    
    require_once SUITE_PATH . 'includes/Models/class-suite-model-employee.php'; 

    // Controladores Base
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-controller.php';

    // Controladores AJAX (Módulos de la UI)
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-client.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-quotes.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-kanban.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-commissions.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-logistics.php';
	require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-employees.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-roles.php';

    // Controladores API REST (Data Lake y Machine Learning)
    require_once SUITE_PATH . 'includes/Controllers/Api/class-suite-api-stats.php';

    // Controlador del Administrador / Frontend (Shortcodes y Vistas)
    require_once SUITE_PATH . 'includes/Controllers/Admin/class-suite-shortcode-controller.php';


    // --- B. INSTANCIACIÓN DE CONTROLADORES (Encendiendo los motores) ---

    // Módulo de Clientes (CRM) - [Las 5 nuevas clases divididas]
    new Suite_Ajax_Client_Search();
    new Suite_Ajax_Client_Add();
    new Suite_Ajax_Client_Import();
    new Suite_Ajax_Client_Delete();
    new Suite_Ajax_Client_Profile();
	new Suite_Ajax_Log_Export();

    // Módulo de Cotizaciones
    new Suite_Ajax_Quote_Save();
    new Suite_Ajax_Quote_History();
    new Suite_Ajax_Quote_Status();
	new Suite_Ajax_Print_Quote();

    // Módulo 1: Tablero Kanban (Pedidos)
    new Suite_Ajax_Kanban_Data();
    new Suite_Ajax_Kanban_Status();

    // Módulo 3: Logística y Despacho
    new Suite_Ajax_Upload_POD();
    new Suite_Ajax_Print_Picking();

    // Módulo 4: Dashboard de Comisiones y Gamificación
    new Suite_Ajax_Dashboard_Stats();

    // Módulo 5: Cerebro de Demanda (REST API)
    new Suite_API_Stats();
	
	// Módulo 6:--- NUEVO MÓDULO: GESTIÓN DE EQUIPO Y ROLES (RBAC) ---
    new Suite_Ajax_Employee_List();
    new Suite_Ajax_Employee_Save();
    new Suite_Ajax_Employee_Delete();
    new Suite_Ajax_Role_List();
    new Suite_Ajax_Role_Save();
    new Suite_Ajax_Role_Delete();

    // Gestor de la Vista Principal (Shortcode y encolado de assets)
    new Suite_Shortcode_Controller();
}
add_action( 'plugins_loaded', 'suite_empleados_init' );


/**
 * 4. ACTIVACIÓN DEL PLUGIN
 * Crea las tablas, define roles usando dbDelta y programa Cron Jobs.
 */
function suite_plugin_activate() {
    // 1. Instalación de BD y Roles
    require_once SUITE_PATH . 'includes/Core/class-activator.php';
    if ( class_exists( 'Suite_Activator' ) && method_exists( 'Suite_Activator', 'activate' ) ) {
        Suite_Activator::activate();
    } elseif ( function_exists( 'suite_install_db' ) ) {
        suite_install_db(); 
    }

    // 2. Programación de Tareas Automáticas (Data Lake)
    // [Error 3.1 Corregido]: El Cron ahora se registra ESTRICTAMENTE UNA VEZ en la activación.
    require_once SUITE_PATH . 'includes/Core/class-suite-cron-jobs.php';
    if ( class_exists( 'Suite_Cron_Jobs' ) ) {
        $cron_jobs = new Suite_Cron_Jobs();
        $cron_jobs->schedule_events();
    }
}
register_activation_hook( __FILE__, 'suite_plugin_activate' );


/**
 * 5. DESACTIVACIÓN DEL PLUGIN
 * Limpia los Cron Jobs activos para no dejar basura en la memoria de WordPress.
 */
function suite_plugin_deactivate() {
    require_once SUITE_PATH . 'includes/Core/class-suite-cron-jobs.php';
    if ( class_exists( 'Suite_Cron_Jobs' ) ) {
        $cron_jobs = new Suite_Cron_Jobs();
        $cron_jobs->clear_events();
    }
}
register_deactivation_hook( __FILE__, 'suite_plugin_deactivate' );