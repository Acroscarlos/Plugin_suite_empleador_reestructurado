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
						<!-- INICIO FASE 4.1: MATRIZ DE PERMISOS (RBAC) -->
			<div id="matriz-permisos" style="margin-top: 40px; overflow-x: auto; padding-top: 20px; border-top: 2px solid #e2e8f0;">
                <h3 style="color: #0f172a; margin-bottom: 5px; font-size: 18px;">⚙️ Centro de Comando: Matriz de Permisos</h3>
                <p style="color:#64748b; font-size:13px; margin-bottom: 20px;">Habilite o deshabilite capacidades específicas en tiempo real para cada rol.</p>
                
                <?php
                $capacidades = [
                    'Nivel 1 (Vistas)'   => ['suite_view_crm', 'suite_view_quotes', 'suite_view_kanban', 'suite_view_inventory'],
                    'Nivel 2 (Acciones)' => ['suite_action_reverse_logistics', 'suite_action_approve_commissions'],
                    'Nivel 3 (Datos)'    => ['suite_data_detailed_stock', 'suite_data_marketing_transit']
                ];
                
                // Obtenemos todos los roles registrados en el sistema
                $roles_wp = wp_roles()->roles;
                ?>
                
                <table class="suite-modern-table" style="width: 100%; text-align: center;">
                    <thead>
                        <tr>
                            <th style="text-align: left; background: #f8fafc;">Rol / Perfil</th>
                            <?php foreach ( $capacidades as $grupo => $caps ) : ?>
                                <?php foreach ( $caps as $cap ) : ?>
                                    <th title="<?php echo esc_attr($grupo); ?>" style="min-width: 120px;">
                                        <small style="display:block; color:#64748b; font-size:10px; text-transform:uppercase;"><?php echo esc_html($grupo); ?></small>
                                        <?php echo esc_html($cap); ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $roles_wp as $role_slug => $role_details ) : ?>
                            <?php 
                            // Zero-Trust: Ocultamos el 'administrator' para evitar que se revoquen sus capacidades maestras por accidente
                            if ( $role_slug === 'administrator' ) continue; 
                            
                            $role_obj = get_role( $role_slug );
                            ?>
                            <tr>
                                <td style="text-align: left;">
                                    <strong><?php echo esc_html( translate_user_role( $role_details['name'] ) ); ?></strong><br>
                                    <small style="color:#94a3b8;"><?php echo esc_html($role_slug); ?></small>
                                </td>
                                <?php foreach ( $capacidades as $grupo => $caps ) : ?>
                                    <?php foreach ( $caps as $cap ) : ?>
                                        <td>
                                            <input type="checkbox" class="toggle-capability" 
                                                data-role="<?php echo esc_attr($role_slug); ?>" 
                                                data-cap="<?php echo esc_attr($cap); ?>" 
                                                <?php checked( $role_obj->has_cap($cap) ); ?>
                                                style="cursor: pointer; width: 18px; height: 18px;">
                                        </td>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <!-- FIN FASE 4.1 -->
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