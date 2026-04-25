# 🧩 MÓDULO LÓGICO: Slice_E_Comisiones_Ledger

### ARCHIVO: `assets/js/modules/commissions.js`
```js
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
            
			
			
			
			
			
            // UI: Cambiar color del botón activo y contraste de texto
            $('.pill-btn').removeClass('active').css({
                'background': 'transparent',
                'color': '#64748b', // Gris estándar
                'border': '1px solid #cbd5e1'
            });
            
            $(this).addClass('active').css({
                'background': '#fff',
                'color': '#0f172a', // Negro para lectura clara
                'border': '1px solid #cbd5e1'
            });
			
			
			

            const target = $(this).data('target');
            
			
			
			
            // Lógica SPA: Ocultar todo y mostrar el contenedor solicitado
            $('#comisiones-dashboard-view, #comisiones-audit-view, #comisiones-fame-view, #comisiones-balance-view').hide(); // <-- Agregada la nueva vista aquí
            $('#' + target).fadeIn();

            // Lazy load de DataTables, Salón de la Fama y Bóveda Contable
            if (target === 'comisiones-audit-view' && !auditTable) {
                loadAuditTable();
            } else if (target === 'comisiones-fame-view' && !fameLoaded) {
                loadHallOfFame();
            } else if (target === 'comisiones-balance-view') {
                // Siempre cargamos data fresca al abrir la bóveda contable
                let currentMonth = new Date().getMonth() + 1;
                let currentYear = new Date().getFullYear();
                loadFinancialBalance(currentMonth, currentYear);
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
                { data: 'created_at', render: function(data, type, row) {
                    
                    // 🧠 MAGIA ORTOGONAL: Si DataTables va a ordenar, le pasamos el número puro de MySQL
                    if (type === 'sort' || type === 'type') {
                        return row.timestamp_orden; 
                    }
                    
                    // Si va a mostrar visualmente, formatea el texto de forma segura sin usar 'new Date()'
                    if (!data) return '';
                    let dateParts = data.split(' ')[0].split('-'); // Extrae [YYYY, MM, DD]
                    if (dateParts.length === 3) {
                        return `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
                    }
                    return data;
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
    
	const loadFinancialBalance = function(mes, anio) {
        $('#balance-accordion-container').html('<div style="text-align:center; padding:40px;"><span style="font-size:24px;">⏳</span><br><p style="color:#64748b; margin-top:10px;">Procesando bóveda contable...</p></div>');

        SuiteAPI.post('suite_get_financial_balance', { mes: mes, anio: anio }).then(res => {
            if (res.success) {
                const data = res.data;

                // Animación suave de números
                $('#kpi-total-nomina').text('$' + parseFloat(data.kpis.total_nomina).toFixed(2));
                $('#kpi-total-recuperado').text('$' + parseFloat(data.kpis.total_recuperado).toFixed(2));
                $('#kpi-participantes').text(data.kpis.participantes);

                if(data.vendedores.length === 0) {
                    $('#balance-accordion-container').html('<div style="background:#f8fafc; padding:20px; border-radius:8px; text-align:center; color:#64748b; border:1px dashed #cbd5e1;">No hay registros contables pendientes en este período.</div>');
                    return;
                }

                let html = '';
                data.vendedores.forEach(v => {
                    // Lógica UX de Colores y Advertencias
                    let isNegative = v.neto < 0;
                    let colorClass = isNegative ? '#dc2626' : '#059669'; // Rojo si debe, Verde si cobra
                    let bgHeader   = isNegative ? '#fef2f2' : '#ffffff';
                    let netText = isNegative
                        ? `-$${Math.abs(v.neto).toFixed(2)} <span style="font-size:10px; font-weight:bold; background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:6px; margin-left:8px; vertical-align:middle;">A FAVOR DE EMPRESA</span>`
                        : `+$${parseFloat(v.neto).toFixed(2)}`;

                    let warningIcon = v.advertencia_auditoria ? `<span title="⚠️ Alerta de Auditoría POS: Hay incongruencias" style="cursor:help; margin-left:8px; font-size:16px;">⚠️</span>` : '';

                    // Diseño de Acordeón
                    html += `
                    <div style="border: 1px solid ${isNegative ? '#fecaca' : '#e2e8f0'}; border-radius: 10px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s ease;">
                        



                        <div class="acc-header" style="background:${bgHeader}; padding:15px 20px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition: background 0.2s;">




                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-weight:bold; color:#475569;">
                                    ${v.vendedor_nombre.charAt(0).toUpperCase()}
                                </div>
                                <strong style="color:#1e293b; font-size:15px;">${v.vendedor_nombre} ${warningIcon}</strong>
                            </div>
                            <strong style="color:${colorClass}; font-size:18px;">${netText}</strong>
                        </div>

                        <div class="acc-body" style="display:none; padding:0 20px 20px 20px; background:${bgHeader};">
                            <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 5px;">
                                <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                                    <thead>
                                        <tr style="color:#64748b;">
                                            <th style="padding:6px 0; border-bottom:1px solid #e2e8f0;">Fecha</th>
                                            <th style="padding:6px 0; border-bottom:1px solid #e2e8f0;">Concepto</th>
                                            <th style="padding:6px 0; border-bottom:1px solid #e2e8f0;">Auditoría</th>
                                            <th style="padding:6px 0; border-bottom:1px solid #e2e8f0; text-align:right;">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${v.detalles.map(d => {
                                            let isEgreso = d.monto < 0;
                                            let rowColor = isEgreso ? '#dc2626' : '#059669';
                                            let signo = isEgreso ? '-' : '+';
                                            let auditBg = d.estado_auditoria === 'verificado' ? '#dcfce7' : (d.estado_auditoria === 'incongruente' ? '#fee2e2' : '#f1f5f9');
                                            let auditCl = d.estado_auditoria === 'verificado' ? '#166534' : (d.estado_auditoria === 'incongruente' ? '#991b1b' : '#64748b');

                                            return `
                                            <tr>
                                                <td style="padding:8px 0; color:#475569; border-bottom:1px solid #f8fafc;">${d.fecha}</td>
                                                <td style="padding:8px 0; color:#334155; font-weight:500; border-bottom:1px solid #f8fafc;">${d.concepto}</td>
                                                <td style="padding:8px 0; border-bottom:1px solid #f8fafc;">
                                                    <span style="background:${auditBg}; color:${auditCl}; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:bold; text-transform:uppercase;">${d.estado_auditoria}</span>
                                                </td>
                                                <td style="padding:8px 0; text-align:right; font-weight:bold; color:${rowColor}; border-bottom:1px solid #f8fafc;">
                                                    ${signo}$${Math.abs(d.monto).toFixed(2)}
                                                </td>
                                            </tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>`;
                });

                $('#balance-accordion-container').html(html);
            }
        }).catch(err => {
            $('#balance-accordion-container').html('<p style="text-align:center; color:#dc2626; padding:20px;">❌ Error de conexión al cargar la bóveda contable.</p>');
        });
    };
	
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

			
			
			
            // 1. Población dinámica del select de vendedores (SOLO COMISIONISTAS)
            let optionsHtml = '<option value="" disabled selected>Seleccione al vendedor...</option>';
            // Cambiamos suite_vars.sellers por suite_vars.commission_sellers
            if (typeof suite_vars !== 'undefined' && suite_vars.commission_sellers && suite_vars.commission_sellers.length > 0) {
                suite_vars.commission_sellers.forEach(seller => {
                    optionsHtml += `<option value="${seller.id}">${seller.name}</option>`;
                });
            } else {
                optionsHtml += '<option value="" disabled>No hay comisionistas habilitados en el sistema</option>';
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
            // Cambiamos suite_vars.sellers por suite_vars.commission_sellers
            if (typeof suite_vars !== 'undefined' && suite_vars.commission_sellers) {
                suite_vars.commission_sellers.forEach(seller => {
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
        
		// Motor de Acordeón para el Balance de Pagos
        $(document).on('click', '.acc-header', function() {
            const body = $(this).next('.acc-body');
            
            // Animación de rotación o cambio de fondo si lo deseas
            $(this).css('background', body.is(':visible') ? '#ffffff' : '#f1f5f9');
            
            body.slideToggle(250);
        });
		
		
		
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
```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-commissions.php`
```php
<?php
/**
 * Controlador AJAX: Dashboard Financiero y Gamificación (Módulo 4)
 *
 * Sirve los datos estadísticos de comisiones y premios para la vista del vendedor.
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Ajax_Dashboard_Stats extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_dashboard_stats';
    
    // Todos los empleados pueden ver las estadísticas
    protected $required_capability = 'read'; 

    protected function process() {
        $vendedor_id = get_current_user_id();
        
        // Determinar mes y año actual
        $mes  = (int) date('m');
        $anio = (int) date('Y');

        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // 1. Obtener la comisión acumulada del vendedor solicitante en el mes actual
        $sql_comision = $wpdb->prepare(
            "SELECT SUM(comision_ganada_usd) 
             FROM {$tabla_ledger} 
             WHERE vendedor_id = %d 
             AND MONTH(created_at) = %d 
             AND YEAR(created_at) = %d",
            $vendedor_id, $mes, $anio
        );
        $comision_mes = floatval( $wpdb->get_var( $sql_comision ) );

        // 2. Obtener el ranking de Gamificación desde el Modelo
        $commission_model = new Suite_Model_Commission();
        $gamification = $commission_model->get_gamification_winners( $mes, $anio );

        // Formateo visual para el frontend
        if ( $gamification['pez_gordo'] ) {
            $gamification['pez_gordo']->total_vendido = number_format( floatval( $gamification['pez_gordo']->total_vendido ), 2 );
        }
        
        // 3. Devolver los datos listos para pintar
        $this->send_success( [
            'mes_evaluado'    => date_i18n( 'F Y' ),
            'comision_actual' => number_format( $comision_mes, 2 ),
            'gamificacion'    => $gamification
        ] );
    }
}



/**
 * Controlador AJAX: Cierre de Mes y Adjudicación de Premios (Fase 2.2)
 */
class Suite_Ajax_Freeze_Commissions extends Suite_AJAX_Controller {

    protected $action_name = 'suite_freeze_commissions';
    protected $required_capability = 'read';

    protected function process() {
		
		if ( ! current_user_can('manage_options') && ! current_user_can('suite_freeze_commissions') ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.' );
            return;
        }		
		

        $fecha_corte = isset( $_POST['fecha_corte'] ) ? sanitize_text_field( $_POST['fecha_corte'] ) : current_time('mysql');
        $dale_play_winner_id = isset( $_POST['dale_play_winner_id'] ) ? intval( $_POST['dale_play_winner_id'] ) : 0;

        if ( ! $dale_play_winner_id ) {
            $this->send_error( 'Se requiere especificar el ganador manual del premio Dale Play.' );
        }

        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // ==========================================
        // 2. CÁLCULO DE PREMIOS AUTOMÁTICOS
        // ==========================================
        
        // A) Ganador "🐟 Pez Gordo" (Suma más alta de monto base)
        // Ignoramos deducciones (< 0) y bonos sin venta (= 0)
       
		
		
        $pez_gordo = $wpdb->get_row( $wpdb->prepare(
            "SELECT vendedor_id, SUM(monto_base_usd) as total_vendido 
             FROM {$tabla_ledger} 
             WHERE estado_pago IN ('pendiente', 'pagado') 
             AND MONTH(created_at) = MONTH(%s) AND YEAR(created_at) = YEAR(%s) 
             AND created_at <= %s AND monto_base_usd > 0
             GROUP BY vendedor_id ORDER BY total_vendido DESC LIMIT 1",
            $fecha_corte, $fecha_corte, $fecha_corte
        ) );
		
		
		
		

        // B) Ganador "🏃 Deja pa' los demás" (Mayor cantidad de órdenes)
        $deja_pa = $wpdb->get_row( $wpdb->prepare(
            "SELECT vendedor_id, COUNT(id) as total_ordenes 
             FROM {$tabla_ledger} 
             WHERE estado_pago IN ('pendiente', 'pagado') 
             AND MONTH(created_at) = MONTH(%s) AND YEAR(created_at) = YEAR(%s) 
             AND created_at <= %s AND monto_base_usd > 0
             GROUP BY vendedor_id ORDER BY total_ordenes DESC LIMIT 1",
            $fecha_corte, $fecha_corte, $fecha_corte
        ) );

        $premios = [
            'dale_play'         => [ 'id' => $dale_play_winner_id, 'monto' => 20.00, 'nombre' => "▶️ Dale Play" ],
            'pez_gordo'         => [ 'id' => $pez_gordo ? $pez_gordo->vendedor_id : 0, 'monto' => 20.00, 'nombre' => "🐟 Pez Gordo" ],
            'deja_pa_los_demas' => [ 'id' => $deja_pa ? $deja_pa->vendedor_id : 0, 'monto' => 20.00, 'nombre' => "🏃 Deja pa' los demás" ]
        ];

        // ==========================================
        // 3. INYECCIÓN DE DINERO Y SALÓN DE LA FAMA
        // ==========================================
        
        $historial_ganadores = [];

        foreach ( $premios as $key => $data ) {
            if ( $data['id'] > 0 ) {
                // Inyectamos fila en el Ledger
                $wpdb->insert(
                    $tabla_ledger,
                    [
                        'quote_id'            => 0, // 0 indica bono, no amarrado a una orden física
                        'vendedor_id'         => $data['id'],
                        'monto_base_usd'      => 0,
                        'comision_ganada_usd' => $data['monto'],
                        'estado_pago'         => 'pagado' // Nace como 'pagado' para quedar congelado en este mismo corte
                    ],
                    [ '%d', '%d', '%f', '%f', '%s' ]
                );

                // Recopilamos datos para el Salón de la Fama
                $user_info = get_userdata( $data['id'] );
                $historial_ganadores[$key] = [
                    'vendedor_id'   => $data['id'],
                    'vendedor_name' => $user_info ? $user_info->display_name : 'Desconocido',
                    'monto_premio'  => $data['monto'],
                    'premio_nombre' => $data['nombre']
                ];
            }
        }

        // Guardar el JSON Inmutable del Salón de la Fama en wp_options
        $mes_cierre = date( 'Y_m', strtotime( $fecha_corte ) );
        update_option( 'suite_hall_of_fame_' . $mes_cierre, wp_json_encode( $historial_ganadores ), false );

        // ==========================================
        // 4. CIERRE GENERAL (CONGELAMIENTO)
        // ==========================================
        
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tabla_ledger} 
                 SET estado_pago = 'pagado' 
                 WHERE estado_pago = 'pendiente' AND created_at <= %s",
                $fecha_corte
            )
        );

        if ( $updated !== false ) {
            // Auditoría
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'cierre_mes', "Cierre de Mes ejecutado. {$updated} comisiones congeladas. Premios adjudicados en el Ledger." );
            }
            $this->send_success( "Cierre contable ejecutado. Se congelaron {$updated} registros y se adjudicaron los premios exitosamente." );
        } else {
            $this->send_error( 'Fallo de integridad en base de datos al intentar congelar el Ledger.', 500 );
        }
    }
}








/**
 * Controlador AJAX: Auditoría de Comisiones (RLS Aplicado)
 */
class Suite_Ajax_Commission_Audit extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_commission_audit';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;

        $user_id = get_current_user_id();
        $user_roles = (array) wp_get_current_user()->roles;
        
        // Validación de gerencia unificada (Admin, suite_gerente, o gerente)
        $is_admin_gerente = current_user_can( 'manage_options' ) || in_array( 'suite_gerente', $user_roles ) || in_array( 'gerente', $user_roles );

        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';
        $tabla_users  = $wpdb->users;

        // Sentencia SQL Base (Sanitizada: Retiramos 'l.notas' para evitar fallos de esquema)
        $sql = "SELECT l.id, l.quote_id, l.monto_base_usd, l.comision_ganada_usd, 
                       l.estado_pago, l.recibo_loyverse, l.estado_auditoria, l.created_at, 
                       UNIX_TIMESTAMP(l.created_at) AS timestamp_orden,
                       u.display_name AS vendedor_nombre 
                FROM {$tabla_ledger} l
                LEFT JOIN {$tabla_users} u ON l.vendedor_id = u.ID";

        // Barrera Zero-Trust (Row-Level Security)
        if ( ! $is_admin_gerente ) {
            $sql .= $wpdb->prepare( " WHERE l.vendedor_id = %d", $user_id );
        }

        $sql .= " ORDER BY l.id DESC LIMIT 1000";

        $resultados = $wpdb->get_results( $sql );

        // Enviamos la data a la vista. El DataTables (JS) ahora sí tiene todas las piezas.
        $this->send_success( $resultados );
    }
}





