/**
 * SuiteCommissions - Módulo del Dashboard Financiero y Gamificación
 * 
 * Se encarga de solicitar las estadísticas mensuales del vendedor y 
 * actualizar los líderes de los premios en la vista.
 */
const SuiteCommissions = (function($) {
    'use strict';
	let auditTable = null;
    let fameLoaded = false;

    // ==========================================
    // MÉTODOS PRIVADOS
    // ==========================================

    const renderDashboard = function(data) {
        // 1. Rendimiento Personal
        $('#dash-mes-actual').text(data.mes_evaluado);
        $('#dash-comision-actual').text('$' + data.comision_actual);

        // 2. Líder "Pez Gordo" (Dinero)
        const pezGordo = data.gamificacion.pez_gordo;
        if (pezGordo) {
            $('#pez-gordo-name').text('👑 ' + pezGordo.display_name);
            $('#pez-gordo-amount').text('$' + pezGordo.total_vendido);
        } else {
            $('#pez-gordo-name').text('Aún sin ventas');
            $('#pez-gordo-amount').text('$0.00');
        }

        // 3. Líder "Deja pa' los demás" (Cantidad)
        const dejaPa = data.gamificacion.deja_pa_los_demas;
        if (dejaPa) {
            $('#deja-pa-name').text('🚀 ' + dejaPa.display_name);
            $('#deja-pa-count').text(dejaPa.cantidad_ventas + ' ventas cerradas');
        } else {
            $('#deja-pa-name').text('Aún sin ventas');
            $('#deja-pa-count').text('0 ventas');
        }
    };
	
	// ==========================================
    // MÉTODOS PRIVADOS (INICIO FASE 3.2: SPA & DATATABLES)
    // ==========================================

    const bindPillEvents = function() {
        $('.pill-btn').on('click', function(e) {
            e.preventDefault();
            
            // UI: Cambiar color del botón activo
            $('.pill-btn').removeClass('active').css({
                'background': 'transparent',
                'border': '1px solid #cbd5e1'
            });
            $(this).addClass('active').css({
                'background': '#fff',
                'border': '1px solid #cbd5e1'
            });

            const target = $(this).data('target');
            
            // Lógica SPA: Ocultar todo y mostrar el contenedor solicitado
            $('#comisiones-dashboard-view, #comisiones-audit-view, #comisiones-fame-view').hide();
            $('#' + target).fadeIn();

            // Lazy load de DataTables y Salón de la Fama (Solo carga si no se había cargado antes)
            if (target === 'comisiones-audit-view' && !auditTable) {
                loadAuditTable();
            } else if (target === 'comisiones-fame-view' && !fameLoaded) {
                loadHallOfFame();
            }
        });
    };

    const loadAuditTable = function() {
        auditTable = $('#auditTable').DataTable({
            responsive: true,
            language: { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            ajax: {
                url: suite_vars.ajax_url,
                type: 'POST',
                // OJO: Esta acción debe existir en tu backend PHP
                data: { action: 'suite_get_commission_audit', nonce: suite_vars.nonce },
                dataSrc: 'data'
            },
			
			
			
			columns: [
                // 0: Checkboxes de selección múltiple
                { 
                    data: 'id', 
                    visible: suite_vars.is_admin,
                    orderable: false,
                    render: (data, type, row) => {
                        if (row.estado_pago === 'pendiente') {
                            return `<input type="checkbox" class="com-chk" value="${data}" data-monto="${row.comision_ganada_usd}" style="width:16px; height:16px; cursor:pointer;">`;
                        }
                        return '🔒';
                    }
                },
                // 1: ID Orden
                { data: 'quote_id', render: data => data > 0 ? `<strong>#${data}</strong>` : '<span style="color:#64748b;">Premio/Bono/Abono</span>' },
                
                // 2: INYECCIÓN FASE 5 - Recibo Loyverse
                { data: 'recibo_loyverse', render: data => data ? `<span style="font-family:monospace; font-weight:bold; color:#0f172a;">🧾 #${data}</span>` : '<span style="color:#94a3b8;">N/A</span>' },
                
                // 3: Vendedor
                { data: 'vendedor_nombre', render: (data, type, row) => {
                    let badge = (row.notas && row.notas.includes('B2B')) 
                        ? ' <span style="background:#0ea5e9; color:white; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:5px;">B2B</span>' 
                        : '';
                    return `<strong>${data}</strong>${badge}`;
                }},
                
                // 4: Monto Base
                { data: 'monto_base_usd', render: data => `$${parseFloat(data).toFixed(2)}` },
                
                // 5: Comisión
                { data: 'comision_ganada_usd', render: data => {
                    let val = parseFloat(data);
                    let color = val < 0 ? '#dc2626' : '#059669';
                    return `<strong style="color:${color};">${val < 0 ? '' : '+'}$${val.toFixed(2)}</strong>`;
                }},
                
                // 6: Estado de Pago
                { data: 'estado_pago', render: data => {
                    let bg = data === 'pagado' ? '#d1fae5' : (data === 'anulado' ? '#fee2e2' : '#fef3c7');
                    let cl = data === 'pagado' ? '#065f46' : (data === 'anulado' ? '#991b1b' : '#92400e');
                    return `<span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background:${bg}; color:${cl};">${data.toUpperCase()}</span>`;
                }},

                // 7: INYECCIÓN FASE 5 - Auditoría POS
                { data: 'estado_auditoria', render: (data, type, row) => {
                    if (!row.recibo_loyverse) return '<span style="color:#cbd5e1;">N/A</span>';

                    let audit_bg = '#f1f5f9', audit_cl = '#64748b', audit_icon = '⏳', audit_text = 'Pendiente API';
                    if (data === 'verificado') {
                        audit_bg = '#dcfce7'; audit_cl = '#166534'; audit_icon = '✅'; audit_text = 'Verificado';
                    } else if (data === 'incongruente') {
                        audit_bg = '#fee2e2'; audit_cl = '#991b1b'; audit_icon = '🚨'; audit_text = 'Incongruencia';
                    }

                    let html = `<span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background:${audit_bg}; color:${audit_cl}; display:inline-block; margin-bottom:5px;">${audit_icon} ${audit_text}</span>`;

                    // CORRECCIÓN: Botones de acción para Admin habilitados en estado 'incongruente' Y 'pendiente'
                    if (suite_vars.is_admin && (data === 'incongruente' || data === 'pendiente') && row.estado_pago === 'pendiente') {
                        html += `
                        <div style="display: flex; gap: 4px;">
                            <button class="btn-modern-action small trigger-audit-force" data-id="${row.id}" title="Aprobar Manualmente" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:2px 6px;">✔️ Aprobar</button>
                            <button class="btn-modern-action small trigger-audit-reject" data-id="${row.id}" title="Anular (Fraude/Error)" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:2px 6px;">🚫 Anular</button>
                        </div>`;
                    }
                    return html;
                }},

                // 8: Fecha Operación
                { data: 'created_at', render: data => {
                    let d = new Date(data);
                    return `${d.getDate().toString().padStart(2, '0')}/${(d.getMonth() + 1).toString().padStart(2, '0')}/${d.getFullYear()}`;
                }}
            ],
            
            // CORRECCIÓN: Como insertamos 2 columnas nuevas antes de la fecha, 
            // la fecha pasó del índice 6 al índice 8.
            order: [[8, 'desc']]
			
			
			
        });
    };

    const loadHallOfFame = function() {
        $('#fame-cards-container').html('<p style="color:#64748b;">⏳ Desclasificando leyendas...</p>');
        SuiteAPI.post('suite_get_hall_of_fame').then(res => {
            if (res.success && res.data.length > 0) {
                let html = '';
                res.data.forEach(mesData => {
                    // Título del Periodo
                    html += `<div style="width:100%; border-bottom:2px solid #e2e8f0; margin-top:15px; padding-bottom:5px;"><h4 style="color:#0f172a;">📅 Periodo: ${mesData.mes.replace('_', '-')}</h4></div>`;
                    
                    // Contenedor de las tarjetas del periodo
                    html += `<div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:15px; width:100%;">`;
                    
                    Object.values(mesData.premios).forEach(premio => {
                        html += `
                        <div class="kpi-card" style="flex:1; min-width:250px; background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                            <small style="display:block; text-transform:uppercase; color:#64748b; font-weight:bold; margin-bottom:5px;">${premio.premio_nombre}</small>
                            <h3 style="margin:0; color:#0f172a; font-size:20px;">👤 ${premio.vendedor_name}</h3>
                            <strong style="display:block; margin-top:10px; color:#059669; font-size:18px;">+$${parseFloat(premio.monto_premio).toFixed(2)}</strong>
                        </div>`;
                    });
                    html += `</div>`; // Cierre del contenedor de tarjetas
                });
                $('#fame-cards-container').html(html);
                fameLoaded = true; // Marcar como cargado para no volver a pedirlo al backend
            } else {
                $('#fame-cards-container').html('<p style="color:#64748b;">Aún no hay registros en el Salón de la Fama.</p>');
            }
        }).catch(() => {
            $('#fame-cards-container').html('<p style="color:#dc2626;">Error al cargar el Salón de la Fama.</p>');
        });
    };
    // ==========================================
    // FIN FASE 3.2: SPA & DATATABLES
    // ==========================================

	
	// ==========================================
    // EVENT LISTENERS
    // ==========================================
    const bindEvents = function() {
        
		// Acción del Auditor Manual (Loyverse)
        $('#btn-force-audit-sync').on('click', function(e) {
            e.preventDefault(); // <--- BLOQUEO DE RECARGA NATIVA
            
            const btn = $(this);
            Swal.fire({
                title: '¿Iniciar Auditoría Manual?',
                text: 'El sistema consultará la API de Loyverse para todos los registros pendientes. Esto puede tardar unos segundos.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Sí, iniciar barrido'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.prop('disabled', true).text('⏳ Sincronizando...');
                    SuiteAPI.post('suite_run_manual_audit').then(res => {
                        Swal.fire('Auditoría Completa', res.data.message, 'success');
                        if (auditTable) auditTable.ajax.reload(null, false);
                    }).finally(() => {
                        btn.prop('disabled', false).text('🔄 Sincronizar con Loyverse Ahora');
                    });
                }
            });
        });
		
		
        // Acción de Cierre de Mes con Adjudicación de Premios (Fase 2.2)
        $('#btn-cierre-mes').on('click', async function(e) {
            e.preventDefault();

            // 1. Población dinámica del select de vendedores (Usando variable localizada de WP)
            let optionsHtml = '<option value="" disabled selected>Seleccione al vendedor...</option>';
            if (typeof suite_vars !== 'undefined' && suite_vars.sellers) {
                suite_vars.sellers.forEach(seller => {
                    optionsHtml += `<option value="${seller.id}">${seller.name}</option>`;
                });
            } else {
                optionsHtml += '<option value="">(Debe inyectar suite_vars.sellers desde WP)</option>';
            }

            // 2. Interfaz Híbrida mediante SweetAlert2
            const { value: dalePlayWinnerId, isConfirmed } = await Swal.fire({
                title: '⚙️ Cierre Contable de Mes',
                html: `
                    <div style="text-align:left; font-size:14px;">
                        <p style="color:#b91c1c; font-weight:bold; margin-bottom:15px;">
                            ⚠️ ATENCIÓN: Esta acción es IRREVERSIBLE. El Ledger pasará a estado "pagado".
                        </p>
                        <p style="margin-bottom:8px;"><strong>Asignación Manual de Premio:</strong></p>
                        <label style="color:#475569; font-size:13px;">▶️ Dale Play (Esfuerzo excepcional)</label>
                        <select id="dale-play-select" class="swal2-select" style="display:flex; width:100%; margin: 5px 0 15px 0; font-size:15px; padding:8px;">
                            ${optionsHtml}
                        </select>
                        <p style="font-size:12px; color:#64748b; font-style:italic;">
                            * Los premios "🐟 Pez Gordo" y "🏃 Deja pa' los demás" se calcularán e inyectarán automáticamente en el Ledger.
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '🔒 Congelar Mes y Repartir Premios',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const selectEl = document.getElementById('dale-play-select');
                    if (!selectEl.value) {
                        Swal.showValidationMessage('Debe seleccionar un ganador para el premio Dale Play.');
                    }
                    return selectEl.value;
                }
            });

            if (!isConfirmed || !dalePlayWinnerId) return;

            const btn = $(this);
            btn.prop('disabled', true).text('⏳ Procesando Cierre...');

            const fechaCorte = new Date().toISOString().slice(0, 19).replace('T', ' ');

            // 3. Disparar el Endpoint AJAX
            SuiteAPI.post('suite_freeze_commissions', {
                fecha_corte: fechaCorte,
                dale_play_winner_id: dalePlayWinnerId
            }).then(res => {
                if (res.success) {
                    Swal.fire('¡Éxito!', res.data.message || res.data, 'success').then(() => {
                        location.reload(); 
                    });
                } else {
                    Swal.fire('Error', res.data.message || res.data, 'error');
                    btn.prop('disabled', false).text('🔒 Ejecutar Cierre de Mes');
                }
			}).catch(err => {
                Swal.fire('Error Crítico', 'Ocurrió un error de red al intentar congelar el Ledger.', 'error');
                btn.prop('disabled', false).text('🔒 Ejecutar Cierre de Mes');
            });
        });
		
		
		// =========================================================
        // INICIO FASE 5: ACCIONES DEL AUDITOR DE LOYVERSE
        // =========================================================
        $('#auditTable').on('click', '.trigger-audit-force, .trigger-audit-reject', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const isForce = $(this).hasClass('trigger-audit-force');
            
            const actionType = isForce ? 'force_approve' : 'reject_fraud';
            const titleText = isForce ? '¿Aprobación Forzada?' : '¿Anular por Fraude?';
            const descText = isForce 
                ? '¿Confirmas que esta comisión es válida a pesar de la alerta de Loyverse? El estado pasará a "Verificado".'
                : '¿Confirmas que este registro es inválido? La comisión será ANULADA permanentemente.';
            const confirmColor = isForce ? '#059669' : '#dc2626';
            const confirmText = isForce ? 'Sí, Aprobar' : 'Sí, Anular';

            Swal.fire({
                title: titleText,
                text: descText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#64748b',
                confirmButtonText: confirmText,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    SuiteAPI.post('suite_process_audit_action', { 
                        ledger_id: id, 
                        audit_action: actionType 
                    }).then(res => {
                        if (res.success) {
                            Swal.fire('¡Procesado!', res.data.message || 'El estado de auditoría fue actualizado.', 'success');
                            if (auditTable) auditTable.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.data.message || 'No se pudo actualizar.', 'error');
                        }
                    }).catch(() => {
                        Swal.fire('Error', 'Fallo de conexión con el servidor.', 'error');
                    });
                }
            });
        });
        // --- FIN ACCIONES DEL AUDITOR ---

		// =========================================================
        // INICIO REPARACIÓN FASE 6.1: LISTENERS DE LIQUIDACIÓN Y ABONOS
        // =========================================================

        // A. Seleccionar todos los checkboxes (Checkbox Maestro)
        $('#auditTable').on('change', '#chk-all-com', function() {
            const isChecked = $(this).is(':checked');
            $('.com-chk').prop('checked', isChecked);
        });

        // B. Botón "Liquidar Seleccionados"
        $('#btn-pay-selected').on('click', function(e) {
            e.preventDefault();
            const selectedIds = [];
            $('.com-chk:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                Swal.fire('Atención', 'Seleccione al menos una comisión pendiente para liquidar.', 'warning');
                return;
            }

            Swal.fire({
                title: '¿Procesar Liquidación?',
                text: `¿Está seguro de liquidar y marcar como PAGADOS los ${selectedIds.length} registros seleccionados?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                confirmButtonText: 'Sí, registrar pago',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const btn = $(this);
                    const originalText = btn.html();
                    btn.prop('disabled', true).text('⏳ Liquidando...');

                    SuiteAPI.post('suite_pay_selected_commissions', { ledger_ids: selectedIds })
                        .then(res => {
                            if (res.success) {
                                Swal.fire('¡Éxito!', res.data.message || res.data, 'success');
                                if (auditTable) auditTable.ajax.reload(null, false);
                                $('#chk-all-com').prop('checked', false); // Limpiar checkbox maestro
                            } else {
                                Swal.fire('Error', res.data.message || res.data, 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Fallo de conexión.', 'error'))
                        .finally(() => btn.prop('disabled', false).html(originalText));
                }
            });
        });

        // C. Botón "Registrar Abono / Anticipo"
        $('#btn-register-abono').on('click', async function(e) {
            e.preventDefault();
            
            let optionsHtml = '<option value="" disabled selected>Seleccione al vendedor o aliado...</option>';
            if (typeof suite_vars !== 'undefined' && suite_vars.sellers) {
                suite_vars.sellers.forEach(seller => {
                    optionsHtml += `<option value="${seller.id}">${seller.name}</option>`;
                });
            }

            const { value: formValues } = await Swal.fire({
                title: '💸 Registrar Abono',
                html: `
                    <select id="swal-abono-seller" class="swal2-select" style="display:flex; width:100%; margin: 10px 0;">${optionsHtml}</select>
                    <input id="swal-abono-monto" class="swal2-input" type="number" step="0.01" min="1" placeholder="Monto del abono (USD)">
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Registrar Abono',
                confirmButtonColor: '#0284c7',
                preConfirm: () => {
                    const seller = document.getElementById('swal-abono-seller').value;
                    const monto = document.getElementById('swal-abono-monto').value;
                    if (!seller || !monto || parseFloat(monto) <= 0) {
                        Swal.showValidationMessage('Debe seleccionar un usuario y un monto válido mayor a 0.');
                    }
                    return { vendedor_id: seller, monto: monto };
                }
            });

            if (formValues) {
                SuiteAPI.post('suite_register_abono', formValues)
                    .then(res => {
                        if (res.success) {
                            Swal.fire('¡Éxito!', res.data.message || res.data, 'success');
                            if (auditTable) auditTable.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.data.message || res.data, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Fallo de conexión.', 'error'));
            }
        });
        // --- FIN REPARACIÓN ---
        
    };
	
	
    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        /**
         * Llama a la API para obtener la data fresca y pinta la interfaz
         */
        loadDashboard: function() {
            // Mostrar estado de carga visual sutil
            $('#dash-comision-actual').css('opacity', '0.5');

            SuiteAPI.post('suite_get_dashboard_stats').then(res => {
                if (res.success) {
                    renderDashboard(res.data);
                } else {
                    console.error("Error cargando comisiones:", res.data);
                }
            }).catch(err => {
                console.error("Error de red al cargar dashboard de comisiones.");
            }).finally(() => {
                $('#dash-comision-actual').css('opacity', '1');
            });
        },

        init: function() {
			bindEvents();
			bindPillEvents();
			if (typeof suite_vars !== 'undefined' && suite_vars.is_b2b) {
                loadAuditTable();
            }            
			// Se puede cargar automáticamente, o esperar a que el usuario haga clic en la pestaña
            // Lo dejamos listo para ser invocado por el controlador de pestañas.
        }
    };

})(jQuery);