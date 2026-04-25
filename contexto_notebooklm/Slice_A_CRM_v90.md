# 🧩 MÓDULO LÓGICO: Slice_A_CRM

### ARCHIVO: `assets/js/modules/crm.js`
```js
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
				language: dtLanguage,
				// --- INICIO CORRECCIÓN: Optimización y Truncado de Columna ---
				columnDefs: [
					{
						targets: 1, // Índice de la columna "Nombre / Razón Social"
						render: function(data, type, row) {
							if (type === 'display') {
								// Extraemos el texto puro en caso de que venga con etiquetas HTML (como <strong>)
								let textContent = $('<div>').html(data).text();
								return `<div style="max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${textContent}">${data}</div>`;
							}
							return data;
						}
					}
				]
				// --- FIN CORRECCIÓN ---
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
						// 1. Lógica de UI para Estados (Badges semánticos)
						let badgeBg = '#f1f5f9', badgeCl = '#64748b'; // Default: Gris
						let estadoTxt = r.estado ? r.estado.toUpperCase() : 'DESCONOCIDO';
						
						if (r.estado === 'despachado' || r.estado === 'pagado') {
							badgeBg = '#dcfce7'; badgeCl = '#166534'; // Verde
						} else if (r.estado === 'por_enviar' || r.estado === 'proceso' || r.estado === 'pendiente') {
							badgeBg = '#fef08a'; badgeCl = '#854d0e'; // Amarillo
						} else if (r.estado === 'emitida') {
							badgeBg = '#dbeafe'; badgeCl = '#1d4ed8'; // Azul
						} else if (r.estado === 'archivado') {
							badgeBg = '#f1f5f9'; badgeCl = '#475569'; // Gris oscuro
						}

						// 2. Lógica Condicional para Botones (Archivos PDF/Imágenes)
						let btnFactura = (r.factura_fiscal_url && r.factura_fiscal_url !== '') 
							? `<a href="${r.factura_fiscal_url}" target="_blank" class="btn-modern-action small" style="background:#fee2e2; color:#dc2626; text-decoration:none; padding:4px 8px; margin-right:5px; border-radius:4px;" title="Ver Factura">📄</a>` 
							: '';
					
						
						
						let btnPod = (r.pod_url && r.pod_url !== '') 
							? `<a href="${r.pod_url}" target="_blank" class="btn-modern-action small" style="background:#e0e7ff; color:#4f46e5; text-decoration:none; padding:4px 8px; margin-right:5px; border-radius:4px;" title="Ver Comprobante de Entrega">📦</a>` 
							: '';
							
						// NUEVO: Botón de Retención
						let btnRetencion = (r.retencion_url && r.retencion_url !== '') 
							? `<a href="${r.retencion_url}" target="_blank" class="btn-modern-action small" style="background:#fce7f3; color:#be123c; text-decoration:none; padding:4px 8px; margin-right:5px; border-radius:4px;" title="Descargar Planilla de Retención">🧾</a>` 
							: '';

						// Mantenemos tu botón original de Imprimir

						
						
						
						// Mantenemos tu botón original de Imprimir
						let btnPrint = `<a href="${suite_vars.ajax_url}?action=suite_print_quote&id=${r.id}&nonce=${suite_vars.nonce}" target="_blank" class="btn-modern-action small" style="background:#f8fafc; color:#475569; border: 1px solid #cbd5e1; padding:4px 8px; border-radius:4px; font-size:11px; text-decoration:none;" title="Imprimir PDF">🖨️ Imprimir</a>`;

						// 3. Renderizado final de la fila (5 COLUMNAS EXACTAS)
						hHtml += `
							<tr>
								<td style="font-size:13px; color:#64748b;">${r.fecha}</td>
								<td><strong>${r.codigo}</strong></td>
								<td><strong style="color:#059669;">$${r.total}</strong></td>
								<td>
									<span style="background:${badgeBg}; color:${badgeCl}; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:800; letter-spacing:0.5px; display:inline-block;">
										${estadoTxt}
									</span>
								</td>
								<td style="display:flex; align-items:center; gap: 4px;">
									${btnFactura}
									${btnPod}
									${btnPrint}
									${btnRetencion}
								</td>
							</tr>
						`;
					});
				} else {
					// Actualizamos el colspan a 5 para cuando no haya resultados
					hHtml = '<tr><td colspan="5" class="text-center text-gray-500 py-4" style="text-align:center; color:#94a3b8; padding:20px;">Sin compras registradas.</td></tr>';
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
```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-client.php`
```php
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
			'vendedor_id'  => get_current_user_id(), 
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
        // --- INICIO: MIDDLEWARE ZERO-TRUST (Prohibición de Hard-Delete) ---
        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( 'Acceso denegado. Solo un administrador puede eliminar registros permanentemente. Por favor, cambie el estado u oculte el registro.', 403 );
            return; // Detenemos la ejecución inmediatamente
        }
        // --- FIN: ZERO-TRUST ---

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
            $this->send_success( 'Cliente eliminado permanentemente.' );
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

        // --- BARRERA DE SEGURIDAD IDOR PARA EXPEDIENTES ---
        $user = wp_get_current_user();
        $user_id = get_current_user_id();
        
        $es_dueño = ( intval( $cliente->vendedor_id ) === $user_id || intval( $cliente->vendedor_id ) === 0 );
        $is_admin = current_user_can( 'manage_options' );
        $is_gerente = in_array( 'suite_gerente', (array)$user->roles ) || in_array( 'gerente', (array)$user->roles );
        $tiene_bandera_global = current_user_can( 'suite_view_all_customers' );

        // Si no es el dueño, ni admin, ni gerente, ni tiene la bandera -> Bloquear.
        if ( ! $es_dueño && ! $is_admin && ! $is_gerente && ! $tiene_bandera_global ) {
            $this->send_error( 'Acceso Denegado: No tiene permisos para ver el expediente de un cliente que no le pertenece.', 403 );
            return;
        }
        // --- FIN BARRERA ---

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
                    'id'                 => $h->id,
                    'fecha'              => date( 'd/m/Y', strtotime( $h->fecha ) ),
                    'codigo'             => $h->codigo,
                    'total'              => number_format( floatval( $h->total ), 2 ),
                    'estado'             => $h->estado,
                    'factura_fiscal_url' => isset($h->factura_fiscal_url) ? $h->factura_fiscal_url : '',
                    'pod_url'            => isset($h->pod_url) ? $h->pod_url : '',
                    'retencion_url'      => isset($h->retencion_url) ? $h->retencion_url : ''
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
```

### ARCHIVO: `includes/Models/class-suite-model-client.php`
```php
<?php
/**
 * Modelo de Base de Datos: Clientes (CRM)
 *
 * Maneja las consultas específicas de la tabla suite_clientes.
 * Hereda de Suite_Model_Base los métodos genéricos.
 * ACTUALIZADO: Módulo 1 (Zero-Trust) y Anti-Secuestro de Cartera.
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Client extends Suite_Model_Base {

    protected function set_table_name() {
        return 'suite_clientes';
    }

    /**
     * Override del método Base: Devuelve clientes respetando la seguridad RLS
     */
    public function get_all( $limit = 100, $offset = 0 ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);
        
        // --- INYECCIÓN RBAC: Validación de la nueva bandera ---
        $tiene_bandera_global = current_user_can( 'suite_view_all_customers' );
        
        $sql = "SELECT * FROM {$this->table_name}";
        
        // Zero-Trust: Filtro estricto si es vendedor regular
        // Si no es admin, no es gerente Y NO tiene la bandera global, se restringe la vista
        if ( ! $is_admin && ! $is_gerente && ! $tiene_bandera_global ) {
            // Se muestran los suyos y los huérfanos (vendedor_id = 0)
            $sql .= $this->wpdb->prepare(" WHERE vendedor_id = %d OR vendedor_id = 0", get_current_user_id());
        }
        
        $sql .= $this->wpdb->prepare(" ORDER BY id DESC LIMIT %d OFFSET %d", intval($limit), intval($offset));
        return $this->wpdb->get_results( $sql );
    }
	
	

    /**
     * Busca clientes por Nombre/Razón Social o por RIF/CI con Seguridad RLS.
     */
    public function search_clients( $term ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);
        
        // --- INYECCIÓN RBAC: Nueva Bandera Global ---
        $tiene_bandera_global = current_user_can( 'suite_view_all_customers' );

        $term_clean = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $term ) );
        $term_name = $term;

        // Construcción SQL Base con paréntesis para aislar el OR
        $sql = "SELECT * FROM {$this->table_name} WHERE (nombre_razon LIKE %s OR rif_ci LIKE %s)";
        
        // Zero-Trust: Si no tiene poderes de supervisor/admin, aislar la búsqueda
        if ( ! $is_admin && ! $is_gerente && ! $tiene_bandera_global ) {
            // Solo busca entre sus clientes O los que no tienen vendedor (huérfanos)
            $sql .= $this->wpdb->prepare(" AND (vendedor_id = %d OR vendedor_id = 0)", get_current_user_id());
        }
        
        $sql .= " LIMIT 10";

        // Preparar y ejecutar
        $prepared_sql = $this->wpdb->prepare(
            $sql, 
            '%' . $this->wpdb->esc_like( $term_name ) . '%', 
            '%' . $this->wpdb->esc_like( $term_clean ) . '%'
        );

        return $this->wpdb->get_results( $prepared_sql );
    }

    /**
     * Obtiene los KPIs y estadísticas de compras de un cliente específico.
     * ACTUALIZADO: Solo contabiliza las compras hechas con EL VENDEDOR ACTUAL (RLS).
     */
    public function get_client_stats( $client_id ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);

        $tabla_cotizaciones = $this->wpdb->prefix . 'suite_cotizaciones';

        $sql = $this->wpdb->prepare( 
            "SELECT 
                SUM(total_usd) as total, 
                COUNT(id) as count, 
                MIN(fecha_emision) as first, 
                MAX(fecha_emision) as last 
            FROM {$tabla_cotizaciones} 
            WHERE cliente_id = %d", 
            intval( $client_id ) 
        );

        // Zero-Trust
        if ( ! $is_admin && ! $is_gerente ) {
            $sql .= $this->wpdb->prepare(" AND vendedor_id = %d", get_current_user_id());
        }

        return $this->wpdb->get_row( $sql );
    }

    /**
     * Historial RLS: Un vendedor solo ve las compras que ESE cliente hizo con ÉL.
     */
    public function get_client_history( $client_id ) {
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_gerente = in_array('suite_gerente', (array)$user->roles) || in_array('gerente', (array)$user->roles);
        
        $tabla_cotizaciones = $this->wpdb->prefix . 'suite_cotizaciones';
        
        // Extraemos también las URLs de los comprobantes logísticos para la UI
        $sql = $this->wpdb->prepare(
            "SELECT id, codigo_cotizacion as codigo, fecha_emision as fecha, total_usd as total, estado, factura_fiscal_url, pod_url, retencion_url 
             FROM {$tabla_cotizaciones}
             WHERE cliente_id = %d", 
             intval($client_id)
        );
        
        // Zero-Trust
        if ( ! $is_admin && ! $is_gerente ) {
            $sql .= $this->wpdb->prepare(" AND vendedor_id = %d", get_current_user_id());
        }
        
        $sql .= " ORDER BY id DESC LIMIT 10";
        
        return $this->wpdb->get_results( $sql );
    }
}
```

### ARCHIVO: `views/app/tab-clientes.php`
```php
<?php
/**
 * Vista: Pestaña de Clientes (CRM) y Modales
 * 
 * Espera recibir las variables:
 * @var array $clientes Array de objetos con los datos de los clientes.
 * @var bool  $es_admin Booleano que indica si el usuario tiene privilegios de administrador.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<!-- ========================================================= -->
<!-- 1. PESTAÑA PRINCIPAL (TABLA DE CLIENTES)                  -->
<!-- ========================================================= -->
<div id="TabCli" class="suite-tab-content active"> <!-- Le añadimos 'active' temporalmente para la prueba -->
    
    <!-- Barra de Herramientas Superior -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; position:relative;">
        
        <!-- Buscador DataTables -->
        <div style="width: 320px; position:relative;">
            <span class="dashicons dashicons-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></span>
            <input type="text" id="clients-table-search" placeholder="Buscar cliente por RIF o Nombre..." style="width:100%; padding: 10px 15px 10px 45px; border-radius: 8px; border: 1px solid #cbd5e1;">
        </div>
        
        <!-- Botones de Acción (Solo Admin) -->
        <?php if ( $es_admin ) : ?>
            <div>
                <!-- Nota: openModal asumimos que vive en main.js o lo dejamos como helper global en UI -->
                <button class="btn-modern-action" onclick="jQuery('#modal-add-client').fadeIn();">➕ Nuevo Cliente</button>
                <button class="btn-modern-action" onclick="jQuery('#modal-import-clients').fadeIn();">📥 Importar CSV</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenedor de la Tabla -->
    <div class="suite-table-responsive">
        <table id="clientsTable" class="suite-modern-table display nowrap">
            <thead>
                <tr>
                    <th>RIF</th>
                    <th>Nombre / Razón Social</th>
                    <th>Contacto</th>
                    <th>Ubicación</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( isset( $clientes ) && ! empty( $clientes ) ) : ?>
					<?php foreach ( $clientes as $c ) : ?>
						<tr>
                            <td class="font-mono"><?php echo esc_html( $c->rif_ci ); ?></td>
                            <td><strong><?php echo esc_html( $c->nombre_razon ); ?></strong></td>
                            
                            <td>
                                <?php echo esc_html( $c->telefono ); ?><br>
                                <small style="color: #64748b;"><?php echo esc_html( $c->email ); ?></small>
                            </td>
                            
                            <td><?php echo esc_html( ucwords( strtolower( $c->ciudad ) ) ); ?></td>
                            
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-modern-action small" onclick="SuiteCRM.openProfile(<?php echo intval( $c->id ); ?>)">📂 Expediente</button>
                                    
                                    <?php 
                                    $wa_phone = preg_replace('/[^0-9]/', '', $c->telefono);
                                    if ( ! empty( $wa_phone ) ) : 
                                        if ( strlen( $wa_phone ) === 11 && strpos( $wa_phone, '0' ) === 0 ) {
                                            $wa_phone = '58' . substr( $wa_phone, 1 );
                                        } elseif ( strlen( $wa_phone ) === 10 ) {
                                            $wa_phone = '58' . $wa_phone;
                                        }
                                    ?>
                                        <a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr( $wa_phone ); ?>" target="_blank" class="btn-modern-action small" style="color: #059669; border-color: #a7f3d0; background: #ecfdf5; text-decoration: none;">📱 WA</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================= -->
<!-- 2. MODALES ESPECÍFICOS DEL CRM                            -->
<!-- ========================================================= -->

<!-- Modal: Crear Nuevo Cliente -->
<div id="modal-add-client" class="suite-modal" style="display: none;">
    <div class="suite-modal-content">
        <span class="close-modal" onclick="jQuery('.suite-modal').fadeOut();">×</span>
        <h3 style="margin-top:0; color:#0f172a;">➕ Nuevo Cliente</h3>
        
        <div class="form-group-row">
            <div style="flex:1">
                <label>RIF/CI *</label>
                <input type="text" id="new-cli-rif" class="widefat" placeholder="Ej: J123456789">
            </div>
            <div style="flex:2">
                <label>Nombre/Razón Social *</label>
                <input type="text" id="new-cli-nombre" class="widefat">
            </div>
        </div>
        
        <div class="form-group-row">
            <div style="flex:1"><label>Teléfono</label><input type="text" id="new-cli-tel" class="widefat"></div>
            <div style="flex:1"><label>Email</label><input type="email" id="new-cli-email" class="widefat"></div>
        </div>
        
        <label>Dirección</label>
        <input type="text" id="new-cli-dir" class="widefat">
        
        <div class="form-group-row">
            <div style="flex:1"><label>Ciudad</label><input type="text" id="new-cli-ciudad" class="widefat"></div>
            <div style="flex:1"><label>Estado</label><input type="text" id="new-cli-estado" class="widefat"></div>
        </div>
        
        <button class="btn-save-big" id="btn-create-client" style="margin-top:15px;">Guardar Cliente</button>
    </div>
</div>

<!-- Modal: Importar Clientes Masivamente -->
<div id="modal-import-clients" class="suite-modal" style="display: none;">
    <div class="suite-modal-content">
        <span class="close-modal" onclick="jQuery('.suite-modal').fadeOut();">×</span>
        <h3 style="margin-top:0; color:#0f172a;">📥 Importar Clientes</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:20px;">
            Orden exacto del CSV: <strong>Nombre, RIF, Teléfono, Email, Dirección</strong>
        </p>
        <input type="file" id="csv-clients-file" accept=".csv" class="widefat" style="padding:10px; background:#f8fafc;">
        <button class="btn-save-big" id="btn-run-import-cli" style="margin-top:10px;">Procesar Importación</button>
    </div>
</div>

<!-- Modal: Expediente / Perfil de Cliente -->
<style>
/* =========================================================
   RECUPERACIÓN: MODAL INMERSIVO DEL PERFIL DEL CLIENTE
   ========================================================= */
