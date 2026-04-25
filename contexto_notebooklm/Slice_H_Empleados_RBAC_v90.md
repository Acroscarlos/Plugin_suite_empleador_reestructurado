# 🧩 MÓDULO LÓGICO: Slice_H_Empleados_RBAC

### ARCHIVO: `assets/js/modules/employees.js`
```js
/**
 * SuiteEmployees - Módulo de Gestión de Empleados y Roles (RBAC)
 * 
 * Se encarga de inicializar las tablas de usuarios y perfiles,
 * renderizar la matriz dinámica de capacidades y procesar el CRUD.
 */
const SuiteEmployees = (function($) {
    'use strict';

    // ==========================================
    // VARIABLES PRIVADAS
    // ==========================================
    let empTable = null;
    let roleTable = null;
    let currentCapabilitiesDict = {}; // Almacena el diccionario devuelto por el Backend

    const dtLanguage = { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json", "lengthMenu": "_MENU_" };

    // ==========================================
    // MÉTODOS PRIVADOS (Renders y Utilidades)
    // ==========================================

    /**
     * Inicializa las tablas de DataTables
     */
    const initTables = function() {
        if ($('#employeesTable').length && !$.fn.DataTable.isDataTable('#employeesTable')) {
            empTable = $('#employeesTable').DataTable({
                responsive: true,
                language: dtLanguage,
                dom: 'lrtip', // Sin botones de exportación por defecto
                pageLength: 25
            });
        }

        if ($('#rolesTable').length && !$.fn.DataTable.isDataTable('#rolesTable')) {
            roleTable = $('#rolesTable').DataTable({
                responsive: true,
                language: dtLanguage,
                dom: 'lrtip',
                pageLength: 25
            });
        }
    };

    /**
     * Dibuja la matriz de Checkboxes agrupada por módulos
     */
    const renderCapabilitiesMatrix = function() {
        const container = $('#role-capabilities-matrix');
        let html = '';

        for (const [group, caps] of Object.entries(currentCapabilitiesDict)) {
            html += `<div class="cap-group"><h4>Módulo: ${group}</h4>`;
            
            for (const [capKey, capLabel] of Object.entries(caps)) {
                html += `
                    <label class="cap-item">
                        <input type="checkbox" name="role_caps[]" value="${capKey}"> 
                        ${capLabel}
                    </label>
                `;
            }
            html += `</div>`;
        }
        
        container.html(html);
    };

    /**
     * Limpia los campos del Modal de Empleados para crear uno nuevo
     */
    const resetEmployeeModal = function() {
        $('#emp-modal-title').text('Añadir Empleado');
        $('#emp-id').val('0');
        $('#emp-first-name, #emp-last-name, #emp-email, #emp-phone, #emp-password').val('');
        $('#emp-role').val('');
		$('#emp-is-b2b').prop('checked', false);
		$('#emp-participa-comisiones').prop('checked', false); 
    };

    /**
     * Limpia los campos del Modal de Roles para crear uno nuevo
     */
    const resetRoleModal = function() {
        $('#role-modal-title').text('Crear Nuevo Perfil');
        $('#role-key-hidden, #role-display-name, #role-key').val('');
        
        // Desbloquea el Key (Solo se bloquea al editar para evitar inconsistencias)
        $('#role-key').prop('readonly', false).css('background-color', '#fff');
        
        // Desmarca todos los checks
        $('input[name="role_caps[]"]').prop('checked', false);
    };

    // ==========================================
    // CARGA DE DATOS (AJAX)
    // ==========================================

	const loadEmployees = function() {
        if (!empTable) return;
        
        SuiteAPI.post('suite_get_employees').then(res => {
            if (res.success && res.data) {
                empTable.clear();
                
                res.data.forEach(e => {
                    // Determinar el rol principal y estilos visuales
                    // CORRECCIÓN: Extraemos el índice [0] si es array para garantizar que roleKey sea un texto puro
                    let roleKey = (Array.isArray(e.roles) && e.roles.length > 0) ? e.roles[0] : (e.roles ? Object.keys(e.roles)[0] : 'N/A');
                    let roleDisplay = (typeof roleKey === 'string') ? roleKey.replace('suite_', '').toUpperCase() : 'N/A';
                    let badgeClass = roleKey === 'administrator' ? 'pill-critico' : 'pill-neutral';

                    // Convertimos la data a Base64 para pasarla segura al botón OnClick
                    let safeData = btoa(unescape(encodeURIComponent(JSON.stringify(e))));
                    
                    let btnEdit = `<button class="btn-modern-action small" onclick="SuiteEmployees.editEmployee('${safeData}')">✏️ Editar</button>`;
                    let btnDel = `<button class="btn-modern-action small" style="color:#dc2626; border-color:#fca5a5;" onclick="SuiteEmployees.deleteEmployee(${e.id})">🗑️ Borrar</button>`;
                    
                    empTable.row.add([
                        `<strong>${e.nombre}</strong>`,
                        e.email,
                        e.telefono,
                        `<span class="status-pill ${badgeClass}">${roleDisplay}</span>`,
                        `<div style="display:flex; gap:8px;">${btnEdit} ${btnDel}</div>`
                    ]);
                });
                
                empTable.draw();
            }
        });
    };
	
	
    const loadRoles = function() {
        if (!roleTable) return;

        SuiteAPI.post('suite_get_roles').then(res => {
            if (res.success && res.data) {
                
                const roles = res.data.roles;
                currentCapabilitiesDict = res.data.diccionario;

                roleTable.clear();
                
                // Limpiar y preparar el selector de roles del modal de Empleados
                const selectRole = $('#emp-role');
                selectRole.empty().append('<option value="">Seleccione un rol...</option>');

                // Procesamos cada Rol devuelto
                for (const [key, roleData] of Object.entries(roles)) {
                    let name = roleData.name;
                    let caps = roleData.capabilities; // Objeto { 'read': true, 'suite_view_crm': true }
                    
                    // Extraer solo las capabilities de la Suite para pintar los badges visuales
                    let activeCaps = Object.keys(caps).filter(c => caps[c] && c.startsWith('suite_'));
                    let capsBadges = `<div class="role-tags-container">` + activeCaps.map(c => `<span>${c}</span>`).join('') + `</div>`;

                    let safeData = btoa(unescape(encodeURIComponent(JSON.stringify({key: key, name: name, activeCaps: activeCaps}))));

                    let btnEdit = `<button class="btn-modern-action small" onclick="SuiteEmployees.editRole('${safeData}')">✏️ Editar</button>`;
                    let btnDel = '';
                    
                    // Protección frontend para no mostrar botón de borrado en roles críticos
                    if (!['administrator', 'subscriber', 'editor'].includes(key)) {
                        btnDel = `<button class="btn-modern-action small" style="color:#dc2626; border-color:#fca5a5;" onclick="SuiteEmployees.deleteRole('${key}')">🗑️ Borrar</button>`;
                    }

                    roleTable.row.add([
                        `<strong>${name}</strong><br><small class="font-mono text-gray-500">${key}</small>`,
                        capsBadges || '<small class="text-gray-400">Sin permisos de Suite</small>',
                        `<div style="display:flex; gap:8px;">${btnEdit} ${btnDel}</div>`
                    ]);

                    // Añadir la opción al desplegable
                    selectRole.append(`<option value="${key}">${name}</option>`);
                }
                
                roleTable.draw();
                renderCapabilitiesMatrix(); // Pintamos la matriz de Checkboxes
            }
        });
    };

    // ==========================================
    // LISTENERS DE FORMULARIOS (Guardar)
    // ==========================================

    const bindEvents = function() {

        // Interceptar botones HTML originales "Nuevo" para limpiar formularios
        $('button[onclick*="#modal-employee"]').on('click', resetEmployeeModal);
        $('button[onclick*="#modal-role"]').on('click', resetRoleModal);

        // Guardar Empleado
        $('#btn-save-employee').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            
            const payload = {
                id: $('#emp-id').val(),
                first_name: $('#emp-first-name').val().trim(),
                last_name: $('#emp-last-name').val().trim(),
                email: $('#emp-email').val().trim(),
                telefono: $('#emp-phone').val().trim(),
                password: $('#emp-password').val(),
                role: $('#emp-role').val(),
				is_b2b: $('#emp-is-b2b').is(':checked') ? 1 : 0,
				participa_comisiones: $('#emp-participa-comisiones').is(':checked') ? 1 : 0
            };

            if (!payload.email || !payload.role) {
                return alert('⚠️ El Correo Electrónico y el Rol son obligatorios.');
            }

            btn.prop('disabled', true).text('Procesando...');

            SuiteAPI.post('suite_save_employee', payload).then(res => {
                if (res.success) {
                    alert('✅ ' + (res.data.message || 'Empleado guardado con éxito.'));
                    $('#modal-employee').fadeOut();
                    loadEmployees();
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            }).catch(() => {
                alert('❌ Error de red al intentar guardar el empleado.');
            }).finally(() => {
                btn.prop('disabled', false).text('💾 Guardar Empleado');
            });
        });

        // Guardar Rol y Permisos
        $('#btn-save-role').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);

            // Recolectar checkboxes marcados
            const caps = [];
            $('input[name="role_caps[]"]:checked').each(function() {
                caps.push($(this).val());
            });

            // Usamos el hidden_key como fallback, o el input visible si es nuevo
            const hiddenKey = $('#role-key-hidden').val();
            const roleKey = hiddenKey ? hiddenKey : $('#role-key').val().trim().toLowerCase();

            const payload = {
                role_key: roleKey,
                display_name: $('#role-display-name').val().trim(),
                capabilities: caps
            };

            if (!payload.role_key || !payload.display_name) {
                return alert('⚠️ El Nombre y el Identificador (Key) son obligatorios.');
            }

            btn.prop('disabled', true).text('Procesando...');

            SuiteAPI.post('suite_save_role', payload).then(res => {
                if (res.success) {
                    alert('✅ Rol guardado y permisos aplicados con éxito.');
                    $('#modal-role').fadeOut();
                    loadRoles(); // Recarga la tabla y el select del otro modal
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            }).catch(() => {
                alert('❌ Error de red al intentar guardar el rol.');
            }).finally(() => {
                btn.prop('disabled', false).text('🛡️ Guardar Perfil y Permisos');
            });
        });
		
        // --- INICIO FASE 4.1: INTERCEPTOR DE MATRIZ DE PERMISOS ---
        $('#matriz-permisos').on('change', '.toggle-capability', function(e) {
            const checkbox = $(this);
            const role = checkbox.data('role');
            const cap = checkbox.data('cap');
            const isGranted = checkbox.is(':checked') ? 1 : 0;

            // Bloqueo temporal para prevenir spam de clics
            checkbox.prop('disabled', true);

            SuiteAPI.post('suite_update_role_cap', {
                role: role,
                capability: cap,
                is_granted: isGranted
            }).then(res => {
                if (res.success) {
                    // Notificación Toast sutil
                    const toast = document.createElement('div');
                    toast.textContent = '✅ ' + (res.data.message || res.data);
                    toast.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#10b981; color:white; padding:12px 20px; border-radius:8px; z-index:9999; box-shadow:0 4px 6px rgba(0,0,0,0.1); transition: opacity 0.4s ease-out; font-size:14px;';
                    document.body.appendChild(toast);
                    
                    // Desvanecer el Toast
                    setTimeout(() => { 
                        toast.style.opacity = '0'; 
                        setTimeout(() => toast.remove(), 400); 
                    }, 2500);
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                    checkbox.prop('checked', !isGranted); // Reversión visual
                }
            }).catch(err => {
                alert('❌ Ocurrió un error de red al intentar actualizar la capacidad.');
                checkbox.prop('checked', !isGranted); // Reversión visual
            }).finally(() => {
                checkbox.prop('disabled', false);
            });
        });
        // --- FIN FASE 4.1 ---		
		
		
		
    };

    // ==========================================
    // API PÚBLICA (Revelado)
    // ==========================================
    return {
        init: function() {
            initTables();
            bindEvents();
            
            // Retraso ligero para permitir que la vista principal renderice
            setTimeout(() => {
                loadRoles(); // Cargar roles primero, ya que alimenta el Select de Empleados
                loadEmployees();
            }, 300);
        },

        // Expuestos para ser llamados desde los botones dinámicos en las Tablas
		editEmployee: function(base64Data) {
            const data = JSON.parse(decodeURIComponent(escape(atob(base64Data))));
            
            $('#emp-modal-title').text('Editar Empleado');
            $('#emp-id').val(data.id);
            
            // Tratar de separar el nombre (Fase 1-C no retorna First/Last individualmente por compatibilidad)
            let parts = data.nombre.split(' ');
            // CORRECCIÓN: Asignamos parts[0] para asegurar que tome solo el primer string y no el array completo
            $('#emp-first-name').val(parts[0] || '');
            $('#emp-last-name').val(parts.slice(1).join(' ') || '');
            
            $('#emp-email').val(data.email);
            $('#emp-phone').val(data.telefono !== '-' ? data.telefono : '');
            $('#emp-password').val(''); // Se deja vacío para no alterar
            
            // Seleccionar rol en el dropdown
            // CORRECCIÓN: Extraemos el índice [0] del array para que jQuery seleccione correctamente el <option>
            let rKey = (Array.isArray(data.roles) && data.roles.length > 0) ? data.roles[0] : (data.roles ? Object.keys(data.roles)[0] : '');
            $('#emp-role').val(rKey);
			$('#emp-is-b2b').prop('checked', data.is_b2b === '1' || data.is_b2b === 1);
			$('#emp-participa-comisiones').prop('checked', data.participa_comisiones === '1' || data.participa_comisiones === 1);
            $('#modal-employee').fadeIn();
        },

        deleteEmployee: function(id) {
            if (!confirm('¿Está seguro de eliminar a este empleado? Perderá el acceso al ERP inmediatamente.')) return;
            
            SuiteAPI.post('suite_delete_employee', { id: id }).then(res => {
                if (res.success) {
                    alert('✅ Empleado eliminado.');
                    loadEmployees();
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            });
        },

        editRole: function(base64Data) {
            const data = JSON.parse(decodeURIComponent(escape(atob(base64Data))));
            
            $('#role-modal-title').text('Editar Perfil');
            $('#role-key-hidden').val(data.key);
            
            // Bloqueamos el input del Key porque WP no permite renombrarlo de forma trivial
            $('#role-key').val(data.key).prop('readonly', true).css('background-color', '#f1f5f9');
            $('#role-display-name').val(data.name);

            // Desmarcar todos y marcar solo los activos
            $('input[name="role_caps[]"]').prop('checked', false);
            if (data.activeCaps) {
                data.activeCaps.forEach(cap => {
                    $(`input[name="role_caps[]"][value="${cap}"]`).prop('checked', true);
                });
            }

            $('#modal-role').fadeIn();
        },

        deleteRole: function(key) {
            if (!confirm('¿Eliminar este perfil? Si existen usuarios asignados a este rol, perderán su nivel de acceso.')) return;
            
            SuiteAPI.post('suite_delete_role', { role_key: key }).then(res => {
                if (res.success) {
                    alert('✅ Perfil eliminado.');
                    loadRoles();
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            });
        }
    };

})(jQuery);
```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-roles.php`
```php
<?php
/**
 * Controlador AJAX: Gestión de Roles y Permisos Dinámicos (FASE 2-B)
 *
 * Maneja las peticiones del frontend para listar, crear, editar y eliminar 
 * roles personalizados (RBAC) basándose en las Capacidades (Capabilities).
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Obtener Lista de Roles y el Diccionario de Capacidades
 */
