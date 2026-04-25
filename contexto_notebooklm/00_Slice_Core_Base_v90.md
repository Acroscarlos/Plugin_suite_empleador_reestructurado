# 🏛️ MÓDULO MAESTRO: Core Architecture

### ARCHIVO: `suite-empleados.php`
```php
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
	require_once SUITE_PATH . 'includes/Core/class-suite-email-engine.php';

    // Modelos (Capa de Base de Datos y Lógica de Negocio)
    require_once SUITE_PATH . 'includes/Models/class-suite-model-base.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-client.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-quote.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-inventory.php';
    require_once SUITE_PATH . 'includes/Models/class-suite-model-commission.php';
	require_once SUITE_PATH . 'includes/Models/class-suite-model-roles.php';    
    require_once SUITE_PATH . 'includes/Models/class-suite-model-employee.php'; 
	require_once SUITE_PATH . 'includes/Models/class-suite-model-product.php';


    // Controladores Base
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-controller.php';

    // Controladores AJAX (Módulos de la UI)
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-client.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-quotes.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-kanban.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-commissions.php';
	require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-financial-balance.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-logistics.php';
	require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-employees.php';
    require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-roles.php';
	require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-products.php';
	require_once SUITE_PATH . 'includes/Controllers/Ajax/class-suite-ajax-inventory.php';
    // Controladores API REST (Data Lake y Machine Learning)
    require_once SUITE_PATH . 'includes/Controllers/Api/class-suite-api-stats.php';
	// Controladores de la API REST
	require_once SUITE_PATH . 'includes/Controllers/Api/class-suite-api-sync.php';
	require_once SUITE_PATH . 'includes/Controllers/Api/class-suite-api-telegram-webhook.php'; // <--- FASE 4.1: WEBHOOK TELEGRAM
	

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
	new Suite_Ajax_Get_Products();
	new Suite_Ajax_Quote_Details();
	new Suite_Ajax_Get_Inventory();
	new Suite_Ajax_Process_Super_Pago();
	new Suite_Ajax_Upload_Retention();
	new Suite_Ajax_Upload_Manual_Document();
    // Módulo 1: Tablero Kanban (Pedidos)
    new Suite_Ajax_Kanban_Data();
    new Suite_Ajax_Kanban_Status();
	new Suite_Ajax_Reverse_Logistics();
	new Suite_Ajax_Reverse_To_Paid(); 
	
	
    // Módulo 3: Logística y Despacho
    new Suite_Ajax_Upload_POD();
    new Suite_Ajax_Print_Picking();

    // Módulo 4: Dashboard de Comisiones y Gamificación
    new Suite_Ajax_Dashboard_Stats();
	new Suite_Ajax_Freeze_Commissions();
	new Suite_Ajax_Commission_Audit(); 
	new Suite_Ajax_Process_Audit_Action();
    new Suite_Ajax_Hall_of_Fame();
	new Suite_Ajax_Pay_Selected();
	new Suite_Ajax_Register_Abono();
	new Suite_Ajax_Run_Manual_Audit();
	new Suite_Ajax_Financial_Balance();
    // Módulo 5: Cerebro de Demanda (REST API)
    new Suite_API_Stats();
	new Suite_API_Sync();
	
	// Módulo 6:--- NUEVO MÓDULO: GESTIÓN DE EQUIPO Y ROLES (RBAC) ---
    new Suite_Ajax_Employee_List();
    new Suite_Ajax_Employee_Save();
    new Suite_Ajax_Employee_Delete();
    new Suite_Ajax_Role_List();
    new Suite_Ajax_Role_Save();
    new Suite_Ajax_Role_Delete();
	new Suite_Ajax_Update_Role_Cap();

    // Gestor de la Vista Principal (Shortcode y encolado de assets)
    new Suite_WooCommerce_Integration();
    new Suite_Shortcode_Controller();
	
}
add_action( 'plugins_loaded', 'suite_empleados_init' );
add_action( 'rest_api_init', function () {
	$telegram_webhook = new Suite_Telegram_Webhook();
	$telegram_webhook->register_routes();
	});

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


```