/**
 * Controlador AJAX: Acciones Manuales del Auditor de Loyverse (Fase 5.2)
 * Permite al administrador aprobar forzosamente o anular comisiones incongruentes.
 */
class Suite_Ajax_Process_Audit_Action extends Suite_AJAX_Controller {

    protected $action_name = 'suite_process_audit_action';
    
    // BARRERA ABSOLUTA: Solo los administradores financieros pueden tocar esto
    protected $required_capability = 'manage_options'; 

    protected function process() {
        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        $ledger_id = isset( $_POST['ledger_id'] ) ? intval( $_POST['ledger_id'] ) : 0;
        $action    = isset( $_POST['audit_action'] ) ? sanitize_text_field( $_POST['audit_action'] ) : '';

        if ( ! $ledger_id || ! in_array( $action, ['force_approve', 'reject_fraud'] ) ) {
            $this->send_error( 'Datos de auditoría inválidos o corruptos.', 400 );
        }

        if ( $action === 'force_approve' ) {
            // APROBACIÓN FORZADA: Pasa la auditoría a verificado, el pago sigue pendiente hasta cierre de mes.
            $actualizado = $wpdb->update(
                $tabla_ledger,
                [ 'estado_auditoria' => 'verificado' ],
                [ 'id' => $ledger_id ],
                [ '%s' ],
                [ '%d' ]
            );

            if ( $actualizado === false ) $this->send_error('Error al aprobar en BD.');
            $this->send_success( ['message' => 'Comisión verificada forzosamente.'] );

        } elseif ( $action === 'reject_fraud' ) {
            // ANULACIÓN POR FRAUDE: Mata la auditoría y anula el pago permanentemente.
            $actualizado = $wpdb->update(
                $tabla_ledger,
                [ 
                    'estado_auditoria' => 'incongruente',
                    'estado_pago'      => 'anulado'
                ],
                [ 'id' => $ledger_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            if ( $actualizado === false ) $this->send_error('Error al anular en BD.');
            $this->send_success( ['message' => 'Comisión anulada permanentemente por fraude o error.'] );
        }
    }
}



/**
 * Controlador AJAX: Ejecución Manual del Auditor de Loyverse (Fase 5.3)
 * Conecta con la API REST de Loyverse, aplica sanitización de formato y concilia montos.
 */
class Suite_Ajax_Run_Manual_Audit extends Suite_AJAX_Controller {

    protected $action_name = 'suite_run_manual_audit';
    
    // Solo la gerencia/administración puede disparar la auditoría masiva
    protected $required_capability = 'manage_options'; 

    protected function process() {
        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // 🚨 ATENCIÓN CARLOS: Pega aquí tu Token de Loyverse
        $api_token = '012d2a9b2e0a4930a60d76ce769f1ec8';

        // 1. AGRUPACIÓN INTELIGENTE
        $pendientes = $wpdb->get_results( "
            SELECT recibo_loyverse, SUM(monto_base_usd) as total_erp
            FROM {$tabla_ledger}
            WHERE estado_auditoria = 'pendiente' 
              AND recibo_loyverse IS NOT NULL 
              AND recibo_loyverse != ''
            GROUP BY recibo_loyverse
            LIMIT 50
        " );

        if ( empty( $pendientes ) ) {
            $this->send_success( ['message' => 'El Ledger está limpio. No hay recibos pendientes por auditar.'] );
        }

        $verificados = 0;
        $incongruentes = 0;

        // 2. CICLO DE AUDITORÍA
        foreach ( $pendientes as $req ) {
            
            // --- INICIO MAGIA DE FORMATEO (Fase 5.3) ---
            $raw_receipt = trim( $req->recibo_loyverse );
            
            // A. Quitamos guiones accidentales y todos los ceros a la izquierda
            $clean_receipt = ltrim( str_replace('-', '', $raw_receipt), '0' );
            
            // B. Aplicamos máscara "nn-nnnn" o "n-nnnn" (Guion antes de los últimos 4 dígitos)
            $len = strlen( $clean_receipt );
            if ( $len >= 5 ) {
                $formatted_receipt = substr( $clean_receipt, 0, $len - 4 ) . '-' . substr( $clean_receipt, -4 );
            } else {
                // Si el recibo es extrañamente corto, lo mandamos tal cual para que Loyverse decida
                $formatted_receipt = $clean_receipt;
            }
            
            // Codificamos para URL segura (ej: 45-1243)
            $recibo_url = urlencode( $formatted_receipt );
            // --- FIN MAGIA DE FORMATEO ---

            $url = "https://api.loyverse.com/v1.0/receipts/{$recibo_url}";

            $response = wp_remote_get( $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_token,
                    'Accept'        => 'application/json'
                ],
                'timeout' => 10 
            ]);

			
            $nuevo_estado = 'incongruente';
			$log_detalle = "Recibo: {$formatted_receipt} | ";

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $body['total_money'] ) ) {
					$monto_loyverse = floatval( $body['total_money'] );
					$monto_erp      = floatval( $req->total_erp );

					$diferencia = abs( $monto_loyverse - $monto_erp );
					$log_detalle .= "Loyverse: {$monto_loyverse} | ERP: {$monto_erp} | Dif: {$diferencia}";

					if ( $diferencia <= 0.05 ) {
						$nuevo_estado = 'verificado';
					}
				}
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$log_detalle .= "ERROR API: Código HTTP {$code}";
			}