class Suite_Ajax_Role_List extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_roles';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'suite_manage_team' ) ) {
            $this->send_error( 'Acceso denegado', 403 );
        }

        $model = new Suite_Model_Roles();

        $this->send_success( [
            'roles'       => $model->get_all_roles(),
            'diccionario' => $model->get_capabilities_dictionary()
        ] );
    }
}

/**
 * 2. Crear o Actualizar un Rol (y sus Capacidades)
 */
class Suite_Ajax_Role_Save extends Suite_AJAX_Controller {

    protected $action_name = 'suite_save_role';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'suite_manage_team' ) ) {
            $this->send_error( 'Acceso denegado', 403 );
        }

        $role_key     = isset( $_POST['role_key'] ) ? sanitize_key( $_POST['role_key'] ) : '';
        $display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( $_POST['display_name'] ) : '';
        // AJAX puede enviar arrays si se estructuran correctamente en el FormData o Payload
        $capabilities = isset( $_POST['capabilities'] ) && is_array( $_POST['capabilities'] ) ? $_POST['capabilities'] : [];

        if ( empty( $role_key ) || empty( $display_name ) ) {
            $this->send_error( 'El identificador del rol y el nombre a mostrar son obligatorios.' );
        }

        $model = new Suite_Model_Roles();
        $result = $model->create_or_update_role( $role_key, $display_name, $capabilities );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message() );
        }

        $this->send_success( 'Rol guardado y permisos actualizados correctamente.' );
    }
}