#modal-client-profile .suite-modal-content.large {
    max-width: 1200px;
    width: 95%;
    height: 90vh; /* Pantalla completa */
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
    background: #f8fafc;
    border-radius: 16px;
}

#modal-client-profile .modal-header-fixed {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    position: relative;
    border-radius: 16px 16px 0 0;
}

#modal-client-profile .close-modal-float {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 32px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1;
}

#modal-client-profile .close-modal-float:hover {
    color: #dc2626;
    transform: scale(1.1);
}

#modal-client-profile .modal-body-scroll {
    display: flex;
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    gap: 30px;
}

/* Columna Izquierda */
#modal-client-profile .profile-left {
    flex: 0 0 380px;
    background: #ffffff;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
}

/* Columna Derecha */
#modal-client-profile .profile-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* Tarjetas KPI */
#modal-client-profile .kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

#modal-client-profile .kpi-card {
    padding: 25px;
    border-radius: 16px;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

#modal-client-profile .kpi-card.kpi-total { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
#modal-client-profile .kpi-card.kpi-count { background: linear-gradient(135deg, #0284c7 0%, #3b82f6 100%); }
#modal-client-profile .kpi-card.kpi-last  { background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); }

#modal-client-profile .kpi-card small {
    font-size: 13px;
    text-transform: uppercase;
    font-weight: 600;
    opacity: 0.9;
    margin-bottom: 5px;
    display: block;
}

