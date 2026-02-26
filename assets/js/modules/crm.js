/**
 * SuiteCRM - Módulo de Gestión de Clientes
 * 
 * Controla la vista del directorio de clientes, inicialización de DataTables,
 * perfiles modales y creación manual.
 */
const SuiteCRM = (function($) {
    'use strict';

    // Variables Privadas
    let cliTable = null;
    const dtLanguage = { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json", "lengthMenu": "_MENU_" };

    /**
     * Limpia el RIF en tiempo real (Regla de negocio: Anti-duplicados) [1, 2]
     */
    const cleanRifInput = function(input) {
        let raw = $(input).val().toUpperCase();
        let clean = raw.replace(/[^A-Z0-9]/g, '');

        if (raw !== clean) { $(input).val(clean); }

        if (clean.length > 0) {
            if (!/^[VEJGPC]/.test(clean)) {
                $(input).css('border', '2px solid red').attr('title', 'Formato inválido (Ej: J123456789)');
            } else {
                $(input).css('border', '').attr('title', '');
            }
        }
        return clean;
    };

	/**
     * Inicializa la Tabla de Clientes (DataTables) [3, 4]
     */
    const initDataTable = function() {
        if ($('#clientsTable').length && !$.fn.DataTable.isDataTable('#clientsTable')) {
            
			// ---------------------------------------------------------
            // INICIO DE LA CORRECCIÓN QUIRÚRGICA: SEGURIDAD Y LOGS
            // ---------------------------------------------------------

            // Leemos la variable global de permisos (o suite_vars.is_admin como fallback)
            const canExportData = suite_vars.can_export || suite_vars.is_admin;
            let tableDom = canExportData ? 'Blrtip' : 'lrtip'; // Oculta botones si no hay permisos

            // Array de botones con Secuestro de Clic (Interceptor)
            let tableButtons = canExportData ? [
                { 
                    extend: 'excelHtml5', 
                    text: 'Excel', 
                    className: 'btn-modern-action small',
                    action: function (e, dt, node, config) {
                        let btnInstance = this;
                        
                        // 1. Llamada a la API para registrar el Log
                        SuiteAPI.post('suite_log_export', { tabla: 'Clientes (Excel)' }).then(res => {
                            if (res.success) {
                                // 2. Ejecutamos la acción nativa de DataTables para descargar
                                $.fn.dataTable.ext.buttons.excelHtml5.action.call(btnInstance, e, dt, node, config);
                            }
                        }).catch(err => {
                            alert('⚠️ Acción bloqueada: No se pudo registrar la auditoría de seguridad.');
                        });
                    }
                },
                { 
                    extend: 'csvHtml5', 
                    text: 'CSV', 
                    className: 'btn-modern-action small',
                    action: function (e, dt, node, config) {
                        let btnInstance = this;
                        
                        SuiteAPI.post('suite_log_export', { tabla: 'Clientes (CSV)' }).then(res => {
                            if (res.success) {
                                $.fn.dataTable.ext.buttons.csvHtml5.action.call(btnInstance, e, dt, node, config);
                            }
                        }).catch(err => {
                            alert('⚠️ Acción bloqueada: No se pudo registrar la auditoría de seguridad.');
                        });
                    },
                    exportOptions: {
                        format: {
                            body: function (data, row, column, node) {
                                // Lógica para limpiar acentos intacta
                                return data ? data.toString().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : data;
                            }
                        }
                    }
                }
            ] : [];

            cliTable = $('#clientsTable').DataTable({
                paging: true,
                pageLength: 25,
                responsive: true,
                dom: tableDom,
                buttons: tableButtons,
                language: dtLanguage
            });

            // Buscador personalizado
            $('#clients-table-search').on('keyup', function() { 
                cliTable.search(this.value).draw(); 
            });
        }
    };

    /**
     * Maneja los eventos DOM del módulo CRM [5-8]
     */
    const bindEvents = function() {
        
        // 1. Limpieza de RIFs en todos los inputs relevantes
        $(document).on('input blur', '#cli-rif, #prof-rif, #new-cli-rif', function() {
            cleanRifInput(this);
        });

        // 2. Crear Cliente Manual
        $('#btn-create-client').on('click', function() {
            const rif = $('#new-cli-rif').val();
            const nombre = $('#new-cli-nombre').val();

            if (!rif || !nombre) return alert('RIF y Nombre son requeridos');

            const data = {
                rif: rif,
                nombre: nombre,
                direccion: $('#new-cli-dir').val(),
                telefono: $('#new-cli-tel').val(),
                email: $('#new-cli-email').val(),
                ciudad: $('#new-cli-ciudad').val(),
                estado: $('#new-cli-estado').val()
            };

            // USO DE LA NUEVA API
            SuiteAPI.post('suite_add_client_manual', data).then(res => {
                if (res.success) {
                    alert('Cliente creado exitosamente');
                    location.reload();
                } else {
                    alert(res.data.message || res.data); // Compatibilidad de errores
                }
            }).catch(() => alert('Error de conexión con el servidor.'));
        });

        // 3. Importar CSV de Clientes
        $('#btn-run-import-cli').on('click', function() {
            const fileInput = $('#csv-clients-file').prop('files');
            if (!fileInput) return alert('Selecciona un archivo CSV primero');

            const btn = $(this);
            btn.prop('disabled', true).text('Procesando...');

            const fd = new FormData();
            fd.append('csv_file', fileInput);

            // USO DE LA NUEVA API (Formulario Múltiple)
            SuiteAPI.postForm('suite_import_clients_csv', fd).then(res => {
                if (res.success) {
                    alert(res.data);
                    location.reload();
                } else {
                    alert(res.data.message || res.data);
                    btn.prop('disabled', false).text('Procesar');
                }
            }).catch(() => {
                alert('Error al subir el archivo');
                btn.prop('disabled', false).text('Procesar');
            });
        });

        // 4. Actualizar Perfil del Cliente
        $('#btn-update-profile').on('click', function() {
            const btn = $(this);
            btn.text('Guardando...');

            const data = {
                is_update_only: true, // Flag vital para el backend
                id: $('#prof-id').val(),
                rif: $('#prof-rif').val(),
                nombre: $('#prof-nombre').val(),
                telefono: $('#prof-tel').val(),
                email: $('#prof-email').val(),
                direccion: $('#prof-dir').val(),
                ciudad: $('#prof-ciudad').val(),
                estado: $('#prof-estado').val(),
                contacto: $('#prof-contacto').val(),
                notas: $('#prof-notas').val()
            };

            SuiteAPI.post('suite_save_quote_crm', data).then(res => {
                btn.text('Guardar Cambios');
                if (res.success) {
                    alert('Perfil Actualizado Correctamente');
                } else {
                    alert(res.data.message || res.data);
                }
            });
        });

        // 5. Borrar Cliente
        $('#btn-delete-profile').on('click', function() {
            if (!confirm('¿Está seguro de que desea eliminar este cliente? Se mantendrá el registro financiero en sus cotizaciones previas.')) return;
            
            const id = $('#prof-id').val();

            SuiteAPI.post('suite_delete_client', { id: id }).then(res => {
                if (res.success) {
                    alert('Cliente eliminado');
                    location.reload();
                } else {
                    alert(res.data.message || res.data);
                }
            });
        });
    };

    /**
     * Carga el perfil completo de un cliente y abre el modal [5, 9, 10]
     * Expuesto públicamente para ser llamado desde los botones de la tabla
     * 
     * @param {number} id - ID del cliente
     */
    const openProfile = function(id) {
        $('#prof-history-body').html('<tr><td colspan="4" class="text-center">Cargando datos...</td></tr>');
        $('#prof-id').val(id);

        SuiteAPI.post('suite_get_client_profile', { id: id }).then(res => {
            if (res.success) {
                const c = res.data.cliente;
                const s = res.data.stats;
                const h = res.data.history;

                // Llenar formulario
                $('#prof-nombre').val(c.nombre_razon);
                $('#prof-rif').val(c.rif_ci);
                $('#prof-tel').val(c.telefono);
                $('#prof-email').val(c.email);
                $('#prof-dir').val(c.direccion);
                $('#prof-ciudad').val(c.ciudad);
                $('#prof-estado').val(c.estado);
                $('#prof-contacto').val(c.contacto_persona);
                $('#prof-notas').val(c.notas || '');

                // Llenar KPIs
                $('#kpi-total').text('$' + s.total);
                $('#kpi-count').text(s.count);
                $('#kpi-last').text(s.last);

				// Llenar Historial de Compras
				let hHtml = '';
				if (h.length > 0) {
					h.forEach(r => {
						hHtml += `
							<tr>
								<td>${r.fecha}</td>
								<td><strong>${r.codigo}</strong></td>
								<td class="text-green">$${r.total}</td>
								<td>
									<a href="${suite_vars.ajax_url}?action=suite_print_quote&id=${r.id}&nonce=${suite_vars.nonce}" target="_blank" class="btn-modern-action small">📄 Imprimir</a>
								</td>
							</tr>
						`;
					});
				} else {
					hHtml = '<tr><td colspan="4" class="text-center text-gray-500 py-4">Sin compras registradas.</td></tr>';
				}
                
                $('#prof-history-body').html(hHtml);

                // Mostrar Modal (Asumimos que openModal es global o lo manejamos aquí)
                if (typeof window.openModal === 'function') {
                    window.openModal('modal-client-profile');
                } else {
                    $('#modal-client-profile').fadeIn();
                }
            } else {
                alert(res.data.message || res.data);
            }
        });
    };

    /**
     * Búsqueda predictiva expuesta para el cotizador (SuiteQuoter)
     * 
     * @param {string} term - Término de búsqueda
     * @returns {Promise} - Devuelve la promesa pura
     */
    const searchClient = function(term) {
        return SuiteAPI.post('suite_search_client_ajax', { term: term });
    };

    // API Pública Revelada
    return {
        init: function() {
            initDataTable();
            bindEvents();
        },
        openProfile: openProfile,
        searchClient: searchClient,
        cleanRif: cleanRifInput // Expuesto por si otros módulos (Cotizador) lo necesitan
    };

})(jQuery);