			// REGISTRO DE TELEMETRÍA: Para que veas qué pasó en la tabla suite_logs
			if ( function_exists('suite_record_log') ) {
				suite_record_log( 'auditoria_pos', $log_detalle );
			}

            // 4. ACTUALIZACIÓN DEL LEDGER EN MASA (Usamos el recibo original guardado en la BD para el WHERE)
            $wpdb->update(
                $tabla_ledger,
                [ 'estado_auditoria' => $nuevo_estado ],
                [ 'recibo_loyverse' => $req->recibo_loyverse, 'estado_auditoria' => 'pendiente' ],
                [ '%s' ],
                [ '%s', '%s' ]
            );

            if ( $nuevo_estado === 'verificado' ) {
                $verificados++;
            } else {
                $incongruentes++;
            }
        }

        $this->send_success( [
            'message' => "Auditoría finalizada.<br>✅ <b>{$verificados}</b> Verificados.<br>🚨 <b>{$incongruentes}</b> Incongruencias o errores."
        ] );
    }
}



/**
 * Controlador AJAX: Salón de la Fama Histórico
 */
class Suite_Ajax_Hall_of_Fame extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_hall_of_fame';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;

        // Leemos las opciones inmutables generadas en la Fase 2.2
        $resultados = $wpdb->get_results( "
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'suite_hall_of_fame_%'
            ORDER BY option_name DESC
        " );

        $fame_data = [];

        foreach ( $resultados as $row ) {
            // Limpiamos el string para dejar solo el año_mes
            $mes_raw = str_replace( 'suite_hall_of_fame_', '', $row->option_name );
            $premios = json_decode( $row->option_value, true );

            if ( is_array( $premios ) ) {
                $fame_data[] = [
                    'mes'     => $mes_raw,
                    'premios' => $premios
                ];
            }
        }

        $this->send_success( $fame_data );
    }
}



