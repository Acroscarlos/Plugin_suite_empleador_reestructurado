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