### ARCHIVO: `assets/css/suite-styles.css`
```css
/* ==========================================
   MODALES, PESTAÑAS Y FORMULARIOS
   ========================================== */
.suite-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
}

.suite-modal-content {
    background-color: #ffffff;
    padding: 25px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-height: 90vh;
    overflow-y: auto;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #0f172a;
}

.suite-tabs-modern {
    display: flex;
	flex-wrap: nowrap !important;      /* Impide que salten de línea */
    overflow-x: auto;                  /* Activa el scroll horizontal en pantallas pequeñas */
    -webkit-overflow-scrolling: touch;
    gap: 10px;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 20px;
    background: #f8fafc;
    padding: 10px 10px 0 10px;
    border-radius: 8px 8px 0 0;
	scrollbar-width: none;             /* Firefox */
    -ms-overflow-style: none;
}
.suite-tabs-modern::-webkit-scrollbar {
    display: none;
}

.tab-btn {
	flex-shrink: 0;                    /* Evita que los botones se achiquen si no caben */
    white-space: nowrap;
    padding: 12px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 600;
    color: #64748b;
    border-radius: 8px 8px 0 0;
    transition: all 0.3s;
}

.tab-btn:hover {
    background: #f1f5f9;
    color: #334155;
}

.tab-btn.active {
    background: #ffffff;
    color: #0073aa;
    border: 1px solid #e2e8f0;
    border-bottom: 2px solid #ffffff;
    margin-bottom: -2px;
}

.form-group-row {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-bottom: 15px;
}
.form-group-row > div {
    flex: 1;
}
```

### ARCHIVO: `assets/js/core/api.js`
```js
/**
 * SuiteAPI - Orquestador de Peticiones AJAX
 * * Centraliza las llamadas al servidor, inyectando automáticamente 
 * credenciales de seguridad (Nonces) y enrutamiento (URL).
 */
const SuiteAPI = (function($) {
    'use strict';

    // Variables privadas extraídas de la localización de WP
    const apiUrl = suite_vars.ajax_url;
    const sysNonce = suite_vars.nonce;

    /**
     * Interceptor Global de Errores
     * Captura expiración de sesión (401) o fallos de permisos (403)
     */
    const handleAjaxError = function(error, reject) {
        if (error.status === 401 || error.status === 403) {
            alert('🔒 Su sesión ha expirado o fue cerrada por seguridad. Por favor, recargue la página e inicie sesión nuevamente.');
        }
        reject(error);
    };

    /**
     * Petición POST estándar para JSON
     * * @param {string} action - El nombre del hook de WP (ej. 'suite_search_client_ajax')
     * @param {object} data - Datos a enviar
     * @returns {Promise}
     */
    const post = function(action, data = {}) {
        return new Promise((resolve, reject) => {
            // Inyección automática de parámetros obligatorios
            const payload = {
                ...data,
                action: action,
                nonce: sysNonce
            };

            $.post(apiUrl, payload)
                .done(response => resolve(response))
                .fail(error => handleAjaxError(error, reject)); // <-- Interceptor inyectado
        });
    };

    /**
     * Petición POST para subir archivos (FormData)
     * Utilizado para la importación de CSV o futuras subidas de fotos (POD)
     * * @param {string} action - El nombre del hook de WP
     * @param {FormData} formData - Objeto FormData instanciado
     * @returns {Promise}
     */
    const postForm = function(action, formData) {
        return new Promise((resolve, reject) => {
            // Inyección en FormData
            formData.append('action', action);
            formData.append('nonce', sysNonce);

            $.ajax({
                url: apiUrl,
                type: 'POST',
                data: formData,
                processData: false, // Vital para que jQuery no procese el archivo
                contentType: false, // Vital para que el navegador asigne el boundary multipart
                success: response => resolve(response),
                error: error => handleAjaxError(error, reject) // <-- Interceptor inyectado
            });
        });
    };

    // API Pública Revelada
    return {
        post: post,
        postForm: postForm
    };

})(jQuery);
```