/**
 * Controlador: Liquidación de Comisiones Seleccionadas
 */
class Suite_Ajax_Pay_Selected extends Suite_AJAX_Controller {
    protected $action_name = 'suite_pay_selected_commissions';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;
		
		if ( ! current_user_can('manage_options') && ! current_user_can('suite_action_approve_commissions') ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.' );
            return;
        }
		
        $ids = isset( $_POST['ledger_ids'] ) ? array_map( 'intval', $_POST['ledger_ids'] ) : [];

        if ( empty( $ids ) ) {
            $this->send_error( 'No se seleccionaron comisiones para pagar.' );
        }

        $ids_string = implode( ',', $ids );
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        // UPDATE blindado: Solo afecta a los IDs enviados que sigan estando 'pendiente'
        $updated = $wpdb->query( "UPDATE {$tabla_ledger} SET estado_pago = 'pagado' WHERE id IN ({$ids_string}) AND estado_pago = 'pendiente'" );

        if ( $updated !== false ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'pago_comisiones', "Se liquidaron {$updated} líneas contables de forma manual." );
            }
            $this->send_success( "Se han pagado {$updated} registros exitosamente." );
        } else {
            $this->send_error( 'Fallo de integridad al actualizar el Ledger.' );
        }
    }
}

/**
 * Controlador: Registro de Abonos / Anticipos
 */
class Suite_Ajax_Register_Abono extends Suite_AJAX_Controller {
    protected $action_name = 'suite_register_abono';
    protected $required_capability = 'read';
	