/**
 * 3. Eliminar un Rol
 */
class Suite_Ajax_Role_Delete extends Suite_AJAX_Controller {

    protected $action_name = 'suite_delete_role';
    protected $required_capability = 'read';

    protected function process() {
        // Zero-Trust Security: Barrera estricta de capacidades
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'suite_manage_team' ) ) {
            $this->send_error( 'Acceso denegado', 403 );
        }

        $role_key = isset( $_POST['role_key'] ) ? sanitize_key( $_POST['role_key'] ) : '';

        if ( empty( $role_key ) ) {
            $this->send_error( 'Identificador del rol no válido.' );
        }

        $model = new Suite_Model_Roles();
        $result = $model->delete_role( $role_key );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 403 ); // 403 si intentó borrar rol protegido
        }

        $this->send_success( 'Rol eliminado del sistema.' );
    }
}



/**
 * Controlador AJAX: Actualizador de Capacidades de la Matriz RBAC (Fase 4.1)
 */
class Suite_Ajax_Update_Role_Cap extends Suite_AJAX_Controller {

    protected $action_name = 'suite_update_role_cap';
    // Barrera Zero-Trust: Solo quien puede gestionar opciones (El Admin maestro)
    protected $required_capability = 'manage_options'; 

    protected function process() {
        $role_name  = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : '';
        $capability = isset( $_POST['capability'] ) ? sanitize_text_field( $_POST['capability'] ) : '';
        $is_granted = isset( $_POST['is_granted'] ) && $_POST['is_granted'] == '1';

        if ( empty( $role_name ) || empty( $capability ) ) {
            $this->send_error( 'Faltan parámetros requeridos (rol o capacidad).' );
        }

        // Blindaje adicional: Proteger al administrador maestro
        if ( $role_name === 'administrator' ) {
            $this->send_error( 'Acción bloqueada: No se pueden modificar las capacidades del Administrador maestro.' );
        }

        $role_obj = get_role( $role_name );

        if ( ! $role_obj ) {
            $this->send_error( 'El rol especificado no existe en el sistema.' );
        }

        // Lógica Central: Añadir o Quitar Capacidad
        if ( $is_granted ) {
            $role_obj->add_cap( $capability );
            $mensaje = "Capacidad '{$capability}' ASIGNADA al rol '{$role_name}'.";
        } else {
            $role_obj->remove_cap( $capability );
            $mensaje = "Capacidad '{$capability}' REVOCADA del rol '{$role_name}'.";
        }

        // Auditoría
        if ( function_exists( 'suite_record_log' ) ) {
            suite_record_log( 'matriz_rbac', "Matriz de Permisos: " . $mensaje );
        }

        $this->send_success( $mensaje );
    }
}
```

### ARCHIVO: `includes/Models/class-suite-model-roles.php`
```php
<?php
/**
 * Modelo de Base de Datos: Gestor Dinámico de Roles y Permisos
 *
 * Actúa como un Wrapper (envoltura) de la clase global WP_Roles de WordPress.
 * Permite gestionar roles personalizados y asignar capacidades (Capabilities)
 * de forma dinámica para el ERP.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Roles {

    /**
     * Devuelve el diccionario maestro de capacidades del ERP agrupadas por módulos.
     * Este array alimenta la matriz de checkboxes en la interfaz de usuario.
     *
     * @return array
     */
	/**
     * Devuelve el diccionario estructurado de capacidades de la Suite
     */
	public function get_capabilities_dictionary() {
        // FASE 4.2: Diccionario Unificado de Capacidades (Sincronizado con Matriz RBAC)
        return [
            'Nivel 1 (Vistas)' => [
                'suite_access'           => 'Acceso Base al Sistema (Login)',
                'suite_view_crm'         => 'Ver Directorio de Clientes',
                'suite_view_quotes'      => 'Ver Cotizador e Historial',
                'suite_view_kanban'      => 'Ver Tablero Kanban (Pedidos)',
                'suite_view_inventory'   => 'Ver Control de Inventario',
                'suite_view_commissions' => 'Ver Panel de Comisiones y Ledger', // Actualizado
                'suite_view_logistics'   => 'Ver Módulo de Logística',
                'suite_view_marketing'   => 'Ver Módulo BI & Marketing',
                'suite_manage_team'      => 'Gestión de Equipo (RBAC)'
            ],
            'Nivel 2 (Acciones Críticas)' => [
                'suite_change_status'              => 'Cambiar Estados (Mover Kanban)',
                'suite_action_approve_commissions' => 'Liquidar Pagos y Registrar Abonos', // Renombrado a su función real
                'suite_freeze_commissions'         => 'Ejecutar Cierre de Mes (Premios)',  // NUEVO: Poder supremo mensual
                'suite_action_reverse_logistics'   => 'Aprobar Logística Inversa'
            ],
            'Nivel 3 (Datos Sensibles)' => [
                'suite_data_detailed_stock'    => 'Ver Costos Base de Stock',
                'suite_data_marketing_transit' => 'Ver Data de Tránsito en BI',
				'suite_view_all_customers'     => 'Ver Todos los Clientes (Global)',
				'suite_view_all_quotes'        => 'Ver Todo el Historial de Cotizaciones'
            ]
        ];
    }

    /**
     * Obtiene todos los roles registrados en el sistema WordPress.
     *
     * @return array Array de roles.
     */
    public function get_all_roles() {
        global $wp_roles;
        
        // Asegurar que la clase global esté instanciada
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = wp_roles();
        }

        return $wp_roles->roles;
    }

    /**
     * Crea un rol nuevo o actualiza uno existente (Nombre y Capacidades).
     *
     * @param string $role_key Identificador único (slug) del rol (ej. 'vendedor_senior').
     * @param string $display_name Nombre público del rol (ej. 'Vendedor Senior').
     * @param array $capabilities_array Array simple con los keys de las capacidades activadas.
     * @return true|WP_Error
     */
    public function create_or_update_role( $role_key, $display_name, $capabilities_array ) {
        global $wp_roles;

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = wp_roles();
        }

        $role_key     = sanitize_key( $role_key );
        $display_name = sanitize_text_field( $display_name );
        $role_obj     = get_role( $role_key );

        if ( ! $role_obj ) {
            // 1. CREACIÓN: El rol no existe, usar add_role
            $role_obj = add_role( $role_key, $display_name );
            if ( ! $role_obj ) {
                return new WP_Error( 'role_creation_failed', 'Error interno al intentar crear el rol.' );
            }
        } else {
            // 2. ACTUALIZACIÓN DE NOMBRE: WordPress no tiene función nativa para renombrar. 
            // Se debe actualizar directamente en el array global y guardar en la BD.
            $wp_roles->roles[ $role_key ]['name'] = $display_name;
            $wp_roles->role_names[ $role_key ]    = $display_name;
            update_option( $wp_roles->role_key, $wp_roles->roles );
        }

        // 3. LIMPIEZA DE CAPACIDADES: Revocar todas las capacidades específicas de la Suite
        // Esto garantiza que si el usuario desmarcó un checkbox, el permiso se elimine.
        $dictionary = $this->get_capabilities_dictionary();
        foreach ( $dictionary as $group => $caps ) {
            foreach ( $caps as $cap_key => $cap_label ) {
                $role_obj->remove_cap( $cap_key );
            }
        }

        // 4. ASIGNACIÓN: Otorgar las capacidades recibidas en el payload
        if ( is_array( $capabilities_array ) && ! empty( $capabilities_array ) ) {
            foreach ( $capabilities_array as $cap ) {
                $role_obj->add_cap( sanitize_key( $cap ) );
            }
        }

        // 5. PERMISOS BASE OBLIGATORIOS
        $role_obj->add_cap( 'read' );         // Necesario para entrar al wp-admin/intranet
        $role_obj->add_cap( 'suite_access' ); // Bandera general de pertenencia al ERP

        return true;
    }

    /**
     * Elimina un rol del sistema.
     * Posee una barrera de seguridad para no destruir roles críticos.
     *
     * @param string $role_key Identificador único del rol.
     * @return true|WP_Error
     */
    public function delete_role( $role_key ) {
        $role_key = sanitize_key( $role_key );

        // BARRERA DE SEGURIDAD: Prohibido borrar roles críticos
        $protected_roles = [ 'administrator', 'subscriber', 'editor', 'author', 'contributor' ];
        
        if ( in_array( $role_key, $protected_roles, true ) ) {
            return new WP_Error( 'protected_role', 'Seguridad: No se permite eliminar los roles nativos del núcleo de WordPress.' );
        }

        $role_obj = get_role( $role_key );
        
        if ( ! $role_obj ) {
            return new WP_Error( 'role_not_found', 'El rol que intenta eliminar no existe.' );
        }

        // TODO: (Opcional Arquitectónico) Verificar si existen usuarios con este rol antes de borrarlo
        // y pasarlos a un rol "subscriber" temporal.

        remove_role( $role_key );

        return true;
    }

}
```

### ARCHIVO: `views/app/tab-equipo.php`
```php
<?php
/**
 * Vista: Pestaña de Gestión de Equipo y Roles (RBAC) - Fase 3
 * 
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<style>
/* =========================================================
   ESTILOS: SUB-PESTAÑAS Y MATRIZ DE PERMISOS (RBAC)
   ========================================================= */
