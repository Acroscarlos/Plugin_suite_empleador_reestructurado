/**
 * SuiteKanban - Módulo visual y lógico del Tablero de Pedidos
 * 
 * Gestiona la carga de tarjetas y los eventos Drag & Drop (SortableJS).
 * Intercepta el cierre de ventas (Módulo 4) para recolectar datos de comisión.
 */
const SuiteKanban = (function($) {
    'use strict';

    // Variable temporal para retener la tarjeta en memoria mientras se llena el modal
    let pendingDrop = null;

    // ==========================================
    // MÉTODOS PRIVADOS
    // ==========================================

    const buildCardHTML = function(order) {
        let waBtnHtml = '';
        if (order.wa_phone) {
            let msg = encodeURIComponent(`Hola ${order.cliente_nombre}, le contactamos respecto a su pedido ${order.codigo_cotizacion}: ${suite_vars.ajax_url}?action=suite_print_quote&id=${order.id}`);
            waBtnHtml = `<a href="https://api.whatsapp.com/send?phone=${order.wa_phone}&text=${msg}" target="_blank" class="btn-modern-action" style="padding: 4px; font-size:11px; text-decoration:none;">📱 WA</a>`;
        }

		// --- INICIO FASE 5.2: RESTRICCIÓN VISUAL B2B ---
        let mobilePayBtn = '';
        // El botón de pago desaparece si el usuario es Aliado Comercial
		if (!suite_vars.is_b2b && order.estado !== 'pagado' && order.estado !== 'despachado' && order.estado !== 'por_enviar') {			
            mobilePayBtn = `<button type="button" class="btn-modern-action trigger-mobile-pay" data-id="${order.id}" data-col="${order.estado}" style="padding: 4px; font-size:11px; background:#10b981; color:white; border:none; cursor:pointer;">💰 Pagar</button>`;
        }
		
		
		
		
		
		
		
		
		
		
		
		
		
		
        let reverseBtn = '';
        // La logística inversa también bloqueada visualmente para el B2B
        if (!suite_vars.is_b2b && suite_vars.is_admin) {
            if (order.estado === 'despachado') {
                reverseBtn = `<button class="btn-modern-action small trigger-reverse-logistics" data-id="${order.id}" style="color:#dc2626; border-color:#fca5a5; width:100%; margin-top:8px;">🔙 Logística Inversa (Admin)</button>`;
            } 
            // NUEVO: Reversa de 'Por Enviar' a 'Pagado'
            else if (order.estado === 'por_enviar') {
                reverseBtn = `<button class="btn-modern-action small trigger-reverse-to-paid" data-id="${order.id}" style="color:#d97706; border-color:#fcd34d; background:#fffbeb; width:100%; margin-top:8px;">🔙 Devolver a Pagado (Admin)</button>`;
            }
        }
		
		
		
		
		
		
		
		
		
		
		
		
        // --- FIN FASE 5.2 ---

        // --- INICIO CORRECCIÓN: Botón de Guía Logística (POD) ---
        let podBtn = '';
        if (order.estado === 'despachado' && order.pod_url) {
            podBtn = `<a href="${order.pod_url}" target="_blank" class="btn-modern-action small" style="color:#0284c7; border-color:#bae6fd;" title="Ver Guía / Comprobante de Entrega">📸 Guía</a>`;
        }
		// --- INICIO: BOTONES DE VALIDACIÓN DE PAGO (SOLO ADMIN) ---
        let adminValidationBtns = '';
        if (order.estado === 'pagado' && suite_vars.is_admin) {
            adminValidationBtns = `
                <div style="display:flex; gap:5px; margin-top:10px; padding-top:10px; border-top:1px dashed #e2e8f0;">
                    <button class="btn-modern-action trigger-view-payment" data-id="${order.id}" style="background:#3b82f6; color:white; border:none; padding:4px 8px; font-size:11px; flex:1;">👀 Ver Pago</button>
                    <button class="btn-modern-action trigger-approve-payment" data-id="${order.id}" style="background:#10b981; color:white; border:none; padding:4px 8px; font-size:11px;" title="Aprobar y pasar a Logística">✅</button>
                    <button class="btn-modern-action trigger-reject-payment" data-id="${order.id}" style="background:#ef4444; color:white; border:none; padding:4px 8px; font-size:11px;" title="No recibido (Devolver)">❌</button>
                </div>
            `;
        }
        // --- FIN: BOTONES VALIDACIÓN ---
    
        // --- INICIO: INDICADOR DE URGENCIA ---
        let priorityBadge = (order.prioridad == '1') 
            ? `<span style="background:#fee2e2; color:#dc2626; border: 1px solid #fca5a5; font-size:10px; font-weight:900; padding:2px 6px; border-radius:4px; margin-left:8px; box-shadow: 0 0 5px rgba(220, 38, 38, 0.4);">🚨 URGENTE</span>` 
            : '';
        // --- FIN: INDICADOR ---

        return `
        <div class="kanban-card" style="${order.prioridad == '1' ? 'border-left: 4px solid #dc2626;' : ''}" data-id="${order.id}" data-is-b2b="${order.vendedor_is_b2b ? '1' : '0'}">
            <div style="display:flex; justify-content:space-between; margin-bottom: 8px;">
                <div>
                    <span style="font-weight:bold; color:#0f172a;">#${order.codigo_cotizacion}</span>
                    ${priorityBadge}
                </div>
            </div>
            <div style="font-size: 13px; color:#475569; margin-bottom: 10px;">
                👤 ${order.cliente_nombre || 'Sin Nombre'} <br>
                <span style="font-weight:bold; color:#059669;">$${order.total_fmt}</span>
            </div>
            <div style="display:flex; gap: 5px; flex-wrap:wrap;">
                ${waBtnHtml}
                ${mobilePayBtn}
                <a href="${suite_vars.ajax_url}?action=suite_print_quote&id=${order.id}&nonce=${suite_vars.nonce}" target="_blank" class="btn-modern-action small" style="color:#475569;" title="Imprimir PDF">🖨️</a>
                ${podBtn}
            </div>
			${adminValidationBtns}
            ${reverseBtn}
        </div>
        `;
    };

    /**
     * Helper para devolver una tarjeta exactamente a su posición original
     * si el usuario cancela el drag & drop o falla el servidor.
     */
    const revertCard = function(itemEl, fromCol, oldIndex) {
        // Al usar el oldIndex, buscamos el elemento que AHORA ocupa ese índice.
        // Insertando el itemEl antes de ese nodo, vuelve exactamente a su lugar original.
        const referenceNode = fromCol.children[oldIndex] || null;
        fromCol.insertBefore(itemEl, referenceNode);
    };

    const initSortableColumns = function() {
        const columns = document.querySelectorAll('.kanban-column-body');
        
        columns.forEach(col => {
            new Sortable(col, {
                group: 'kanban', 
                animation: 150,
                ghostClass: 'kanban-ghost',
				
				
				// --- INICIO FASE 5.2: CAMPO DE FUERZA B2B (onMove) ---
                onMove: function (evt) {
                    if (suite_vars.is_b2b) {
                        // Leemos el data-status directamente de la columna origen y destino
                        const toStatus = evt.to.getAttribute('data-status');
                        const fromStatus = evt.from.getAttribute('data-status');
                        
                        // Zonas seguras para los Aliados Comerciales
                        const allowedColumns = ['emitida', 'pagado']; 

                        // Si intenta arrastrar a un área financiera o logística, bloqueamos el movimiento en vivo
                        if (!allowedColumns.includes(toStatus) || !allowedColumns.includes(fromStatus)) {
                            return false; 
                        }
                    }
                },
                // --- FIN FASE 5.2 ---				
				
				
				
				
				onEnd: function (evt) {
                    const itemEl = evt.item;  
                    const toCol = evt.to;     
                    const fromCol = evt.from; 
                    const oldIndex = evt.oldIndex; // <-- Capturamos la posición original exacta
                    
                    if (toCol === fromCol) return; 

                    const quoteId = itemEl.getAttribute('data-id');
                    const newStatus = toCol.getAttribute('data-status');
                    const oldStatus = fromCol.getAttribute('data-status'); // <-- AGREGADO: Necesario para validar de dónde viene

					// ----------------------------------------------------
                    // CANDADO MÓDULO 2: FÍSICA ZERO-TRUST ESTRICTA
                    // ----------------------------------------------------
                    const isMoveValid = function(oldS, newS) {
                        if (suite_vars.is_b2b) {
                            if (oldS === 'emitida' && newS === 'pagado') return true;
                            if (oldS === 'pagado' && newS === 'emitida') return true;
                            return false;
                        }

                        // REGLAS KANBAN V2: CANDADO ABSOLUTO
                        // Solo permitimos 2 movimientos manuales (porque ambos son interceptados por Modales de Seguridad)
                        if (oldS === 'emitida' && newS === 'pagado') return true; // Abre Modal de Pago
                        if (oldS === 'pagado' && newS === 'emitida') return true; // Abre Alerta de Doble Confirmación (Admin)
                        
                        // TODO LO DEMÁS ESTÁ PROHIBIDO MANUALMENTE. 
                        // Los avances a Logística y a Enviado se hacen EXCLUSIVAMENTE mediante los botones.
                        return false; 
                    };

                    if (!isMoveValid(oldStatus, newStatus)) {
                        alert('⛔ Movimiento no permitido. Debe respetar la secuencia lógica (Pendiente ➡️ Pagado ➡️ Por Enviar ➡️ Enviado).');
                        revertCard(itemEl, fromCol, oldIndex);
                        return;
                    }

                    // ----------------------------------------------------
                    // INTERCEPTOR SÚPER-MODAL Y CANDADOS DE REVERSO
                    // ----------------------------------------------------
                    if (newStatus === 'pagado' && oldStatus === 'emitida') {
                        
                        // 1. Guardar contexto y revertir tarjeta visualmente
                        pendingDrop = { quoteId, itemEl, fromCol, toCol, oldIndex };
                        revertCard(itemEl, fromCol, oldIndex);
                        
                        // 2. Limpiar Súper-Modal y preparar campos
                        $('#form-super-pago')[0].reset();
                        $('#sp-datos-envio-container, #box-agencia, #box-direccion').hide();
                        $('#sp-quote-id').val(quoteId);
                        
                        // Ajuste estricto de Zona Horaria (Caracas / Local)
                        const tzOffset = (new Date()).getTimezoneOffset() * 60000;
                        const localISOTime = (new Date(Date.now() - tzOffset)).toISOString().slice(0, 10);
                        $('#sp-fecha-pago').val(localISOTime);
                        
                        $('#modal-super-pago').fadeIn();

                    } else if (oldStatus === 'pagado' && newStatus === 'emitida') {
                        
                        // --- CANDADO ZERO-TRUST: REVERSO DE PAGO ---
                        if (!suite_vars.is_admin) {
                            alert('⛔ Acceso Denegado: Una vez en "Pagado", la tarjeta queda bloqueada. Solo un Administrador puede devolverla.');
                            revertCard(itemEl, fromCol, oldIndex);
                            return;
                        }

                        // Si es Admin, exigimos doble confirmación
                        const seguro = confirm('⚠️ MODO ADMIN: ¿Estás totalmente seguro de devolver esta orden a "Pendiente"?\n\nEl equipo de Logística no verá este pedido hasta que se vuelva a procesar el pago.');
                        
                        if (!seguro) {
                            revertCard(itemEl, fromCol, oldIndex);
                            return;
                        }

                        // Si el Admin confirma, dejamos que el sistema haga el cambio
                        updateCounters();
                        updateOrderStatus(quoteId, newStatus, itemEl, fromCol, oldIndex);

                    } else {
                        // Cualquier otro movimiento legal (Ej: Pagado -> Por Enviar)
                        updateCounters();
                        updateOrderStatus(quoteId, newStatus, itemEl, fromCol, oldIndex);
                    }
                },
            });
        });
    };

    /**
     * Envía un cambio de estado normal (Sin modal) a la base de datos
     */
    const updateOrderStatus = function(quoteId, newStatus, itemEl, originalCol, oldIndex) {
        SuiteAPI.post('suite_change_status_ajax', { 
            id: quoteId, 
            estado: newStatus 
        }).then(res => {
            if (!res.success) {
                alert('❌ Error: ' + (res.data.message || res.data));
                revertCard(itemEl, originalCol, oldIndex); // Revertir UI
                updateCounters();
            }
        }).catch(() => {
            alert('❌ Fallo de conexión al actualizar el pedido.');
            revertCard(itemEl, originalCol, oldIndex); // Revertir UI
            updateCounters();
        });
    };

    const updateCounters = function() {
        $('.kanban-column-body').each(function() {
            let status = $(this).data('status');
            let count = $(this).children('.kanban-card').length;
            $('#count-' + status).text(count);
        });
    };

    // ==========================================
    // LISTENERS DEL MODAL DE CIERRE DE VENTA
    // ==========================================
	const bindModalEvents = function() {
		
		// Multiplexor de campos logísticos dinámicos
        $('#sp-tipo-envio').on('change', function() {
            const tipo = $(this).val();
            const container = $('#sp-datos-envio-container');
            const boxAgencia = $('#box-agencia');
            const boxDireccion = $('#box-direccion');
            const boxSucursal = $('#box-sucursal'); // NUEVO
            
            // Elementos DOM
            const baseInputs = $('#sp-nombre-receptor, #sp-rif-receptor, #sp-telefono-receptor');
            const inputAgencia = $('#sp-agencia-envio');
            const inputDireccion = $('#sp-direccion-envio');
            const inputSucursal = $('#sp-sucursal'); // NUEVO

            if (tipo) {
                container.slideDown();
                baseInputs.prop('required', true); // Siempre requeridos (Retiro, Moto, Nacional)

                if (tipo === 'Retiro') {
                    boxAgencia.hide();
                    inputAgencia.prop('required', false).val('');
                    boxDireccion.hide();
                    inputDireccion.prop('required', false).val('');
                    // NUEVO: Mostrar Sucursal
                    boxSucursal.slideDown();
                    inputSucursal.prop('required', true);
                } 
                else if (tipo === 'Motorizado') {
                    // NUEVO: Ocultar Sucursal
                    boxSucursal.hide();
                    inputSucursal.prop('required', false).val('');
                    
                    boxAgencia.hide();
                    inputAgencia.prop('required', false).val('');
                    boxDireccion.slideDown();
                    inputDireccion.prop('required', true);
                } 
                else if (tipo === 'Nacional') {
                    // NUEVO: Ocultar Sucursal
                    boxSucursal.hide();
                    inputSucursal.prop('required', false).val('');
                    
                    boxAgencia.slideDown();
                    inputAgencia.prop('required', true);
                    boxDireccion.slideDown();
                    inputDireccion.prop('required', true);
                }
            } else {
                container.slideUp();
                baseInputs.prop('required', false);
                inputAgencia.prop('required', false);
                inputDireccion.prop('required', false);
                inputSucursal.prop('required', false).val(''); // NUEVO
            }
        });
		
		
		
		
		
		
		
		
		
		
		
		

        // Cancelar y cerrar Súper-Modal
        $('#close-super-pago, #btn-cancel-sp').on('click', function() {
            if (pendingDrop) {
                revertCard(pendingDrop.itemEl, pendingDrop.fromCol, pendingDrop.oldIndex);
                pendingDrop = null;
                updateCounters();
            }
            $('#modal-super-pago').fadeOut();
        });
        // --- FIN FASE 2 ---

        
        // 1. Cerrar o cancelar Modal (Botón "X" o click por fuera)
        $('#close-modal-cierre').on('click', function() {
            if (pendingDrop) {
                // Al cancelar, la tarjeta se devuelve a su posición milimétricamente exacta
                revertCard(pendingDrop.itemEl, pendingDrop.fromCol, pendingDrop.oldIndex);
                pendingDrop = null;
                updateCounters();
            }
            $('#modal-cierre-venta').fadeOut();
        });

        // 2. Procesar Formulario
        $('#btn-confirmar-pago').on('click', function(e) {
            e.preventDefault();
            if (!pendingDrop) return;

		// 1. Limpieza y ensamblaje de Factura/Nota de entrega
            const rawNumber = $('#cierre-loyverse').val().replace(/[^0-9]/g, '');
            const prefix = $('#cierre-recibo-prefijo').val();
            const finalReceipt = rawNumber ? (prefix + rawNumber) : '';
			
			const colaboradoresIds = $('#cierre-compartido').val() || [];
			
			
            // Recolectar datos (NOTA: Se removió la propiedad 'action' redundante)
            const payload = {
                id: pendingDrop.quoteId,
                estado: 'pagado',
                canal_venta: $('#cierre-canal').val(),
                metodo_pago: $('#cierre-pago').val(),
                metodo_entrega: $('#cierre-entrega').val(),
                recibo_loyverse: finalReceipt, // Aquí inyectamos el recibo ensamblado (Ej: "F1005")
                url_captura: $('#cierre-captura').val().trim(),
				colaboradores: colaboradoresIds,
				porcentaje_b2b: $('#cierre-b2b-percent').is(':visible') ? $('#cierre-b2b-percent').val() : 0
            };

            // Validar obligatorios (Nota: Validamos !rawNumber para asegurar que escribieron los dígitos)
            if (!payload.canal_venta || !payload.metodo_pago || !payload.metodo_entrega || !rawNumber) {
                return alert('⚠️ Por favor complete todos los campos obligatorios y asegúrese de ingresar el número de recibo.');
            }

            const btn = $(this);
            btn.prop('disabled', true).text('Procesando...');

            // Enviar al Backend
            SuiteAPI.post('suite_change_status_ajax', payload).then(res => {
                if (res.success) {
                    alert('✅ ' + (res.data.message || 'Pago procesado y comisión registrada.'));
                    $('#modal-cierre-venta').fadeOut();
                    pendingDrop = null; // Liberar memoria, la tarjeta se queda en 'Pagado'
                    updateCounters();
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                    revertCard(pendingDrop.itemEl, pendingDrop.fromCol, pendingDrop.oldIndex);
                    pendingDrop = null;
                    $('#modal-cierre-venta').fadeOut();
                    updateCounters();
                }
            }).catch(() => {
                alert('❌ Error de red. La operación fue cancelada.');
                revertCard(pendingDrop.itemEl, pendingDrop.fromCol, pendingDrop.oldIndex);
                pendingDrop = null;
                $('#modal-cierre-venta').fadeOut();
                updateCounters();
            }).finally(() => {
                btn.prop('disabled', false).text('Confirmar y Procesar Pago');
            });
        });

        // 3. Trigger manual (Mobile) para "Pagar" sin arrastrar
        $('#kb-col-emitida, #kb-col-pagado, #kb-col-por_enviar, #kb-col-despachado').on('click', '.trigger-mobile-pay', function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');
            const col = $(this).data('col');
            
            pendingDrop = { 
                quoteId: quoteId, 
                itemEl: $(this).closest('.kanban-card')[0], 
                fromCol: document.getElementById('kb-col-' + col), 
                toCol: document.getElementById('kb-col-pagado'), 
                oldIndex: $(this).closest('.kanban-card').index()
            };
            
            $('#form-super-pago')[0].reset();
            $('#sp-datos-envio-container, #box-agencia, #box-direccion').hide();
            $('#sp-quote-id').val(quoteId);
            
            // Ajuste estricto de Zona Horaria (Caracas / Local)
            const tzOffsetMobile = (new Date()).getTimezoneOffset() * 60000;
            const localISOTimeMobile = (new Date(Date.now() - tzOffsetMobile)).toISOString().slice(0, 10);
            $('#sp-fecha-pago').val(localISOTimeMobile);

            $('#modal-super-pago').fadeIn();
        });
		
        // NUEVO TRIGGER EXCLUSIVO ADMIN: DEVOLVER A PAGADO
        $('.kanban-board').on('click', '.trigger-reverse-to-paid', function(e) {
            e.preventDefault();
            const orderId = $(this).data('id');
            
            Swal.fire({
                title: '¿Revertir orden a "Pagado"?',
                html: `¿Hubo un error logístico?<br><span style="color:#d97706; font-size:13px;">La orden regresará a la columna Pagado.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, Revertir'
            }).then((result) => {
                if (result.isConfirmed) {
                    SuiteAPI.post('suite_reverse_to_paid', { order_id: orderId }).then(res => {
                        if (res.success) {
                            Swal.fire('¡Revertido!', 'La orden regresó a Pagado.', 'success');
                            if (typeof SuiteKanban !== 'undefined') SuiteKanban.loadBoard(); 
                        } else {
                            Swal.fire('Error', res.data.message || res.data, 'error');
                        }
                    }).catch(() => Swal.fire('Error', 'Fallo de conexión.', 'error'));
                }
            });
        });

        // 4. TRIGGER EXCLUSIVO ADMIN: LOGÍSTICA INVERSA
        $('#kb-col-despachado, .kanban-board').on('click', '.trigger-reverse-logistics', function(e) {
            e.preventDefault();

            const orderId = $(this).data('id');
            const seguro = confirm('⚠️ ¿Estás seguro de aplicar logística inversa? Esto reversará la comisión y devolverá la orden a "Pagado" para su reevaluación.');

            if (!seguro) return;

            const btn = $(this);
            btn.prop('disabled', true).text('⏳ Procesando...');

            SuiteAPI.post('suite_reverse_logistics', {
                order_id: orderId
            }).then(res => {
                if (res.success) {
                    alert('✅ ' + (res.data.message || 'Logística inversa aplicada con éxito.'));
                    
                    // Recargar el tablero si existe el método, de lo contrario recargar la página
                    if (typeof SuiteKanban !== 'undefined' && typeof SuiteKanban.loadBoard === 'function') {
                        SuiteKanban.loadBoard();
                    } else {
                        location.reload();
                    }
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                    btn.prop('disabled', false).html('🔙 Logística Inversa (Admin)');
                }
            }).catch(err => {
                alert('❌ Ocurrió un error de red al intentar aplicar la logística inversa.');
                btn.prop('disabled', false).html('🔙 Logística Inversa (Admin)');
            });
        });
		
		
		
		
		// 5. EVENTO SUBMIT DEL NUEVO SÚPER-MODAL (FASE 3)
        $('#form-super-pago').on('submit', function(e) {
            e.preventDefault();

            if (!pendingDrop) {
                alert("Error: No se encontró la tarjeta original. Recargue la página.");
                return;
            }

            // --- 🛡️ UX DE ÉLITE: ALERTA TEMPRANA DE PESO ---
            // Usamos un nombre de variable único (archivoValidacion) para no chocar con el código de abajo
            const archivoValidacion = $('#sp-comprobante')[0].files[0];
            const maxSizeBytes = 3.5 * 1024 * 1024; // 3.5 MB
            
            if (archivoValidacion && archivoValidacion.size > maxSizeBytes) {
                alert('❌ Error: El comprobante pesa demasiado. El límite estricto es de 3.5MB para garantizar el envío a Finanzas.\n\nPor favor, comprima la imagen o el PDF e intente de nuevo.');
                return; // 🛑 Detenemos la ejecución inmediatamente
            }
            // ------------------------------------------------

            // Prevención de doble envío
            const btnSubmit = $(this).find('button[type="submit"]');
            const originalText = btnSubmit.html();
            btnSubmit.prop('disabled', true).text('⏳ Subiendo y Cifrando...');

			
			
			
			
			
			
			
			
			
			
			
            // 1. Instancia de FormData
            let formData = new FormData();
			
			
			
			
			
            // 2. Enrutamiento y Seguridad
            formData.append('action', 'suite_process_super_pago');
            formData.append('nonce', suite_vars.nonce);
            formData.append('quote_id', $('#sp-quote-id').val());

            // 3. Sección A: Pago
            formData.append('forma_pago', $('#sp-forma-pago').val());
            formData.append('fecha_pago', $('#sp-fecha-pago').val());
            formData.append('monto_pagado', $('#sp-monto-pagado').val());
            formData.append('requiere_factura', $('#sp-factura').is(':checked') ? 1 : 0);
            formData.append('agente_retencion', $('#sp-retencion').is(':checked') ? 1 : 0);

            // Archivo Comprobante (CORRECCIÓN JQUERY a Nativo)
            const fileInput = $('#sp-comprobante')[0].files[0];
            if (fileInput) {
                formData.append('comprobante', fileInput);
            }

			
			
			
			
            // 4. Sección B: Logística
            formData.append('tipo_envio', $('#sp-tipo-envio').val());
            formData.append('sucursal_retiro', $('#sp-sucursal').val()); // NUEVA INYECCIÓN
            formData.append('agencia_envio', $('#sp-agencia-envio').val());
            formData.append('nombre_receptor', $('#sp-nombre-receptor').val());
            formData.append('rif_receptor', $('#sp-rif-receptor').val());
            formData.append('telefono_receptor', $('#sp-telefono-receptor').val());
            formData.append('direccion_envio', $('#sp-direccion-envio').val());

            // 5. Sección C: Prioridad
            formData.append('prioridad', $('#sp-prioridad').is(':checked') ? 1 : 0);

            // 6. Disparo AJAX crudo blindado (Multipart)
            $.ajax({
                url: suite_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false, 
                contentType: false, 
                dataType: 'json',
                success: function(res) {
                    try {
                        if (res && res.success) {
                            $('#modal-super-pago').fadeOut();
                            pendingDrop = null; 
                            alert('✅ ' + (res.data.message || 'Cierre de venta procesado con éxito.'));
                            location.reload(); // Recarga a prueba de balas para evitar desincronización
                        } else {
                            alert('❌ Error: ' + (res?.data?.message || res?.data || 'Desconocido'));
                            if (pendingDrop) revertCard(pendingDrop.itemEl, pendingDrop.fromCol, pendingDrop.oldIndex);
                            $('#modal-super-pago').fadeOut();
                            pendingDrop = null;
                            updateCounters();
                        }
                    } catch (error) {
                        console.error(error);
                        location.reload(); // Salvavidas: Si algo falla en JS, recarga la página.
                    }
                },
                error: function(xhr) {
                    console.error("AJAX Error:", xhr.responseText);
                    alert('⚠️ El archivo se procesó, actualizando tablero...');
                    location.reload(); 
                },
                complete: function() {
                    btnSubmit.prop('disabled', false).html(originalText);
                }
            });
        });
		
		
		// --- ACCIONES ADMIN: APROBAR O RECHAZAR PAGO ---
        
        // 1. APROBAR PAGO (Pasa a Por Enviar)
        $('.kanban-board').on('click', '.trigger-approve-payment', function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');
            const btn = $(this);
            
            if(!confirm('¿Confirmas que el dinero está en la cuenta? La orden pasará a Logística.')) return;
            
            btn.prop('disabled', true).text('⏳');
            updateOrderStatus(quoteId, 'por_enviar', null, null, null); // Reutilizamos tu función existente
        });

        // 2. RECHAZAR PAGO (Devuelve a Pendiente)
        $('.kanban-board').on('click', '.trigger-reject-payment', function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');
            const btn = $(this);
            
            const motivo = prompt('Escribe el motivo del rechazo (Ej: Captura falsa, Monto incompleto). La orden volverá a Pendiente:');
            if(motivo === null) return; // Canceló el prompt
            
            btn.prop('disabled', true).text('⏳');
            updateOrderStatus(quoteId, 'emitida', null, null, null); 
            // Opcional: Podrías hacer un AJAX especial que además borre los datos de pago en la BD y guarde el motivo.
        });

        // 3. VER DETALLES DEL PAGO Y COMPROBANTE
        $('.kanban-board').on('click', '.trigger-view-payment', function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');
            
            const btn = $(this); 
            const originalHtml = btn.html();
            btn.html('⏳').prop('disabled', true);

            SuiteAPI.post('suite_get_quote_details_ajax', { id: quoteId }).then(res => {
                btn.html(originalHtml).prop('disabled', false);
                if (res.success) {
                    const cot = res.data.cotizacion;
                    
                    // 1. Procesar la imagen o el PDF
                    let imgHtml = '';
                    if (cot.comprobante_pago_url) {
                        const fileUrl = cot.comprobante_pago_url;
                        if (fileUrl.toLowerCase().endsWith('.pdf')) {
                            imgHtml = `<a href="${fileUrl}" target="_blank" class="btn-modern-action" style="background:#dc2626; color:white; display:block; text-align:center; padding:10px; margin-bottom:15px; text-decoration:none;">📄 ABRIR PDF DEL COMPROBANTE</a>`;
                        } else {
                            imgHtml = `<a href="${fileUrl}" target="_blank"><img src="${fileUrl}" style="width:100%; max-height:300px; object-fit:contain; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:15px; background:#fff; cursor:zoom-in;" title="Clic para ampliar"></a>`;
                        }
                    } else {
                        imgHtml = `<div style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:5px; text-align:center; margin-bottom:15px; font-weight:bold;">⚠️ El vendedor no adjuntó archivo físico.</div>`;
                    }

                    // 2. Procesar tabla de datos (Añadidas Alertas Fiscales y Urgencia)
                    let fiscalHtml = '';
                    if (cot.requiere_factura == '1') fiscalHtml += '<span style="background:#dbeafe; color:#1d4ed8; border: 1px solid #bfdbfe; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:11px; margin-right:5px;">🧾 FACTURA FISCAL</span>';
                    if (cot.agente_retencion == '1') fiscalHtml += '<span style="background:#fef9c3; color:#a16207; border: 1px solid #fde047; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:11px;">✂️ AGENTE RETENCIÓN</span>';
                    if (!fiscalHtml) fiscalHtml = '<span style="color:#64748b; font-style:italic;">No solicitado</span>';

                    let detallesUrgencia = (cot.prioridad == '1') ? '<tr><td colspan="2" style="background:#fee2e2; color:#dc2626; font-weight:bold; text-align:center; padding:10px;">🚨 ESTA ORDEN ESTÁ MARCADA COMO URGENTE</td></tr>' : '';

                    let detailsHtml = `
                        ${imgHtml}
                        <table class="widefat striped" style="font-size:13px; border-collapse: collapse;">
                            <tbody>
                                ${detallesUrgencia}
                                <tr><td style="width:120px;"><strong>💰 Monto / Forma:</strong></td><td>${cot.forma_pago}</td></tr>
                                <tr><td><strong>📅 Fecha Pago:</strong></td><td>${cot.fecha_pago}</td></tr>
                                <tr><td><strong>⚖️ Req. Fiscales:</strong></td><td>${fiscalHtml}</td></tr>
                                <tr><td><strong>🚚 Logística:</strong></td><td>${cot.tipo_envio}</td></tr>
                                <tr><td><strong>📍 Destino:</strong></td><td>${(cot.direccion_envio || '').replace(/\n/g, '<br>')}</td></tr>
                            </tbody>
                        </table>
                    `;
                    
                    $('#vp-content').html(detailsHtml);
                    $('#modal-ver-pago').fadeIn();
                } else {
                    alert('❌ Error al cargar los detalles del pago.');
                }
            }).catch(() => {
                btn.html(originalHtml).prop('disabled', false);
                alert('❌ Error de red al consultar el pago.');
            });
        });

        // 4. Cerrar Modal de Ver Pago
        $('#close-ver-pago, #btn-cerrar-ver-pago').on('click', function() {
            $('#modal-ver-pago').fadeOut();
        });
		
    };
    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        loadBoard: function() {
            $('.kanban-column-body').empty().html('<div class="text-center text-gray-400 py-4">Cargando...</div>');

            SuiteAPI.post('suite_get_kanban_data').then(res => {
                if (res.success) {
                    const data = res.data;
                    $('.kanban-column-body').empty(); 
                    
                    const columnasPermitidas = ['emitida', 'pagado', 'por_enviar', 'despachado'];
                    columnasPermitidas.forEach(status => {
                        let colHtml = '';
                        if (data[status] && data[status].length > 0) {
                            data[status].forEach(order => {
                                colHtml += buildCardHTML(order);
                            });
                        }
                        $('#kb-col-' + status).html(colHtml);
                    });

                    updateCounters();
                } else {
                    alert('Error al cargar pedidos: ' + res.data);
                }
            });
        },

        init: function() {
            if (typeof Sortable === 'undefined') {
                console.error("SortableJS no está cargado.");
                return;
            }
            
            initSortableColumns();
            bindModalEvents();
            this.loadBoard();
        }
    };

})(jQuery);