    protected function process() {
        global $wpdb;
		
		if ( ! current_user_can('manage_options') && ! current_user_can('suite_action_approve_commissions') ) {
            $this->send_error( 'Privilegios insuficientes para realizar esta acción.' );
            return;
        }		
		
        $vendedor_id = isset( $_POST['vendedor_id'] ) ? intval( $_POST['vendedor_id'] ) : 0;
        $monto = isset( $_POST['monto'] ) ? floatval( $_POST['monto'] ) : 0;

        if ( ! $vendedor_id || $monto <= 0 ) {
            $this->send_error( 'Datos de abono inválidos.' );
        }

		// El abono nace como PENDIENTE y NEGATIVO para restar en la próxima liquidación
        $insert = $wpdb->insert(
            $wpdb->prefix . 'suite_comisiones_ledger',
            [
                'quote_id'            => 0,
                'vendedor_id'         => $vendedor_id,
                'monto_base_usd'      => 0,
                'comision_ganada_usd' => -$monto,
                'estado_pago'         => 'pendiente'
                // ELIMINADA LA COLUMNA 'notas' PARA EVITAR EL CRASH DE MYSQL
            ],
            [ '%d', '%d', '%f', '%f', '%s' ] // ELIMINADO UN '%s' AL FINAL
        );

        if ( $insert ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'abono_comision', "Abono de \${$monto} registrado para el usuario ID {$vendedor_id}." );
            }
            $this->send_success( "Abono de \${$monto} registrado con éxito en el estado de cuenta." );
        } else {
            $this->send_error( 'No se pudo registrar el abono.' );
        }
    }
}
```

### ARCHIVO: `includes/Models/class-suite-model-commission.php`
```php
<?php
/**
 * Modelo de Base de Datos: Comisiones, Metas y Gamificación (Módulo 4)
 *
 * Maneja el cálculo inmutable del 1.5% de comisión y determina a los 
 * ganadores de los premios mensuales ("Pez Gordo", "Deja pa' los demás").
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Commission extends Suite_Model_Base {

    /**
     * Define la tabla principal del modelo (Ledger de Comisiones)
     */
    protected function set_table_name() {
        return 'suite_comisiones_ledger';
    }


    /**
     * Analiza la base de datos para determinar los ganadores de la Gamificación.
     * Solo cuenta ventas efectivamente cerradas ('pagado', 'despachado').
     *
     * @param int $mes  Mes a evaluar (1-12)
     * @param int $anio Año a evaluar (ej. 2024)
     * @return array    Array asociativo con los datos de los ganadores
     */
	public function get_gamification_winners( $mes, $anio ) {
        $tabla_ledger   = $this->wpdb->prefix . 'suite_comisiones_ledger';
        $tabla_user     = $this->wpdb->prefix . 'users';
        $tabla_usermeta = $this->wpdb->usermeta;

        // 1. Ganador "Pez Gordo" ($20): Mayor volumen acumulado (Ledger + Filtro Elegibilidad)
        $pez_gordo = $this->wpdb->get_row( $this->wpdb->prepare( "
		
		
            SELECT l.vendedor_id, u.display_name, SUM(l.monto_base_usd) as total_vendido
            FROM {$tabla_ledger} l
            INNER JOIN {$tabla_user} u ON l.vendedor_id = u.ID
            INNER JOIN {$tabla_usermeta} um ON l.vendedor_id = um.user_id
            WHERE l.estado_pago IN ('pendiente', 'pagado') 
              AND MONTH(l.created_at) = %d 
              AND YEAR(l.created_at) = %d 
              AND l.monto_base_usd > 0
			  
			  
			  
              AND um.meta_key = 'suite_participa_comisiones' AND um.meta_value = '1'
            GROUP BY l.vendedor_id
            ORDER BY total_vendido DESC
            LIMIT 1
        ", intval( $mes ), intval( $anio ) ) );

        // 2. Ganador "Deja pa' los demás" ($20): Mayor cantidad de ventas (Ledger + Filtro Elegibilidad)
        $deja_pa_los_demas = $this->wpdb->get_row( $this->wpdb->prepare( "
            SELECT l.vendedor_id, u.display_name, COUNT(l.id) as cantidad_ventas
            FROM {$tabla_ledger} l
            INNER JOIN {$tabla_user} u ON l.vendedor_id = u.ID
            INNER JOIN {$tabla_usermeta} um ON l.vendedor_id = um.user_id
            WHERE l.estado_pago IN ('pendiente', 'pagado')
              AND MONTH(l.created_at) = %d 
              AND YEAR(l.created_at) = %d 
              AND l.monto_base_usd > 0
              AND um.meta_key = 'suite_participa_comisiones' AND um.meta_value = '1'
            GROUP BY l.vendedor_id
            ORDER BY cantidad_ventas DESC
            LIMIT 1
        ", intval( $mes ), intval( $anio ) ) );

        return [
            'pez_gordo'         => $pez_gordo,
            'deja_pa_los_demas' => $deja_pa_los_demas
        ];
    }

    /**
     * Asignación manual de un premio por parte del Administrador (Ej: "Dale play").
     *
     * @param int    $vendedor_id   ID del vendedor premiado
     * @param string $premio_nombre Nombre del premio (Ej: "Dale play")
     * @param float  $monto         Monto en dólares a premiar (Ej: 10.00)
     * @param int    $mes           Mes correspondiente
     * @param int    $anio          Año correspondiente
     * @return int|false
     */
    public function assign_manual_prize( $vendedor_id, $premio_nombre, $monto, $mes, $anio ) {
        $tabla_premios = $this->wpdb->prefix . 'suite_premios_mensuales';

        $inserted = $this->wpdb->insert( $tabla_premios, [
            'vendedor_id'          => intval( $vendedor_id ),
            'mes'                  => intval( $mes ),
            'anio'                 => intval( $anio ),
            'premio_nombre'        => sanitize_text_field( $premio_nombre ),
            'monto_premio'         => floatval( $monto ),
            'asignado_manualmente' => 1
        ] );

        return $inserted ? $this->wpdb->insert_id : false;
    }
	

	
	
	/**
     * Obtiene las estadísticas financieras (Billetera) de un vendedor.
     * 
     * @param int $vendedor_id ID del vendedor.
     * @param int|null $mes Mes a consultar.
     * @param int|null $anio Año a consultar.
     * @return array Billetera estructurada (Totales y Últimas 10 Transacciones)
     */
    public function get_vendedor_stats( $vendedor_id, $mes = null, $anio = null ) {
        $mes = $mes ? intval( $mes ) : intval( date( 'm' ) );
        $anio = $anio ? intval( $anio ) : intval( date( 'Y' ) );

        $tabla_ledger = $this->table_name;
        $tabla_cot    = $this->wpdb->prefix . 'suite_cotizaciones';

        // 1. Agrupación Contable (Totales por Estado)
        $sql_totales = $this->wpdb->prepare("
            SELECT estado_pago, SUM(comision_ganada_usd) as total
            FROM {$tabla_ledger}
            WHERE vendedor_id = %d AND MONTH(created_at) = %d AND YEAR(created_at) = %d
            GROUP BY estado_pago
        ", $vendedor_id, $mes, $anio);

        $resultados_totales = $this->wpdb->get_results( $sql_totales );

        $totales = [ 'pendiente' => 0.00, 'pagado' => 0.00 ];
        foreach ( $resultados_totales as $row ) {
            if ( isset( $totales[ $row->estado_pago ] ) ) {
                $totales[ $row->estado_pago ] = floatval( $row->total );
            }
        }

        // 2. Historial de Transacciones (Últimas 10 del mes)
        $sql_historial = $this->wpdb->prepare("
            SELECT l.comision_ganada_usd, l.estado_pago, l.created_at, c.codigo_cotizacion, c.total_usd
            FROM {$tabla_ledger} l
            LEFT JOIN {$tabla_cot} c ON l.quote_id = c.id
            WHERE l.vendedor_id = %d AND MONTH(l.created_at) = %d AND YEAR(l.created_at) = %d
            ORDER BY l.id DESC
            LIMIT 10
        ", $vendedor_id, $mes, $anio);

        $historial = $this->wpdb->get_results( $sql_historial );

        return [
            'totales'   => $totales,
            'historial' => $historial
        ];
    }	

	
	
	/**
     * LOGÍSTICA INVERSA UNIVERSAL (FASE 5.4)
     * Anula o deduce la comisión de un pedido devuelto (B2B o Interno).
     */
    public function reverse_commission( $quote_id ) {
        if ( empty( $quote_id ) ) return;

        // Buscamos todas las líneas contables asociadas a esta orden
        $registros = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE quote_id = %d AND estado_pago != 'anulado'",
            intval( $quote_id )
        ) );

        if ( empty( $registros ) ) return;

        foreach ( $registros as $row ) {
            // Protección de Idempotencia: Omitir si ya es una deducción negativa
            if ( floatval( $row->comision_ganada_usd ) < 0 ) {
                continue;
            }

            if ( $row->estado_pago === 'pendiente' ) {
                // ESCENARIO A: Comisión Pendiente -> Soft Delete (Anulado)
                // Usamos el método update de tu modelo para mantener la integridad
                $this->wpdb->update(
                    $this->table_name,
                    [ 'estado_pago' => 'anulado' ],
                    [ 'id' => $row->id ],
                    [ '%s' ],
                    [ '%d' ]
                );

            } elseif ( $row->estado_pago === 'pagado' ) {
                // ESCENARIO B: Comisión Pagada -> Inyección de Contra-asiento
                $nota_deduccion = "Deducción por Logística Inversa - Orden #{$row->quote_id}";
                
                // Detectamos si era B2B leyendo la nota original
                if ( ! empty($row->notas) && strpos( $row->notas, 'B2B' ) !== false ) {
                    $nota_deduccion .= " (Reverso Aliado B2B)";
                }

                $this->insert([
                    'quote_id'            => $row->quote_id,
                    'vendedor_id'         => $row->vendedor_id,
                    'monto_base_usd'      => -abs(floatval( $row->monto_base_usd )),
                    'comision_ganada_usd' => -abs(floatval( $row->comision_ganada_usd )),
                    'estado_pago'         => 'pendiente',
                    'notas'               => $nota_deduccion
                ]);
            }
        }
    }

    /**
     * ADJUDICACIÓN DE PREMIOS (Dinero Real)
     * Liquida los premios de Gamificación inyectándolos en el Ledger como dólares reales.
     * 
     * @param int $mes Mes que se acaba de cerrar
     * @param int $anio Año del cierre
     */
    public function award_monthly_prizes( $mes, $anio ) {
        $winners = $this->get_gamification_winners( $mes, $anio );

        // 1. Premio: Pez Gordo ($20)
        if ( ! empty( $winners['pez_gordo'] ) ) {
            $this->insert([
                'quote_id'            => 0, // 0 indica que no proviene de una venta, es un bono
                'vendedor_id'         => $winners['pez_gordo']->vendedor_id,
                'monto_base_usd'      => 0,
                'comision_ganada_usd' => 20.00,
                'estado_pago'         => 'pendiente'
            ]);
        }

        // 2. Premio: Deja pa' los demás ($20)
        if ( ! empty( $winners['deja_pa_los_demas'] ) ) {
            $this->insert([
                'quote_id'            => 0,
                'vendedor_id'         => $winners['deja_pa_los_demas']->vendedor_id,
                'monto_base_usd'      => 0,
                'comision_ganada_usd' => 20.00,
                'estado_pago'         => 'pendiente'
            ]);
        }
    }
	
	
	/**
     * FASE 5 (ARMONIZADA): Registra la comisión al momento del despacho, 
     * validando fraude por recibo duplicado y soportando ventas compartidas al 1.5%.
     */
    public function registrar_comision_despacho( $quote_id, $vendedor_id, $monto_base_usd, $recibo_loyverse, $colaboradores = [] ) {
        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        $recibo_limpio = sanitize_text_field( trim( $recibo_loyverse ) );

        if ( empty( $recibo_limpio ) ) {
            return new WP_Error( 'fraude_loyverse', 'El número de recibo Loyverse es obligatorio para comisionar.' );
        }

        // 1. BLINDAJE ANTI-DUPLICIDAD: ¿Este recibo ya entró al Ledger en un despacho anterior?
        // Nota: Si es una venta compartida, entrarán 2 registros a la vez al final de esta función, 
        // por lo que revisar una sola vez al principio es 100% seguro y no bloquea a los colaboradores.
        $existe_recibo = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tabla_ledger} WHERE recibo_loyverse = %s LIMIT 1",
            $recibo_limpio
        ) );

        if ( $existe_recibo ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'alerta_fraude', "Intento de duplicidad de comisión. Recibo #{$recibo_limpio} ya existe (ID: {$existe_recibo})." );
            }
            return new WP_Error( 'fraude_loyverse', "FRAUDE DETECTADO: El recibo Loyverse #{$recibo_limpio} ya fue registrado." );
        }

        // 2. REGLA DE NEGOCIO: Comisión Fija del 1.5%
        $porcentaje = 0.015;
        $comision_total = floatval( $monto_base_usd ) * $porcentaje;

        if ( $comision_total <= 0 ) return false;

        // 3. ARMAR EL POOL DE BENEFICIARIOS (Titular + Colaboradores)
        $beneficiarios = [ intval( $vendedor_id ) ];
        
        if ( is_array( $colaboradores ) && !empty( $colaboradores ) ) {
            foreach ( $colaboradores as $colab_id ) {
                $colab_id = intval( $colab_id );
                if ( $colab_id > 0 && ! in_array( $colab_id, $beneficiarios ) ) {
                    $beneficiarios[] = $colab_id;
                }
            }
        }

        // 4. EJECUTAR DIVISIÓN FINANCIERA
        $cantidad_vendedores = count( $beneficiarios );
        $base_dividida       = round( floatval( $monto_base_usd ) / $cantidad_vendedores, 2 );
        $comision_dividida   = round( $comision_total / $cantidad_vendedores, 2 );

        // 5. INSERCIÓN MÚLTIPLE EN EL LEDGER
        $inserted_ids = [];
        foreach ( $beneficiarios as $ben_id ) {
            $insertado = $wpdb->insert( $tabla_ledger, [
                'quote_id'            => intval( $quote_id ),
                'vendedor_id'         => $ben_id,
                'monto_base_usd'      => $base_dividida,
                'comision_ganada_usd' => $comision_dividida,
                'recibo_loyverse'     => $recibo_limpio,
                'estado_pago'         => 'pendiente',
                'estado_auditoria'    => 'pendiente' // Nace pendiente para Fase 5.2
            ] );

            if ( $insertado ) {
                $inserted_ids[] = $wpdb->insert_id;
            }
        }

        if ( empty( $inserted_ids ) ) {
            return new WP_Error( 'db_error', 'Error interno al registrar la comisión en el Ledger.' );
        }

        return $inserted_ids;
    }
	
	
	public function get_global_balances( $mes, $anio ) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'suite_comisiones_ledger'; // Nombre correcto 

        $start_date = sprintf( "%04d-%02d-01 00:00:00", $anio, $mes );
        $end_date   = date( "Y-m-t 23:59:59", strtotime( $start_date ) );

		
		
		
        // CORRECCIÓN FORENSE: Solo extraemos el dinero que legítimamente está "pendiente" de pago o descuento.
        // Ignoramos por completo los registros "pagados" y "anulados".
        $transacciones = $wpdb->get_results( $wpdb->prepare( "
            SELECT id, vendedor_id, quote_id, comision_ganada_usd, estado_auditoria, created_at
            FROM {$tabla}
            WHERE created_at BETWEEN %s AND %s
            AND estado_pago = 'pendiente'
            ORDER BY created_at ASC
        ", $start_date, $end_date ) );
		
		
		
		

        $balances = array();
        foreach ( $transacciones as $tx ) {
            $v_id = $tx->vendedor_id;
            if ( ! isset( $balances[$v_id] ) ) {
                $balances[$v_id] = [
                    'vendedor_nombre' => get_userdata($v_id)->display_name,
                    'neto' => 0,
                    'advertencia_auditoria' => false,
                    'detalles' => []
                ];
            }

            $monto = floatval( $tx->comision_ganada_usd );
            $balances[$v_id]['neto'] += $monto;

            if ( in_array( $tx->estado_auditoria, ['incongruente', 'pendiente'] ) ) {
                $balances[$v_id]['advertencia_auditoria'] = true;
            }

            $concepto = ($tx->quote_id > 0) ? "Comisión Orden #{$tx->quote_id}" : "Bono / Ajuste Manual";
            
            $balances[$v_id]['detalles'][] = [
                'fecha' => date( 'd/m/Y', strtotime($tx->created_at) ),
                'concepto' => $concepto,
                'monto' => $monto,
                'estado_auditoria' => $tx->estado_auditoria
            ];
        }
        return array_values( $balances );
    }
	
}
```

### ARCHIVO: `views/app/tab-comisiones.php`
```php
<?php