#modal-client-profile .kpi-card strong {
    font-size: 34px;
    font-weight: 800;
    letter-spacing: -1px;
    line-height: 1.2;
}

/* Historial */
#modal-client-profile .history-container {
    flex: 1;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#modal-client-profile .history-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    font-weight: 700;
    color: #0f172a;
    font-size: 15px;
}

#modal-client-profile .history-scroll {
    flex: 1;
    overflow-y: auto;
}

@media (max-width: 900px) {
    #modal-client-profile .modal-body-scroll { flex-direction: column; }
    #modal-client-profile .profile-left { flex: auto; }
    #modal-client-profile .suite-modal-content.large { height: 100%; width: 100%; border-radius: 0; max-height: 100vh; }
}
</style>

<div id="modal-client-profile" class="suite-modal" style="display: none;">
    <div class="suite-modal-content large">

        <div class="modal-header-fixed">
            <h3 id="cli-profile-name" style="margin:0; color:#0f172a; font-size: 22px;">📂 Expediente del Cliente</h3>
            <span class="close-modal-float" onclick="jQuery('#modal-client-profile').fadeOut();">&times;</span>
        </div>

        <div class="modal-body-scroll">
            <div class="profile-left">
                <h4 style="margin:0 0 15px 0; color:#475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 14px;">📋 Información de Contacto</h4>

                <input type="hidden" id="prof-id">

                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>RIF/CI</label>
                        <input type="text" id="prof-rif" class="widefat" readonly style="background-color: #f1f5f9; cursor: not-allowed;" title="El RIF no puede ser modificado">
                    </div>
                    <div style="flex: 2;">
                        <label>Razón Social</label>
                        <input type="text" id="prof-nombre" class="widefat">
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Teléfono</label>
                        <input type="text" id="prof-tel" class="widefat">
                    </div>
                    <div style="flex: 1;">
                        <label>Email</label>
                        <input type="email" id="prof-email" class="widefat">
                    </div>
                </div>

                <label>Dirección Física</label>
                <input type="text" id="prof-dir" class="widefat">

                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Ciudad</label>
                        <input type="text" id="prof-ciudad" class="widefat">
                    </div>
                    <div style="flex: 1;">
                        <label>Estado</label>
                        <input type="text" id="prof-estado" class="widefat">
                    </div>
                </div>

                <label>Persona de Contacto</label>
                <input type="text" id="prof-contacto" class="widefat">

                <label>📝 Notas Internas</label>
                <textarea id="prof-notas" class="widefat" rows="3" placeholder="Añadir observaciones sobre este cliente..."></textarea>

                <div style="display:flex; gap:10px; margin-top: auto; padding-top: 15px;">
                    <button id="btn-update-profile" class="btn-save-big" style="flex:2;">💾 Guardar Cambios</button>
                    <?php if ( $es_admin ) : ?>
                    <button id="btn-delete-profile" class="btn-modern-action" style="flex:1; color:#dc2626; border-color:#fca5a5; justify-content: center; background:#fef2f2;">🗑️ Eliminar</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-right">
                <div class="kpi-row" id="cli-profile-kpi-compras">
                    <div class="kpi-card kpi-total">
                        <small>💰 Total Gastado</small>
                        <strong id="kpi-total">$0.00</strong>
                    </div>
                    <div class="kpi-card kpi-count">
                        <small>🛒 Total Operaciones</small>
                        <strong id="kpi-count">0</strong>
                    </div>
                    <div class="kpi-card kpi-last">
                        <small>📅 Última Compra</small>
                        <strong id="kpi-last">-</strong>
                    </div>
                </div>

                <div class="history-container">
                    <div class="history-header">
                        📜 Historial de Cotizaciones y Pedidos
                    </div>
                    <div class="history-scroll">
                        <table class="suite-modern-table" id="cli-history-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
								
								
                                <tr>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Fecha</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Código</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Total</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc; text-align:center;">Estado</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Acción</th>
                                </tr>
								
								
                            </thead>
                            <tbody id="prof-history-body">
                                <tr><td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">Cargando datos del cliente...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