### ARCHIVO: `assets/js/core/state.js`
```js
/**
 * SuiteState - Manejador de Estado Inmutable (Store)
 * 
 * Centraliza los datos financieros y el carrito de compras.
 * Previene la manipulación directa desde el objeto global window.
 */
const SuiteState = (function() {
    'use strict';

    // ==========================================
    // VARIABLES PRIVADAS (El "Estado")
    // ==========================================
    let cart = [];
    let totalUSD = 0.00;
    let totalBS = 0.00;
    let tasaBCV = 1.00; // Se actualizará al inicializar la app

    // ==========================================
    // MÉTODOS PRIVADOS
    // ==========================================
    
    /**
     * Recalcula los totales matemáticos cada vez que el carrito cambia.
     * Mantiene la lógica financiera blindada.
     */
    const calculateTotals = function() {
        totalUSD = cart.reduce((sum, item) => {
            let qty = parseInt(item.qty) || 0;
            let price = parseFloat(item.price) || 0;
            return sum + (qty * price);
        }, 0);

        totalBS = totalUSD * tasaBCV;
    };

    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        /**
         * Retorna una COPIA INMUTABLE del carrito.
         * Si alguien muta este array externamente, no afectará al original.
         * @returns {Array}
         */
        getCart: function() {
            return [...cart]; 
        },

        /**
         * Añade un producto al carrito y recalcula.
         * Si el SKU ya existe, suma la cantidad en lugar de duplicar la fila.
         * @param {Object} item 
         */
        addItem: function(item) {
            // Normalizar datos: Prohibir cantidades negativas o cero
            item.qty = Math.max(1, parseInt(item.qty) || 1);
            item.price = Math.max(0, parseFloat(item.price) || 0.00);

            const existingIndex = cart.findIndex(i => i.sku === item.sku);
            if (existingIndex > -1) {
                cart[existingIndex].qty += item.qty;
            } else {
                cart.push(item);
            }
            calculateTotals();
        },

        /**
         * Actualiza un campo específico de una fila (ej. cantidad o precio editado).
         * @param {number} index - Índice en el array
         * @param {string} field - 'qty' o 'price'
         * @param {number|string} value - Nuevo valor
         */
        updateItem: function(index, field, value) {
            if (cart[index]) {
                if (field === 'qty') {
                    cart[index][field] = Math.max(1, parseInt(value) || 1);
                } else {
                    cart[index][field] = Math.max(0, parseFloat(value) || 0);
                }
                calculateTotals();
            }
        },

        /**
         * Elimina un producto del carrito.
         * @param {number} index 
         */
        removeItem: function(index) {
            if (cart[index]) {
                cart.splice(index, 1);
                calculateTotals();
            }
        },

        /**
         * Vacía el carrito por completo (útil al guardar con éxito).
         */
        clearCart: function() {
            cart = [];
            calculateTotals();
        },

        /**
         * Actualiza la tasa BCV del día.
         * @param {number} tasa 
         */
        setTasa: function(tasa) {
            tasaBCV = parseFloat(tasa) || 1;
            calculateTotals();
        },

        /**
         * Devuelve un snapshot de los totales financieros formateados.
         * @returns {Object}
         */
        getTotals: function() {
            return {
                usd: totalUSD.toFixed(2),
                bs: totalBS.toFixed(2),
                tasa: tasaBCV.toFixed(2)
            };
        }
    };
})();

```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-controller.php`
```php
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
```

### ARCHIVO: `includes/Core/class-activator.php`
```php
<?php
// SEGURIDAD: Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Función de Instalación de Base de Datos
 * Crea las tablas críticas: Inventario, Clientes, Cotizaciones, Items, etc.
 */