/**
 * Vista: Dashboard de Comisiones y Gamificación (Módulo 4)
 * * Muestra las ganancias en tiempo real del vendedor y el ranking 
 * de los premios mensuales para fomentar la competencia.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Instanciar Modelo y solicitar datos del usuario actual
$vendedor_id = get_current_user_id();
$commission_model = new Suite_Model_Commission();
$stats_billetera = $commission_model->get_vendedor_stats( $vendedor_id );

// Validar si el usuario tiene privilegios gerenciales (Zero-Trust UI)
// FASE 4.2: Se delega a la matriz de permisos RBAC
$is_gerencia = current_user_can('manage_options') || current_user_can('suite_action_approve_commissions');

// --- INICIO DE NUEVO CÓDIGO (Restricción B2B) ---
// Identidad del usuario actual para ocultar gamificación
$is_b2b = get_user_meta( $vendedor_id, 'suite_is_b2b', true ) == '1';
// --- FIN DE NUEVO CÓDIGO ---

?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



<div id="TabComisiones" class="suite-tab-content" style="display: none;">
    <div class="suite-header-modern">
        <h2 style="margin:0; font-size: 22px; color: #0f172a;">🏆 Comisiones y Rendimiento</h2>
    </div>

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	<div class="suite-pills-nav" style="display:flex; gap:10px; margin: 20px 25px 0 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; flex-wrap: wrap;">
        
        <?php if ( ! $is_b2b ) : ?>
            <button class="tab-btn active pill-btn" data-target="comisiones-dashboard-view" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 15px; font-size: 14px; background: #fff; cursor: pointer;">📊 Dashboard Actual</button>
        <?php endif; ?>
        
        <button class="tab-btn pill-btn <?php echo $is_b2b ? 'active' : ''; ?>" data-target="comisiones-audit-view" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 15px; font-size: 14px; background: <?php echo $is_b2b ? '#fff' : 'transparent'; ?>; cursor: pointer;">📝 Mis Registros</button>
        
        <?php if ( ! $is_b2b ) : ?>
            <button class="tab-btn pill-btn" data-target="comisiones-fame-view" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 15px; font-size: 14px; background: transparent; cursor: pointer;">🎖️ Salón de la Fama</button>
        <?php endif; ?>
        
        
		
		<?php if ( current_user_can('manage_options') ) : ?>
            <button class="tab-btn pill-btn" data-target="comisiones-balance-view" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 15px; font-size: 14px; background: transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">⚖️ Balance de Pagos</button>
        <?php endif; ?>
		
		
        
    </div>
	
	
	
	

	<?php if ( ! $is_b2b ) : ?>
    <div id="comisiones-dashboard-view">

    <div style="padding: 25px;">
        
        <?php if ( $is_gerencia ) : ?>
        <div style="margin-bottom: 30px; padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="color: #991b1b; margin: 0 0 5px 0; font-size: 16px;">⚙️ Cierre Contable de Mes</h3>
                <p style="color: #7f1d1d; font-size: 13px; margin: 0;">Liquide las comisiones pendientes.
Esta acción pasará el Ledger a estado "pagado" y lo congelará permanentemente.</p>
            </div>
            <button id="btn-cierre-mes" class="btn-save-big" style="background-color: #dc2626; width: auto; padding: 10px 20px;">🔒 Ejecutar Cierre de Mes</button>
        </div>
        <?php endif;
?>

        <h3 style="color: #0f172a; margin-bottom: 15px; font-size: 18px;">💼 Mi Billetera (Mes Actual)</h3>
        <div style="display: flex; gap: 20px; margin-bottom: 25px;">
            <div class="kpi-card" style="flex: 1; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 20px; border-radius: 12px; color: white;">
   
                <small style="display:block; text-transform:uppercase; font-weight:bold; opacity: 0.9;">⏳ Comisiones Pendientes</small>
                <strong style="font-size: 32px;">$<?php echo number_format($stats_billetera['totales']['pendiente'], 2);
?></strong>
            </div>
            <div class="kpi-card" style="flex: 1; background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 20px; border-radius: 12px; color: white;">
                <small style="display:block; text-transform:uppercase; font-weight:bold; opacity: 0.9;">✅ Comisiones Pagadas (Liquidado)</small>
                <strong style="font-size: 32px;">$<?php echo number_format($stats_billetera['totales']['pagado'], 2);
?></strong>
            </div>
        </div>

        <h4 style="color: #475569; margin-bottom: 10px;">📄 Últimas Transacciones</h4>
        <div class="suite-table-responsive" style="margin-bottom: 30px;">
            <table class="suite-modern-table" style="width: 100%; text-align: left;">
                <thead>
                    <tr>
  
                        <th>Fecha</th>
                        <th>Orden</th>
                        <th>Venta Total</th>
                        <th>Mi Comisión</th>
    
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($stats_billetera['historial']) ) : ?>
  
                        <tr><td colspan="5" style="text-align:center; padding: 20px; color:#64748b;">No hay comisiones registradas este mes.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $stats_billetera['historial'] as $tx ) : 
             
               $badge_bg = $tx->estado_pago === 'pagado' ?
'#d1fae5' : '#fef3c7';
                            $badge_cl = $tx->estado_pago === 'pagado' ? '#065f46' : '#92400e';
?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($tx->created_at));
?></td>
                                <td>#<?php echo esc_html($tx->codigo_cotizacion);
?></td>
                                <td>$<?php echo number_format($tx->total_usd, 2);
?></td>
                                <td style="color:#059669;"><strong>+$<?php echo number_format($tx->comision_ganada_usd, 2);
?></strong></td>
                                <td><span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_cl; ?>;"><?php echo strtoupper($tx->estado_pago);
?></span></td>
                            </tr>
                        <?php endforeach;
?>
                    <?php endif;
?>
                </tbody>
            </table>
        </div>

    <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px; color: #111827; display: flex; align-items: center; gap: 10px;">
        🏆 Premios del Mes
    </h3>
    
    <div class="kpi-row" style="display: flex; flex-wrap: wrap; gap: 20px;">
    
    
        <div class="kpi-card" style="flex: 1; min-width: 250px; border-top: 4px solid #f59e0b; text-align: left;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <h4 style="margin: 0; color: #0f172a; font-size: 16px;">🦈 Pez Gordo</h4>
                <span class="pill-warn" style="font-size: 12px; font-weight: bold;">Premio: 
$20</span>
            </div>
            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Al vendedor con mayor volumen ($) facturado.</p>
            
            <div style="margin-top: 20px;
padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                <small style="color: #94a3b8;
text-transform: uppercase; font-weight: bold; font-size: 10px;">Líder Actual</small>
                <strong id="pez-gordo-name" style="font-size: 18px;
display: block; color: #1e293b; margin-top: 4px;">Calculando...</strong>
                <span id="pez-gordo-amount" style="color: #059669;
font-weight: 800; font-size: 15px;">$0.00</span>
            </div>
        </div>

        <div class="kpi-card" style="flex: 1;
min-width: 250px; border-top: 4px solid #3b82f6; text-align: left;">
            <div style="display: flex;
justify-content: space-between; align-items: start;">
                <h4 style="margin: 0;
color: #0f172a; font-size: 16px;">🏃 Deja pa' los demás</h4>
                <span class="pill-info" style="font-size: 12px;
font-weight: bold; background: #dbeafe; color: #1d4ed8;">Premio: $20</span>
            </div>
            <p style="font-size: 12px;
color: #64748b; margin-top: 5px;">Al vendedor con mayor cantidad de ventas (N°).</p>
            
            <div style="margin-top: 20px;
padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                <small style="color: #94a3b8;
text-transform: uppercase; font-weight: bold; font-size: 10px;">Líder Actual</small>
                <strong id="deja-pa-name" style="font-size: 18px;
display: block; color: #1e293b; margin-top: 4px;">Calculando...</strong>
                <span id="deja-pa-count" style="color: #2563eb;
font-weight: 800; font-size: 15px;">0 ventas</span>
            </div>
        </div>

        <div class="kpi-card" style="flex: 1;
min-width: 250px; border-top: 4px solid #8b5cf6; text-align: left; background: #faf5ff;">
            <div style="display: flex;
justify-content: space-between; align-items: start;">
                <h4 style="margin: 0;
color: #0f172a; font-size: 16px;">▶️ Dale Play</h4>
                <span style="font-size: 12px;
font-weight: bold; background: #ede9fe; color: #6d28d9; padding: 4px 10px; border-radius: 99px;">Premio: $10</span>
            </div>
            <p style="font-size: 12px;
color: #64748b; margin-top: 5px;">Por proactividad y cumplimiento en proyectos.</p>
            
            <div style="margin-top: 20px;
padding-top: 15px; border-top: 1px dashed #ddd6fe; text-align: center;">
                <span style="font-size: 32px;
display: block; margin-bottom: 5px;">🎯</span>
                <strong style="color: #5b21b6;
font-size: 13px;">Asignación Manual (Admin)</strong>
                <p style="font-size: 11px;
color: #7c3aed; margin-top: 2px;">Evaluado a fin de mes según Kanban de Proyectos.</p>
            </div>
        </div>

	</div> </div> </div> <?php endif; ?> 
    
    <div id="comisiones-audit-view" style="display: <?php echo $is_b2b ? 'block' : 'none'; ?>; padding: 25px;">
        
        <?php if ( current_user_can('manage_options') || current_user_can('suite_action_approve_commissions') ) : ?>
        <div style="margin-bottom: 20px; display:flex; gap:10px; flex-wrap: wrap; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
            
            <button id="btn-pay-selected" class="btn-modern-action" style="background:#059669; color:white; border:none; padding:8px 15px;">
                💵 Liquidar Seleccionados
            </button>
            
            <button id="btn-register-abono" class="btn-modern-action" style="background:#0284c7; color:white; border:none; padding:8px 15px;">
                💸 Registrar Abono / Anticipo
            </button>

            <?php if ( current_user_can('manage_options') ) : ?>
                <button id="btn-force-audit-sync" class="btn-modern-action" style="background:#4b5563; color:white; border:none; padding:8px 15px;">
                    🔄 Sincronizar con Loyverse Ahora
                </button>
            <?php endif; ?>

        </div>
        <?php endif; ?>
        
        <h3 style="color: #0f172a; margin-bottom: 15px; font-size: 18px;">📝 Auditoría General del Ledger</h3>
        <table class="suite-modern-table" id="auditTable" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 30px;">
                        <?php if ( current_user_can('manage_options') || current_user_can('suite_action_approve_commissions') ) : ?>
                            <input type="checkbox" id="chk-all-com" style="cursor:pointer;" title="Seleccionar todos">
                        <?php else: ?>
                            🔒
                        <?php endif; ?>
                    </th>
                    <th>ID Orden</th>
                    <th>Recibo Loyverse</th> 
                    <th>Vendedor</th>
                    <th>Monto Base</th>
                    <th>Comisión</th>
                    <th>Estado</th>
                    <th>Auditoría POS</th> 
                    <th>Fecha Operación</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

<?php if ( ! $is_b2b ) : ?>
	
	
	
	
	
	
	
    <div id="comisiones-fame-view" style="display:none; padding: 25px;">
        <h3 style="color: #0f172a; margin-bottom: 15px; font-size: 18px;">🎖️ Salón de la Fama Histórico</h3>
        <div id="fame-cards-container" style="display:flex; flex-wrap:wrap; gap:20px;">
        </div>
    </div>
    <?php endif; ?>

    <?php if ( current_user_can('manage_options') ) : ?>
    <div id="comisiones-balance-view" style="display:none; padding: 25px;">
        
        <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
            <div style="flex:1; min-width:200px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border:1px solid #bbf7d0; padding:20px; border-radius:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <small style="color:#166534; font-weight:bold; text-transform:uppercase;">💰 Total Nómina a Pagar</small>
                <strong id="kpi-total-nomina" style="display:block; font-size:28px; color:#15803d; margin-top:5px;">$0.00</strong>
            </div>
            <div style="flex:1; min-width:200px; background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border:1px solid #fecdd3; padding:20px; border-radius:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <small style="color:#9f1239; font-weight:bold; text-transform:uppercase;">♻️ Abonos Retenidos</small>
                <strong id="kpi-total-recuperado" style="display:block; font-size:28px; color:#be123c; margin-top:5px;">$0.00</strong>
            </div>
            <div style="flex:1; min-width:200px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border:1px solid #e2e8f0; padding:20px; border-radius:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <small style="color:#334155; font-weight:bold; text-transform:uppercase;">👥 Participantes Activos</small>
                <strong id="kpi-participantes" style="display:block; font-size:28px; color:#0f172a; margin-top:5px;">0</strong>
            </div>
        </div>

        <h3 style="color: #0f172a; margin-bottom: 15px; font-size: 18px;">📑 Detalles por Vendedor</h3>
        
        <div id="balance-accordion-container">
            <p style="text-align:center; color:#64748b;">Seleccione esta pestaña para cargar los datos.</p>
        </div>

    </div>
    <?php endif; ?>

</div>





```