.equipo-subtabs {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.equipo-subtab-btn {
    padding: 12px 20px;
    cursor: pointer;
    background: none;
    border: none;
    font-weight: 600;
    font-size: 14px;
    color: #64748b;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.3s;
}

.equipo-subtab-btn:hover {
    color: #0f172a;
}

.equipo-subtab-btn.active {
    color: #0073aa;
    border-bottom-color: #0073aa;
}

.equipo-section {
    display: none;
    animation: fadeIn 0.3s ease-in-out;
}

.equipo-section.active {
    display: block;
}

/* MATRIZ DE CAPABILIDADES (GRID) */
.cap-matrix-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
    margin-top: 10px;
    max-height: 350px;
    overflow-y: auto;
    padding-right: 5px;
}

.cap-group {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
}

.cap-group h4 {
    margin: 0 0 12px 0;
    font-size: 13px;
    color: #0f172a;
    text-transform: uppercase;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 6px;
}

.cap-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 13px;
    color: #475569;
}

.cap-item:last-child {
    margin-bottom: 0;
}

.cap-item input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
    width: 16px;
    height: 16px;
    accent-color: #0073aa;
}

.role-tags-container span {
    display: inline-block;
    background: #e0f2fe;
    color: #0369a1;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin: 2px;
    font-weight: 600;
}
</style>