if ( ! function_exists( 'suite_install_db' ) ) {
    function suite_install_db() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Tabla Inventario (Cache)
        $sql_inventario = "CREATE TABLE {$wpdb->prefix}suite_inventario_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sku VARCHAR(100) NOT NULL,
            nombre_producto VARCHAR(255),
            precio_venta DECIMAL(10,2) DEFAULT 0,
            precio_divisas DECIMAL(10,2) DEFAULT 0,
            velocidad_venta DECIMAL(10,2) DEFAULT 0, /* <--- NUEVA COLUMNA (KPI) */
            runway_dias INT DEFAULT 999, /* <--- NUEVA COLUMNA (Autonomía) */
            status_prediccion VARCHAR(50),
            inventario_entrante VARCHAR(10),
            disponibilidad_millennium FLOAT DEFAULT 0,
            disponibilidad_galerias FLOAT DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_sku (sku)
        ) $charset_collate;";

        // 2. Tabla Clientes (CRM)
        $sql_clientes = "CREATE TABLE {$wpdb->prefix}suite_clientes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
			vendedor_id bigint(20) DEFAULT 0,
            nombre_razon VARCHAR(255) NOT NULL,
            rif_ci VARCHAR(50) NOT NULL,
            direccion TEXT,
            ciudad VARCHAR(100),
            estado VARCHAR(100),
            telefono VARCHAR(50),
            email VARCHAR(100),
            contacto_persona VARCHAR(150),
            notas TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_rif (rif_ci)
        ) $charset_collate;";

        // 3. Tabla Cotizaciones
        $sql_cotizaciones = "CREATE TABLE {$wpdb->prefix}suite_cotizaciones (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            codigo_cotizacion VARCHAR(20) NOT NULL,
            cliente_nombre VARCHAR(255),
            cliente_rif VARCHAR(50),
            cliente_id bigint(20) NOT NULL DEFAULT 0,
            direccion_entrega TEXT,
            fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
            validez_dias INT DEFAULT 10,
            moneda VARCHAR(5) DEFAULT 'USD',
            vendedor_id bigint(20),
            total_bs DECIMAL(15,2),
            total_usd DECIMAL(15,2),
            tasa_bcv DECIMAL(10,2),
            estado VARCHAR(20) DEFAULT 'emitida',
            canal_venta VARCHAR(100),
            metodo_pago VARCHAR(100),
            metodo_entrega VARCHAR(100),
            url_captura_pago TEXT,
            recibo_loyverse VARCHAR(100),
			factura_fiscal_url VARCHAR(255),
            pod_url TEXT,
            
            /* --- INICIO FASE 1: NUEVOS CAMPOS KANBAN V2 --- */
            forma_pago VARCHAR(50),
            fecha_pago DATETIME,
            requiere_factura TINYINT(1) DEFAULT 0,
            agente_retencion TINYINT(1) DEFAULT 0,
            comprobante_pago_url TEXT,
            tipo_envio VARCHAR(50),
            agencia_envio VARCHAR(100),
            direccion_envio TEXT,
            prioridad TINYINT(1) DEFAULT 0,
            alerta_loyverse TEXT,
            /* --- FIN FASE 1 --- */
            
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 4. Tabla Libro Mayor de Comisiones (Ledger)
        $sql_comisiones = "CREATE TABLE {$wpdb->prefix}suite_comisiones_ledger (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) NOT NULL,
            vendedor_id bigint(20) NOT NULL,
            monto_base_usd DECIMAL(15,2) DEFAULT 0,
            comision_ganada_usd DECIMAL(15,2) DEFAULT 0,
            estado_pago VARCHAR(20) DEFAULT 'pendiente',
            /* --- FASE 5: AUDITORÍA LOYVERSE --- */
            recibo_loyverse VARCHAR(100) DEFAULT NULL,
            estado_auditoria VARCHAR(20) DEFAULT 'pendiente',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_vendedor (vendedor_id)
        ) $charset_collate;";

        // 5. Tabla Gamificación y Premios
        $sql_premios = "CREATE TABLE {$wpdb->prefix}suite_premios_mensuales (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vendedor_id bigint(20) NOT NULL,
            mes INT NOT NULL,
            anio INT NOT NULL,
            premio_nombre VARCHAR(100) NOT NULL,
            monto_premio DECIMAL(10,2) DEFAULT 0,
            asignado_manualmente TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_mes_anio (mes, anio)
        ) $charset_collate;";

        // 6. Tabla Items (Detalle)
        $sql_items = "CREATE TABLE {$wpdb->prefix}suite_cotizaciones_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cotizacion_id bigint(20) NOT NULL,
            sku VARCHAR(100),
            producto_nombre VARCHAR(255),
            cantidad INT,
            precio_unitario_usd DECIMAL(15,2),
            tiempo_entrega VARCHAR(100),
            subtotal_usd DECIMAL(15,2),
            PRIMARY KEY  (id),
            KEY idx_cotizacion (cotizacion_id)
        ) $charset_collate;";

        // 7. Tabla de Auditoría (Logs)
        $sql_logs = "CREATE TABLE {$wpdb->prefix}suite_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            usuario_id bigint(20) NOT NULL,
            accion VARCHAR(50) NOT NULL,
            detalle TEXT,
            ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_usuario (usuario_id)
        ) $charset_collate;";

        // 8. Tabla Data Lake / Inventario Histórico (Módulo 5 - IA)
        $sql_historico = "CREATE TABLE {$wpdb->prefix}suite_inventario_historico (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fecha_snapshot DATE NOT NULL,
            sku VARCHAR(100) NOT NULL,
            stock_disponible INT DEFAULT 0,
            precio DECIMAL(10,2) DEFAULT 0,
            categoria VARCHAR(100) DEFAULT 'General',
            PRIMARY KEY  (id),
            KEY idx_fecha (fecha_snapshot),
            KEY idx_sku (sku)
        ) $charset_collate;";

        // --- EJECUCIÓN ÚNICA DE DB-DELTA ---
        dbDelta( $sql_inventario );
        dbDelta( $sql_clientes );
        dbDelta( $sql_cotizaciones );
        dbDelta( $sql_items );
        dbDelta( $sql_comisiones );
        dbDelta( $sql_premios );
        dbDelta( $sql_logs );
        dbDelta( $sql_historico ); // Data Lake agregado correctamente

        // Ajuste de Auto-Increment para Cotizaciones (Inicio en 15000)
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}suite_cotizaciones" );
        if ( $count == 0 ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}suite_cotizaciones AUTO_INCREMENT = 15000" );
        }
		
		// --- PARCHE EN CALIENTE: Añadir vendedor_id si el plugin ya estaba instalado ---
        $column_check = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}suite_clientes LIKE 'vendedor_id'" );
        if ( empty( $column_check ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}suite_clientes ADD vendedor_id bigint(20) DEFAULT 0 AFTER id" );
        }
		
		// --- PARCHE EN CALIENTE FASE 5: Añadir columnas al Ledger ---
        $tabla_ledger_check = $wpdb->prefix . 'suite_comisiones_ledger';
        
        $col_loyverse = $wpdb->get_results( "SHOW COLUMNS FROM {$tabla_ledger_check} LIKE 'recibo_loyverse'" );
        if ( empty( $col_loyverse ) ) {
            $wpdb->query( "ALTER TABLE {$tabla_ledger_check} ADD recibo_loyverse VARCHAR(100) DEFAULT NULL" );
        }

        $col_auditoria = $wpdb->get_results( "SHOW COLUMNS FROM {$tabla_ledger_check} LIKE 'estado_auditoria'" );
        if ( empty( $col_auditoria ) ) {
            $wpdb->query( "ALTER TABLE {$tabla_ledger_check} ADD estado_auditoria VARCHAR(20) DEFAULT 'pendiente'" );
        }

        // --- CREACIÓN DE ROLES (Ejecutar una vez) ---
        add_role('suite_vendedor', 'Vendedor Suite', array('read' => true, 'suite_access' => true));
        add_role('suite_logistica', 'Logística Suite', array('read' => true, 'suite_access' => true));
        add_role('suite_marketing', 'Marketing y Análisis', array('read' => true, 'suite_access' => true));

        $admin = get_role('administrator');
        if ( $admin ) {
            $admin->add_cap('suite_access'); // Asegurar que admin tenga acceso
        }
    }
}
```

### ARCHIVO: `includes/Models/class-suite-model-base.php`
```php
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
```

