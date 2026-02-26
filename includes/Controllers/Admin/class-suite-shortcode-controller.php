<?php
/**
 * Controlador de Interfaz: Renderizado de la App y Encolado de Assets
 *
 * Clase responsable de inyectar el sistema en el frontend mediante shortcodes,
 * verificar permisos (Role-Level Security) y orquestar las dependencias JavaScript (ES6).
 *
 * @package SuiteEmpleados\Controllers\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Shortcode_Controller {

    /**
     * Constructor: Engancha los métodos a los hooks de WordPress.
     */
    public function __construct() {
        // Encolar scripts en el frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // Registrar el shortcode [suite_empleados_v8]
        add_shortcode( 'suite_empleados_v8', [ $this, 'render_app' ] );
    }

    /**
     * Encola los estilos, librerías externas y módulos JS en el orden correcto.
     */
    public function enqueue_assets() {
        global $post;

        // Validar que estamos en la página del shortcode antes de cargar assets
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'suite_empleados_v8' ) ) {
            
            // 1. Estilos Clásicos (CSS Actual)
            wp_enqueue_style( 'suite-styles', SUITE_URL . 'assets/css/suite-styles.css', [], SUITE_VERSION );

            // 2. Librerías Externas vía CDN
            wp_enqueue_style( 'dt-css', 'https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/r-2.5.0/datatables.min.css' );
            wp_enqueue_script( 'dt-js', 'https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/r-2.5.0/datatables.min.js', ['jquery'], '1.13.6', true );
            wp_enqueue_script( 'sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', [], null, true );
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true );

            // 3. Arquitectura Core JS (Dependencias base)
            wp_enqueue_script( 'suite-api-js', SUITE_URL . 'assets/js/core/api.js', ['jquery'], SUITE_VERSION, true );
            wp_enqueue_script( 'suite-state-js', SUITE_URL . 'assets/js/core/state.js', [], SUITE_VERSION, true );
            
            // 4. Módulos Funcionales (Dependen del Core)
            wp_enqueue_script( 'suite-crm-js', SUITE_URL . 'assets/js/modules/crm.js', ['suite-api-js', 'dt-js'], SUITE_VERSION, true );
            wp_enqueue_script( 'suite-quoter-js', SUITE_URL . 'assets/js/modules/quoter.js', ['suite-api-js', 'suite-state-js'], SUITE_VERSION, true );
            wp_enqueue_script( 'suite-kanban-js', SUITE_URL . 'assets/js/modules/kanban.js', ['suite-api-js', 'sortable-js'], SUITE_VERSION, true );
            wp_enqueue_script( 'suite-commissions-js', SUITE_URL . 'assets/js/modules/commissions.js', ['suite-api-js'], SUITE_VERSION, true );
            wp_enqueue_script( 'suite-logistics-js', SUITE_URL . 'assets/js/modules/logistics.js', ['suite-api-js'], SUITE_VERSION, true );
            wp_enqueue_script( 'suite-marketing-js', SUITE_URL . 'assets/js/modules/marketing.js', ['suite-api-js', 'chart-js'], SUITE_VERSION, true );
			wp_enqueue_script( 'suite-employees-js', SUITE_URL . 'assets/js/modules/employees.js', ['suite-api-js', 'dt-js'], SUITE_VERSION, true );

            // 5. Orquestador Principal (Carga al final)
            wp_enqueue_script( 'suite-main-js', SUITE_URL . 'assets/js/main.js', [
                'suite-crm-js', 
                'suite-quoter-js', 
                'suite-kanban-js', 
                'suite-commissions-js', 
                'suite-logistics-js', 
                'suite-marketing-js',
				'suite-employees-js'
            ], SUITE_VERSION, true );

			
			// 6. Variables Globales de Entorno e Inyección de Nonce de Seguridad
            $user_actual = wp_get_current_user();
            $roles_actuales = (array) $user_actual->roles;
            $is_gerente = in_array( 'suite_gerente', $roles_actuales ) || in_array( 'gerente', $roles_actuales );
            $can_export = current_user_can( 'manage_options' ) || $is_gerente;			
			
            wp_localize_script( 'suite-api-js', 'suite_vars', [
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'suite_quote_nonce' ),
                'is_admin'   => current_user_can( 'manage_options' ),
                'rest_url'   => esc_url_raw( rest_url() ),
                'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'can_export' => $can_export
            ] );
        }
    }

	/**
     * Renderiza el contenido del shortcode.
     */
    public function render_app( $atts ) {
        
        // --- 1. BARRERA DE AUTENTICACIÓN ---
        if ( ! is_user_logged_in() ) {
            return '<div style="padding:20px; text-align:center; font-family:sans-serif;">🔒 Inicie sesión para acceder a la Intranet de RV Tech.</div>';
        }

        // --- 2. CONTROL DE ROLES DINÁMICO (RBAC) ---
        // Se abandona el chequeo estático de roles por validación de Capacidades (Capabilities)
        $es_admin        = current_user_can( 'manage_options' );
        $can_view_crm    = $es_admin || current_user_can( 'suite_view_crm' );
        $can_view_kanban = $es_admin || current_user_can( 'suite_view_kanban' );
        $can_view_comis  = $es_admin || current_user_can( 'suite_view_commissions' );
        $can_view_logis  = $es_admin || current_user_can( 'suite_view_logistics' );
        $can_view_bi     = $es_admin || current_user_can( 'suite_view_marketing' );
        $can_manage_team = $es_admin || current_user_can( 'suite_manage_team' );

        // Si no tiene ningún permiso, se bloquea el renderizado por completo
        if ( ! $es_admin && ! $can_view_crm && ! $can_view_kanban && ! $can_view_comis && ! $can_view_logis && ! $can_view_bi && ! $can_manage_team ) {
            return '<div style="padding:20px; text-align:center; color:#dc2626; font-family:sans-serif;">⛔ Acceso Denegado. Usted no tiene permisos asignados en el ERP.</div>';
        }

        // --- 3. PRE-CARGA DE DATOS PARA LAS VISTAS ---
        $clientes = [];
        $pedidos_logistica = [];

        // Datos para el CRM
        if ( $can_view_crm || $can_view_logis ) {
            $clientModel = new Suite_Model_Client();
            $clientes    = $clientModel->get_all( 200 );
        }

        // Datos para Almacén (Logística)
        if ( $can_view_logis || $can_view_kanban ) {
            $quoteModel = new Suite_Model_Quote();
            $kanban_data = $quoteModel->get_kanban_orders( null, true );
            $pedidos_logistica = isset( $kanban_data['pagado'] ) ? $kanban_data['pagado'] : [];
        }

        // --- 4. RENDERIZADO DEL HTML Y COMPONENTES ---
        ob_start();

        echo '<div class="suite-wrap">';
        
        // A. MENÚ DE PESTAÑAS (Renderizado dinámico basado en permisos)
        echo '<div class="suite-tabs-modern">';
        
        // Pestañas Operativas Generales (Ventas y Administración)
        if ( $can_view_crm ) {
            echo '<button class="tab-btn active" onclick="openSuiteTab(event, \'TabCli\')">👥 Clientes</button>';
            echo '<button class="tab-btn" onclick="openSuiteTab(event, \'TabPos\')">📝 Cotizador</button>';
        }

        if ( $can_view_kanban ) {
            echo '<button class="tab-btn" onclick="openSuiteTab(event, \'TabKanban\')">📦 Pedidos</button>';
        }
        
        // Comisiones (Ventas)
        if ( $can_view_comis ) {
            echo '<button class="tab-btn" onclick="openSuiteTab(event, \'TabComisiones\')">🏆 Comisiones</button>';
        }
        
        // Pestaña Protegida: Logística
        if ( $can_view_logis ) {
            echo '<button class="tab-btn" onclick="openSuiteTab(event, \'TabLogistica\')" style="color:#0369a1; font-weight:bold;">🚚 Logística</button>';
        }
        
        // Pestaña Protegida: BI & Marketing
        if ( $can_view_bi ) {
            echo '<button class="tab-btn" onclick="openSuiteTab(event, \'TabMarketing\')" style="color:#dc2626; font-weight:bold;">📈 BI & Marketing</button>';
        }
		
		// Pestaña Protegida: Gestión de Equipo (RBAC)
        if ( $can_manage_team ) { 
            echo '<button class="tab-btn" onclick="openSuiteTab(event, \'TabEquipo\')" style="color:#0f172a; font-weight:bold;">⚙️ Equipo y Accesos</button>';
        }
        
        echo '</div>'; // Fin menú de pestañas

        // B. CARGA DE VISTAS (Inyección de plantillas limpia y ordenada)
        if ( $can_view_crm ) {
            require SUITE_PATH . 'views/app/tab-clientes.php';
            require SUITE_PATH . 'views/app/tab-cotizador.php';
        }

        if ( $can_view_kanban ) {
            require SUITE_PATH . 'views/app/tab-kanban.php';
        }
        
        if ( $can_view_comis ) {
            require SUITE_PATH . 'views/app/tab-comisiones.php';
        }

        if ( $can_view_logis ) {
            require SUITE_PATH . 'views/app/tab-logistica.php';
        }
        
        if ( $can_view_bi ) {
            require SUITE_PATH . 'views/app/tab-marketing.php';
        }

		// Nuevo Módulo de Equipo y Roles (RBAC)
        if ( $can_manage_team ) { 
            require SUITE_PATH . 'views/app/tab-equipo.php';
        }

        // C. INYECCIÓN DEL MOTOR JAVASCRIPT UNIFICADO
        // Se inicializan dinámicamente solo los módulos que el usuario haya descargado (según permisos)
        ?>
        <script>
            jQuery(document).ready(function($){
                
                // 1. INICIALIZACIÓN SEGURA DE MÓDULOS (Patrón Factory)
                if (typeof SuiteCRM !== "undefined") SuiteCRM.init();
                if (typeof SuiteQuoter !== "undefined") SuiteQuoter.init();
                if (typeof SuiteKanban !== "undefined") SuiteKanban.init();
                if (typeof SuiteCommissions !== "undefined") SuiteCommissions.init();
                if (typeof SuiteLogistics !== "undefined") SuiteLogistics.init();
                if (typeof SuiteMarketing !== "undefined") SuiteMarketing.init();
				if (typeof SuiteEmployees !== "undefined") SuiteEmployees.init();

                // 2. ENRUTADOR DE PESTAÑAS (Manejo de estado visual y recargas asíncronas)
                window.openSuiteTab = function(evt, name) {
                    
                    // Resetear la vista actual
                    $(".suite-tab-content").removeClass("active").hide();
                    $(".tab-btn").removeClass("active");
                    
                    // Activar la nueva pestaña
                    $("#" + name).fadeIn().addClass("active");
                    evt.currentTarget.classList.add("active");
                    
                    // Disparar las recargas de datos contextuales según la pestaña que se abra
                    if (name === "TabKanban" && typeof SuiteKanban !== "undefined") {
                        SuiteKanban.loadBoard();
                    }
                    if (name === "TabComisiones" && typeof SuiteCommissions !== "undefined") {
                        SuiteCommissions.loadDashboard();
                    }
                    if (name === "TabMarketing" && typeof SuiteMarketing !== "undefined") {
                        SuiteMarketing.loadDashboard();
                    }
                };

            });
        </script>
        <?php

        echo '</div>'; // Fin .suite-wrap

        return ob_get_clean();
    }
}