<!-- CONTENEDOR PRINCIPAL DE LA PESTAÑA -->
<div id="TabEquipo" class="suite-tab-content" style="display: none;">
    
    <div class="suite-header-modern" style="padding-bottom: 0; border-bottom: none;">
        <h2 style="margin:0; font-size: 22px; color: #0f172a;">👥 Gestión de Equipo y Permisos</h2>
    </div>
    
    <!-- SUB-PESTAÑAS -->
    <div class="equipo-subtabs" style="padding: 0 25px;">
        <button class="equipo-subtab-btn active" onclick="toggleEquipoView('empleados', this)">👥 Lista de Empleados</button>
        <button class="equipo-subtab-btn" onclick="toggleEquipoView('roles', this)">🛡️ Perfiles y Permisos</button>
    </div>
    
    <div style="padding: 0 25px 25px 25px;">
        
        <!-- ==========================================
             SECCIÓN 1: EMPLEADOS
             ========================================== -->
        <div id="sec-empleados" class="equipo-section active">
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
                <p style="margin: 0; color: #64748b; font-size: 14px;">Administre los accesos de su equipo de trabajo al ERP.</p>
                <button class="btn-save-big" style="width: auto; padding: 10px 20px;" onclick="jQuery('#modal-employee').fadeIn();">➕ Nuevo Empleado</button>
            </div>
            
            <div class="suite-table-responsive">
                <table class="suite-modern-table" id="employeesTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email / Usuario</th>
                            <th>Teléfono</th>
                            <th>Rol Asignado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="employees-tbody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ==========================================
             SECCIÓN 2: ROLES (RBAC)
             ========================================== -->
        <div id="sec-roles" class="equipo-section">
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
                <p style="margin: 0; color: #64748b; font-size: 14px;">Cree perfiles personalizados y asigne capacidades específicas de los módulos.</p>
                <button class="btn-save-big" style="width: auto; padding: 10px 20px;" onclick="jQuery('#modal-role').fadeIn();">➕ Crear Nuevo Perfil</button>
            </div>
            
            <div class="suite-table-responsive">
                <table class="suite-modern-table" id="rolesTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Perfil</th>
                            <th style="width: 55%;">Permisos Activos</th>
                            <th style="width: 20%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="roles-tbody">
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