### ARCHIVO: `views/components/modal-cliente.php`
```php
<?php
/**
 * Archivo: views/components/modal-cliente.php
 * Proposito: Modal inmersivo a pantalla completa (Legacy Layout)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>
/* =========================================================
   RECUPERACIÓN: MODAL INMERSIVO DEL PERFIL DEL CLIENTE 
   ========================================================= */
#modal-client-profile .suite-modal-content.large {
    max-width: 1200px;
    width: 95%;
    height: 90vh; /* Pantalla completa */
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
    background: #f8fafc;
    border-radius: 16px;
}

#modal-client-profile .modal-header-fixed {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    position: relative;
    border-radius: 16px 16px 0 0;
}

/* Botón flotante y visible de cerrar */
#modal-client-profile .close-modal-float {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 32px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1;
}

#modal-client-profile .close-modal-float:hover {
    color: #dc2626;
    transform: scale(1.1);
}

/* Layout a 2 columnas con Flexbox */
#modal-client-profile .modal-body-scroll {
    display: flex;
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    gap: 30px;
}

/* Columna Izquierda: Datos y Acciones */
#modal-client-profile .profile-left {
    flex: 0 0 380px;
    background: #ffffff;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
}

/* Columna Derecha: KPIs e Historial */
#modal-client-profile .profile-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* Tarjetas KPI (Estilo Legacy Recuperado) */
#modal-client-profile .kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

#modal-client-profile .kpi-card {
    padding: 25px;
    border-radius: 16px;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

/* Fondos Degradados */
#modal-client-profile .kpi-card.kpi-total { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
#modal-client-profile .kpi-card.kpi-count { background: linear-gradient(135deg, #0284c7 0%, #3b82f6 100%); }
#modal-client-profile .kpi-card.kpi-last  { background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); }

#modal-client-profile .kpi-card small { 
    font-size: 13px; 
    text-transform: uppercase; 
    font-weight: 600; 
    opacity: 0.9; 
    margin-bottom: 5px; 
    display: block;
}

#modal-client-profile .kpi-card strong { 
    font-size: 34px; 
    font-weight: 800; 
    letter-spacing: -1px; 
    line-height: 1.2;
}

/* Contenedor del Historial */
#modal-client-profile .history-container {
    flex: 1;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#modal-client-profile .history-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    font-weight: 700;
    color: #0f172a;
    font-size: 15px;
}

#modal-client-profile .history-scroll {
    flex: 1;
    overflow-y: auto;
}

/* Mobile Responsive */
@media (max-width: 900px) {
    #modal-client-profile .modal-body-scroll { flex-direction: column; }
    #modal-client-profile .profile-left { flex: auto; }
    #modal-client-profile .suite-modal-content.large { height: 100%; width: 100%; border-radius: 0; max-height: 100vh; }
}
</style>

<!-- El ID corresponde estrictamente con el archivo crm.js de la V12 -->
<div id="modal-client-profile" class="suite-modal" style="display: none;">
    <div class="suite-modal-content large">
        
        <!-- CABECERA -->
        <div class="modal-header-fixed">
            <h3 id="cli-profile-name" style="margin:0; color:#0f172a; font-size: 22px;">📂 Expediente del Cliente</h3>
            <span class="close-modal-float" onclick="jQuery('#modal-client-profile').fadeOut();">&times;</span>
        </div>
        
        <div class="modal-body-scroll">
            <!-- COLUMNA IZQUIERDA: DATOS DE CONTACTO (Formulario Mapeado a crm.js) -->
            <div class="profile-left">
                <h4 style="margin:0 0 15px 0; color:#475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 14px;">📋 Información de Contacto</h4>
                
                <input type="hidden" id="prof-id">
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>RIF/CI</label>
                        <input type="text" id="prof-rif" class="widefat" readonly style="background-color: #f1f5f9; cursor: not-allowed;" title="El RIF no puede ser modificado">
                    </div>
                    <div style="flex: 2;">
                        <label>Razón Social</label>
                        <input type="text" id="prof-nombre" class="widefat">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Teléfono</label>
                        <input type="text" id="prof-tel" class="widefat">
                    </div>
                    <div style="flex: 1;">
                        <label>Email</label>
                        <input type="email" id="prof-email" class="widefat">
                    </div>
                </div>
                
                <label>Dirección Física</label>
                <input type="text" id="prof-dir" class="widefat">
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Ciudad</label>
                        <input type="text" id="prof-ciudad" class="widefat">
                    </div>
                    <div style="flex: 1;">
                        <label>Estado</label>
                        <input type="text" id="prof-estado" class="widefat">
                    </div>
                </div>
                
                <label>Persona de Contacto</label>
                <input type="text" id="prof-contacto" class="widefat">
                
                <label>📝 Notas Internas</label>
                <textarea id="prof-notas" class="widefat" rows="3" placeholder="Añadir observaciones sobre este cliente..."></textarea>
                
                <div style="display:flex; gap:10px; margin-top: auto; padding-top: 15px;">
                    <button id="btn-update-profile" class="btn-save-big" style="flex:2;">💾 Guardar Cambios</button>
                    <button id="btn-delete-profile" class="btn-modern-action" style="flex:1; color:#dc2626; border-color:#fca5a5; justify-content: center;">🗑️ Eliminar</button>
                </div>
            </div>

            <!-- COLUMNA DERECHA: KPIs Y TABLA -->
            <div class="profile-right">
                
                <!-- Tarjetas KPI Gigantes -->
                <div class="kpi-row" id="cli-profile-kpi-compras">
                    <div class="kpi-card kpi-total">
                        <small>💰 Total Gastado</small>
                        <strong id="kpi-total">$0.00</strong>
                    </div>
                    <div class="kpi-card kpi-count">
                        <small>🛒 Total Operaciones</small>
                        <strong id="kpi-count">0</strong>
                    </div>
                    <div class="kpi-card kpi-last">
                        <small>📅 Última Compra</small>
                        <strong id="kpi-last">-</strong>
                    </div>
                </div>
                
                <!-- Pestaña de Historial -->
                <div class="history-container">
                    <div class="history-header">
                        📜 Historial de Cotizaciones y Pedidos
                    </div>
                    <div class="history-scroll">
                        <table class="suite-modern-table" id="cli-history-table">
                            <thead>
                                <tr>
                                    <th style="position: sticky; top: 0; z-index: 2;">Fecha</th>
                                    <th style="position: sticky; top: 0; z-index: 2;">Código</th>
                                    <th style="position: sticky; top: 0; z-index: 2;">Total</th>
                                    <th style="position: sticky; top: 0; z-index: 2;">Acción</th>
                                </tr>
                            </thead>
                            <!-- El tbody coincide estrictamente con la inyección de crm.js -->
                            <tbody id="prof-history-body">
                                <tr><td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">Cargando datos del cliente...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