<!-- ==========================================
     MODAL: CRUD DE EMPLEADOS
     ========================================== -->
<div id="modal-employee" class="suite-modal">
    <div class="suite-modal-content">
        <span class="close-modal" onclick="jQuery('#modal-employee').fadeOut();">&times;</span>
        <h3 id="emp-modal-title" style="margin-top:0; color:#0f172a;">Añadir Empleado</h3>
        
        <input type="hidden" id="emp-id" value="0">
        
        <div class="form-group-row">
            <div>
                <label>Nombre</label>
                <input type="text" id="emp-first-name" class="widefat" placeholder="Ej: Juan">
            </div>
            <div>
                <label>Apellido</label>
                <input type="text" id="emp-last-name" class="widefat" placeholder="Ej: Pérez">
            </div>
        </div>
        
        <label>Correo Electrónico (Usuario de Acceso) *</label>
        <input type="email" id="emp-email" class="widefat" placeholder="juan@mitiendaunit.com">
        
        <div class="form-group-row">
            <div>
                <label>Teléfono (Para PDF) *</label>
                <input type="text" id="emp-phone" class="widefat" placeholder="Ej: +58 412 1234567">
            </div>
            <div>
                <label>Contraseña <small style="font-weight:normal; color:#64748b;">(Dejar en blanco para no cambiar)</small></label>
                <input type="password" id="emp-password" class="widefat" placeholder="••••••••">
            </div>
        </div>
        
        <label>Rol / Perfil de Acceso *</label>
        <select id="emp-role" class="widefat">
            <option value="">Seleccione un rol...</option>
            <!-- JS inyectará opciones aquí -->
        </select>
		
		<div class="form-group-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
			<label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#475569; font-size:14px; font-weight:600;">
				<input type="checkbox" id="emp-participa-comisiones" name="participa_comisiones" value="1" style="width:18px; height:18px; cursor:pointer;">
				🏆 Participa en Gamificación y Comisiones
			</label>
		</div>
		
		<div class="form-group-row" style="margin-top: 10px;">
			<label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#475569; font-size:14px; font-weight:600;">
				<input type="checkbox" id="emp-is-b2b" name="is_b2b" value="1" style="width:18px; height:18px; cursor:pointer;">
				🤝 Es Aliado Comercial (Portal B2B)
			</label>
		</div>		
        
        <button type="button" id="btn-save-employee" class="btn-save-big" style="margin-top: 15px;">💾 Guardar Empleado</button>
    </div>
</div>

<!-- ==========================================
     MODAL: CRUD DE ROLES (MATRIZ DE PERMISOS)
     ========================================== -->
<div id="modal-role" class="suite-modal">
    <div class="suite-modal-content" style="max-width: 700px;">
        <span class="close-modal" onclick="jQuery('#modal-role').fadeOut();">&times;</span>
        <h3 id="role-modal-title" style="margin-top:0; color:#0f172a;">Crear Nuevo Perfil</h3>
        
        <!-- El Key original se usa de referencia para actualizar, o se deja vacío para crear uno nuevo -->
        <input type="hidden" id="role-key-hidden" value="">
        
        <div class="form-group-row">
            <div>
                <label>Nombre del Perfil *</label>
                <input type="text" id="role-display-name" class="widefat" placeholder="Ej: Vendedor Senior">
            </div>
            <div>
                <label>Identificador del Sistema (Key) *</label>
                <input type="text" id="role-key" class="widefat" placeholder="ej: vendedor_senior" title="Solo minúsculas y guiones bajos" oninput="this.value = this.value.replace(/[^a-z_]/g, '')">
            </div>
        </div>
        
        <label style="margin-top: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px;">Asignación de Permisos (Capabilities)</label>
        
        <!-- CONTENEDOR DINÁMICO DE CHECKBOXES -->
        <div id="role-capabilities-matrix" class="cap-matrix-grid">
            <div style="color: #94a3b8; font-size: 13px;">Cargando matriz de permisos...</div>
        </div>
        
        <button type="button" id="btn-save-role" class="btn-save-big" style="margin-top: 25px;">🛡️ Guardar Perfil y Permisos</button>
    </div>
</div>

<!-- Pequeño script inline para el manejo exclusivo visual de las Sub-pestañas -->
<script>
function toggleEquipoView(section, btn) {
    // Manejo de botones
    jQuery('.equipo-subtab-btn').removeClass('active');
    jQuery(btn).addClass('active');
    
    // Manejo de secciones
    jQuery('.equipo-section').removeClass('active').hide();
    jQuery('#sec-' + section).fadeIn().addClass('active');
}
</script>
```

