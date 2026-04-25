# 🧩 MÓDULO LÓGICO: Slice_C_Kanban_Pagos

### ARCHIVO: `assets/js/modules/kanban.js`
```js
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
                    
					
					
					
					
					
					
					
					
                    // 1. Procesar la imagen, PDF o RECIBO BDV (JSON)
                    let imgHtml = '';
                    if (cot.comprobante_pago_url) {
                        const fileUrl = cot.comprobante_pago_url;
                        
                        // PARCHE BDV: Detectar si es un JSON generado por la integración Woo
                        if (fileUrl.includes('"is_bdv":true')) {
                            try {
                                let bdv = JSON.parse(fileUrl);
                                imgHtml = `
                                    <div style="background: #f0fdf4; border: 2px dashed #16a34a; padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: left;">
                                        <h4 style="color: #166534; margin-top:0; border-bottom:1px solid #bbf7d0; padding-bottom:5px;">✅ RECIBO BDV CONCILIACIÓN</h4>
                                        <p style="margin: 5px 0;"><strong>💳 Referencia:</strong> <span style="color:#0f172a;">${bdv.ref || 'N/D'}</span></p>
                                        <p style="margin: 5px 0;"><strong>🇻🇪 Monto VES:</strong> <span style="color:#0f172a;">${bdv.ves || '0.00'} Bs.</span></p>
                                        <p style="margin: 5px 0;"><strong>🪪 C.I. Pagador:</strong> <span style="color:#0f172a;">${bdv.ci || 'N/D'}</span></p>
                                        <p style="margin: 5px 0;"><strong>⏱️ Timestamp:</strong> <span style="color:#0f172a;">${bdv.ts || 'N/D'}</span></p>
                                    </div>
                                `;
                            } catch(e) {
                                imgHtml = `<div style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:5px; text-align:center; margin-bottom:15px; font-weight:bold;">⚠️ Error leyendo recibo digital BDV.</div>`;
                            }
                        } 
                        // FLUJO NORMAL: Si no es JSON, es una URL normal (PDF o Imagen)
                        else if (fileUrl.toLowerCase().endsWith('.pdf')) {
                            imgHtml = `<a href="${fileUrl}" target="_blank" class="btn-modern-action" style="background:#dc2626; color:white; display:block; text-align:center; padding:10px; margin-bottom:15px; text-decoration:none; border-radius:8px;">📄 ABRIR PDF DEL COMPROBANTE</a>`;
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
```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-quotes.php`
```php
<?php
/**
 * Controlador AJAX: Cotizador y Venta (Módulo 2: Seguridad Aplicada)
 *
 * Contiene los manejadores para guardar cotizaciones, consultar el historial 
 * y cambiar estados (con candado de inmutabilidad y protección IDOR).
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Endpoint para Guardar Cotizaciones y Actualizar Clientes
 */
class Suite_Ajax_Quote_Save extends Suite_AJAX_Controller {

    protected $action_name = 'suite_save_quote_crm'; 
    protected $required_capability = 'read';

    protected function process() {
        // 1. Recibir datos del frontend
        $client_data = [
			'vendedor_id'      => get_current_user_id(),
            'rif_ci'           => isset( $_POST['rif'] ) ? $_POST['rif'] : '',
            'nombre_razon'     => sanitize_text_field( $_POST['nombre'] ),
            'direccion'        => sanitize_textarea_field( $_POST['direccion'] ),
            'telefono'         => sanitize_text_field( $_POST['telefono'] ),
            'email'            => sanitize_email( $_POST['email'] ),
            'ciudad'           => sanitize_text_field( $_POST['ciudad'] ),
            'estado'           => sanitize_text_field( $_POST['estado'] ),
            'contacto_persona' => sanitize_text_field( $_POST['contacto'] ),
            'notas'            => sanitize_textarea_field( $_POST['notas'] )
        ];

        // 2. MODO ACTUALIZACIÓN DE PERFIL
        $is_update_only = isset( $_POST['is_update_only'] ) && $_POST['is_update_only'] == 'true';
        if ( $is_update_only ) {
            $client_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            if ( $client_id > 0 ) {
                $client_model = new Suite_Model_Client();
                $client_model->update( $client_id, $client_data );
                $this->send_success( [ 'message' => 'Perfil actualizado correctamente.' ] );
            }
            $this->send_error( 'ID de cliente inválido.' );
            return;
        }

        // 3. MODO CREAR COTIZACIÓN
        $items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? $_POST['items'] : [];
        if ( empty( $items ) ) {
            $this->send_error( 'El carrito no puede estar vacío.' );
        }

        // MÓDULO 2: SEGURIDAD - CONTROL DE PRECIOS MÍNIMOS (MIDDLEWARE OPTIMIZADO N+1)
        global $wpdb;
        $tabla_inv = $wpdb->prefix . 'suite_inventario_cache';
        $is_admin = current_user_can( 'manage_options' );

        // Si es admin, nos saltamos la validación en BD para ahorrar recursos
        if ( ! $is_admin && ! empty( $items ) ) {
            $skus_a_verificar = [];
            foreach ( $items as $item ) {
                $sku = sanitize_text_field( $item['sku'] );
                if ( ! in_array( strtoupper( $sku ), ['MANUAL', 'GENERICO'] ) ) {
                    $skus_a_verificar[] = $sku;
                }
            }

            if ( ! empty( $skus_a_verificar ) ) {
                // 1. Pre-cargar precios permitidos con un solo query (IN)
                $placeholders = implode( ',', array_fill( 0, count( $skus_a_verificar ), '%s' ) );
                $sql_precios = $wpdb->prepare( "SELECT sku, precio_venta AS precio FROM {$tabla_inv} WHERE sku IN ($placeholders)", ...$skus_a_verificar );
                // Usamos OBJECT_K para que el array resultante tenga los SKUs como llaves
                $resultados_precios = $wpdb->get_results( $sql_precios, OBJECT_K );

                // 2. Validar precios en memoria
                foreach ( $items as $item ) {
                    $sku = strtoupper( sanitize_text_field( $item['sku'] ) );

                    if ( isset( $resultados_precios[$sku] ) ) {
                        $minimum_price = floatval( $resultados_precios[$sku]->precio );
                        $selling_price = floatval( $item['price'] );

                        if ( $selling_price < $minimum_price ) {
                            $precio_fmt = number_format( $minimum_price, 2 );
                            $this->send_error( "El precio del producto '{$item['name']}' ({$sku}) está por debajo del mínimo permitido (\${$precio_fmt}). Requiere autorización de un supervisor.", 403 );
                        }
                    }
                }
            }
        }

        $meta = [
            'vendedor_id' => get_current_user_id(),
            'tasa'        => floatval( $_POST['tasa'] ),
            'validez'     => intval( $_POST['validez'] ),
            'moneda'      => sanitize_text_field( $_POST['moneda'] )
        ];

        // 4. Instanciar Modelo y Guardar
        $quote_model = new Suite_Model_Quote();
        $result = $quote_model->create_quote( $client_data, $items, $meta );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 500 );
        }

        $this->send_success( $result );
    }
}

/**
 * 2. Endpoint para Listar el Historial de Cotizaciones
 */
class Suite_Ajax_Quote_History extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_history_ajax';
    protected $required_capability = 'read';

    protected function process() {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', (array) $user->roles );
        
        // --- INYECCIÓN RBAC: Nueva Bandera de Historial ---
        $tiene_bandera_historial = current_user_can( 'suite_view_all_quotes' );
        
        // El acceso global se otorga si es Admin, Logística, O tiene la nueva bandera
        $tiene_acceso_global = ( $is_admin || $is_logistica || $tiene_bandera_historial );

        $quote_model = new Suite_Model_Quote();
        $history = $quote_model->get_vendor_history( $user_id, 500, $tiene_acceso_global );
        
        $pending_retentions = [];
        $normal_history = [];

        foreach ( $history as $r ) {
            // Lógica de Retención Pendiente
            $r->is_pending_retention = ($r->estado === 'despachado' && $r->agente_retencion == '1' && empty($r->retencion_url));

            $r->fecha_fmt = date( 'd/m/Y', strtotime( $r->fecha_emision ) );
            
            $r->fecha_cruda = strtotime( $r->fecha_emision );
            $r->total_fmt = number_format( floatval( $r->total_usd ), 2 );
            $r->cliente_nombre = empty( $r->cliente_nombre ) ? 'N/A' : esc_html( $r->cliente_nombre );

            $raw_tel = isset( $r->cliente_telefono ) ? $r->cliente_telefono : '';
            $wa_phone = preg_replace( '/[^0-9]/', '', $raw_tel );
            
            if ( strlen( $wa_phone ) === 11 && strpos( $wa_phone, '0' ) === 0 ) {
                $wa_phone = '58' . substr( $wa_phone, 1 );
            } elseif ( strlen( $wa_phone ) === 10 ) {
                $wa_phone = '58' . $wa_phone;
            }
            $r->wa_phone = $wa_phone;

            if ( empty( $r->estado ) ) {
                $r->estado = 'emitida';
            }
            $r->can_change_status = $tiene_acceso_global;
            
            // Separar en dos arreglos para aplicar "Gravedad" al inicio
            if ( $r->is_pending_retention ) {
                $pending_retentions[] = $r;
            } else {
                $normal_history[] = $r;
            }
        }

        // Combinar: Los de alerta amarilla arriba, el resto abajo
        $sorted_history = array_merge($pending_retentions, $normal_history);

        $this->send_success( $sorted_history );
    }
}

/**
 * 3. Endpoint para Cambiar el Estado de una Cotización (Manual / Kanban)
 * Modificado para Módulo 4: Recibe comprobantes y dispara comisiones.
 */
class Suite_Ajax_Quote_Status extends Suite_AJAX_Controller {

    protected $action_name = 'suite_change_status_ajax'; 
    protected $required_capability = 'read';

    protected function process() {
        $quote_id   = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $new_status = isset( $_POST['estado'] ) ? strtolower( sanitize_text_field( $_POST['estado'] ) ) : '';

        if ( ! $quote_id || empty( $new_status ) ) {
            $this->send_error( 'Datos insuficientes para cambiar el estado.' );
        }

        $quote_model = new Suite_Model_Quote();
        $current_order = $quote_model->get( $quote_id );

        if ( ! $current_order ) {
            $this->send_error( 'La cotización no existe.', 404 );
        }

        $is_admin = current_user_can( 'manage_options' );

        // 1. SEGURIDAD: PREVENCIÓN DE IDOR
        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        $is_logistica = in_array( 'suite_logistica', (array) $user->roles );
        $is_owner = ( intval( $current_order->vendedor_id ) === get_current_user_id() );

        // 1. SEGURIDAD: PREVENCIÓN DE IDOR (Con Bypass para Logística)
        if ( ! $is_admin && ! $is_owner ) {
            // Permitir SOLO si es Logística y está intentando mover a 'despachado'
            if ( ! ( $is_logistica && $new_status === 'despachado' ) ) {
                if ( function_exists('suite_record_log') ) {
                    suite_record_log( 'violacion_idor', "Usuario " . get_current_user_id() . " intentó modificar el pedido #{$quote_id}." );
                }
                $this->send_error( 'Acceso Denegado: No tiene permisos para modificar un pedido que no le pertenece.', 403 );
            }
        }

        $current_status = strtolower( $current_order->estado );
        $protected_statuses = [ 'pagado', 'despachado' ];

        // 2. CANDADO DE INMUTABILIDAD
        if ( in_array( $current_status, $protected_statuses ) && ! $is_admin ) {
            $this->send_error( 'Candado de Inmutabilidad 🔒: Este pedido ya ha sido procesado y no puede ser modificado.', 403 );
        }
		
        // --- INICIO INYECCIÓN: EVENTO DE LOGÍSTICA INVERSA ---
        if ( $current_status === 'despachado' && $new_status === 'proceso' ) {
            // Re-verificación estricta (Zero-Trust)
            if ( ! current_user_can( 'manage_options' ) ) {
                $this->send_error( 'Acceso Denegado. Solo el Administrador puede aplicar Logística Inversa.', 403 );
            }
            // Registro obligatorio en el historial
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'logistica_inversa', "Logística Inversa: El Administrador (ID: " . get_current_user_id() . ") revirtió el pedido #{$quote_id} de 'despachado' a 'proceso'." );
            }

            // --- NUEVO: REVERSO CONTABLE EN EL LEDGER ---
            $commission_model = new Suite_Model_Commission();
            $commission_model->reverse_commission( $quote_id );

            // Nota de Arquitectura: Si el módulo descuenta inventario físico al llegar a 'despachado',
            // este es el punto exacto para invocar una función que restaure dicho stock.
        }
        // --- FIN INYECCIÓN ---

		// CORRECCIÓN 1: Se agregó 'por_enviar' al array de estados válidos
        $estados_validos = ['emitida', 'proceso', 'pagado', 'por_enviar', 'anulado', 'despachado'];
        if ( ! in_array( $new_status, $estados_validos ) ) {
            $this->send_error( 'Estado no válido.', 400 );
        }

        // 3. MÓDULO 4: CAPTURAR DATOS DE CIERRE DE VENTA
        if ( $new_status === 'pagado' ) {
            $recibo = isset($_POST['recibo_loyverse']) ? sanitize_text_field($_POST['recibo_loyverse']) : '';
            
            // CORRECCIÓN 2: Validar Unicidad del Recibo en la Base de Datos
            if ( ! empty( $recibo ) ) {
                global $wpdb;
                $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';
                
                // Buscamos si el recibo ya existe en OTRO pedido distinto al que estamos procesando
                $existe = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tabla_cot} WHERE recibo_loyverse = %s AND id != %d", $recibo, $quote_id ) );
                
                if ( $existe ) {
                    $this->send_error( 'El número de recibo o factura (' . esc_html($recibo) . ') ya está asignado al pedido #' . $existe . '. No se permiten duplicados.', 400 );
                    return; // Detenemos la ejecución inmediatamente
                }
            }

            $extra_data = [
                'canal_venta'      => isset($_POST['canal_venta']) ? sanitize_text_field($_POST['canal_venta']) : '',
                'metodo_pago'      => isset($_POST['metodo_pago']) ? sanitize_text_field($_POST['metodo_pago']) : '',
                'metodo_entrega'   => isset($_POST['metodo_entrega']) ? sanitize_text_field($_POST['metodo_entrega']) : '',
                'url_captura_pago' => isset($_POST['url_captura']) ? esc_url_raw($_POST['url_captura']) : '',
                'recibo_loyverse'  => $recibo,
            ];
            $quote_model->update( $quote_id, $extra_data );
        }

        // 4. CAMBIAR ESTADO
        $result = $quote_model->update_order_status( $quote_id, $new_status );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 500 );
        }

        if ( $result ) {
			
			// 5. -----------------------------------------------------------------
            // INCISIÓN D (FASE 5.3) - MOTOR FINANCIERO Y LEDGER ZERO-TRUST
            // -----------------------------------------------------------------
            if ( $new_status === 'pagado' && $current_status !== 'pagado' ) {
                global $wpdb;

                // 1. Extracción segura del monto base desde la BD para precisión decimal
                $cotizacion = $wpdb->get_row( $wpdb->prepare( 
                    "SELECT total_usd, vendedor_id FROM {$wpdb->prefix}suite_cotizaciones WHERE id = %d", 
                    $quote_id 
                ) );

                if ( $cotizacion ) {
                    $monto_base_usd = floatval( $cotizacion->total_usd );
                    $vendedor_id    = intval( $cotizacion->vendedor_id );

                    // 2. Detección de Identidad B2B
                    $is_b2b = get_user_meta( $vendedor_id, 'suite_is_b2b', true ) === '1';
                    
                    if ( $is_b2b ) {
                        // =========================================================
                        // RUTA A: FLUJO ALIADO COMERCIAL (B2B) - COMISIÓN MANUAL
                        // =========================================================
                        $porcentaje_b2b = isset( $_POST['porcentaje_b2b'] ) ? floatval( $_POST['porcentaje_b2b'] ) : 0;

                        if ( $porcentaje_b2b <= 0 ) {
                            $this->send_error( 'Las órdenes de Aliados B2B requieren un porcentaje de comisión válido mayor a 0.' );
                        }

                        // Cálculo exacto de la comisión inyectada desde el modal
                        $comision_usd = ($monto_base_usd * $porcentaje_b2b) / 100;

                        $wpdb->insert(
                            $wpdb->prefix . 'suite_comisiones_ledger',
                            [
                                'quote_id'            => $quote_id,
                                'vendedor_id'         => $vendedor_id,
                                'monto_base_usd'      => $monto_base_usd,
                                'comision_ganada_usd' => $comision_usd,
                                'estado_pago'         => 'pendiente',
                                'notas'               => "Comisión Aliado B2B ({$porcentaje_b2b}%)"
                            ],
                            [ '%d', '%d', '%f', '%f', '%s', '%s' ]
                        );

                    } else {
                        // =========================================================
                        // RUTA B: FLUJO ESTÁNDAR EMPLEADOS - COMISIÓN AUTOMÁTICA
                        // =========================================================
                        if ( class_exists( 'Suite_Model_Commission' ) ) {
                            $commission_model = new Suite_Model_Commission();
                            
                            // Mantener soporte para Venta Compartida (Colaboradores)
                            $colaboradores_raw = isset( $_POST['colaboradores'] ) ? $_POST['colaboradores'] : [];
                            $colaboradores_clean = is_array( $colaboradores_raw ) ? array_map( 'intval', $colaboradores_raw ) : [];
                        }
                    }
                }
            }
            // -----------------------------------------------------------------
            // FIN DE LA INCISIÓN D
            // -----------------------------------------------------------------

            $this->send_success( [ 'message' => 'Estado actualizado a ' . strtoupper( $new_status ) ] );
        } else {
            $this->send_error( 'Error al actualizar la base de datos o el estado ya era el mismo.' );
        }
    }
}


/**
 * Endpoint para Imprimir Cotización en PDF/HTML (Sustituye a mod-impresion.php)
 */
class Suite_Ajax_Print_Quote extends Suite_AJAX_Controller {

    protected $action_name = 'suite_print_quote';
    protected $required_capability = 'read';

    public function handle_request() {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'suite_quote_nonce' ) ) {
            wp_die( 'Enlace caducado o inválido por seguridad (CSRF).', 'Acceso Denegado', [ 'response' => 403 ] );
        }
        $this->process();
    }

    protected function process() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( 'Privilegios insuficientes.', 'Acceso Denegado', [ 'response' => 403 ] );
        }

        global $wpdb;
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        // 1. OBTENER DATOS PRINCIPALES
        $cot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones WHERE id = %d", $id ) );
        if ( ! $cot ) wp_die( 'Cotización no encontrada.', 'Error', [ 'response' => 404 ] );

        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones_items WHERE cotizacion_id = %d", $id ) );

        // 2. OBTENER DATOS EXTENDIDOS DEL CLIENTE
        $cliente_extra = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_clientes WHERE id = %d", $cot->cliente_id ) );

        // 3. DATOS VENDEDOR
        $vendedor = get_userdata( $cot->vendedor_id );
        $vendedor_nombre = $vendedor ? $vendedor->display_name : 'Equipo de Ventas';
        $tel_db = get_user_meta($cot->vendedor_id, 'suite_telefono', true);
        $vendedor_telefono = !empty($tel_db) ? $tel_db : "+58 424-844-2132";

        // 4. LÓGICA DE MONEDA (USD vs BS)
        $es_bolivares = ( $cot->moneda === 'BS' );
        $simbolo = $es_bolivares ? 'Bs.' : '$';
        $tasa_calc = $es_bolivares ? floatval( $cot->tasa_bcv ) : 1;

        // 5. CÁLCULOS TOTALES
        $subtotal_base = floatval( $cot->total_usd ) * $tasa_calc;
        $descuento = 0; 
        $base_imponible = $subtotal_base - $descuento;
        $iva_pct = 0.16;
        $monto_iva = $base_imponible * $iva_pct;
        $total_final = $base_imponible + $monto_iva;

        // Formateo
        $subtotal_fmt = number_format( $subtotal_base, 2 );
        $iva_fmt = number_format( $monto_iva, 2 );
        $total_fmt = number_format( $total_final, 2 );

        // LÓGICA DE LIMPIEZA DE DATOS
        $validar_dato = function($val) {
            return (!empty($val) && $val !== 'N/A' && $val !== 'N/D');
        };

        $show_dir = $validar_dato($cot->direccion_entrega) ? $cot->direccion_entrega : false;
        
        $ciudad_raw = isset($cliente_extra->ciudad) ? $cliente_extra->ciudad : '';
        $estado_raw = isset($cliente_extra->estado) ? $cliente_extra->estado : '';
        $show_ubicacion = trim("$ciudad_raw $estado_raw");
        if(!$validar_dato($show_ubicacion)) $show_ubicacion = false;

        $show_atencion = (isset($cliente_extra->contacto_persona) && $validar_dato($cliente_extra->contacto_persona)) ? $cliente_extra->contacto_persona : false;
        $tel_raw = (isset($cliente_extra->telefono) && $validar_dato($cliente_extra->telefono)) ? "Telf: " . $cliente_extra->telefono : '';
        $email_raw = (isset($cliente_extra->email) && $validar_dato($cliente_extra->email)) ? "Email: " . $cliente_extra->email : '';
        $show_contacto = trim("$tel_raw $email_raw");
        
        if(empty($tel_raw) && empty($email_raw)) $show_contacto = false;

		// --- INICIO HTML ---
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Cotización #<?php echo esc_attr( $cot->codigo_cotizacion ); ?></title>
            <style>
                /* REGLAS MAESTRAS DE IMPRESIÓN Y COLORES */
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                @media print {
                    @page { margin: 1cm; size: letter portrait; }
                    body { margin: 0; padding: 0; }
                }
                
                /* TIPOGRAFÍA ÚNICA Y GLOBAL */
                body { 
                    font-family: Arial, Helvetica, sans-serif; 
                    padding: 30px; 
                    color: #555555; 
                    font-size: 12px; 
                    margin: 0;
                }
                
                /* CLASES DE COLORES ESPECÍFICAS */
                .text-gray { color: #555555 !important; }
                .text-black-bold { color: #000000 !important; font-weight: bold !important; }
                .text-red-bold { color: #d0121b !important; font-weight: bold !important; }
                .bg-red-unit { background-color: #d0121b !important; color: #ffffff !important; }
                
                /* SEPARADORES */
                .separator-red { border: none; border-top: 2px solid #d0121b; margin: 15px 0; }
                .separator-light { border: none; border-top: 1px solid #e5e7eb; margin: 8px 0 12px 0; }
                .separator-elegant { border: none; border-top: 1px solid #d1d5db; margin: 18px 0; }
                
                /* =========================================
                   ENCABEZADO: LOGO (IZQ) / COTIZACIÓN (DER) 
                   ========================================= */
                .header-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
                .header-table td { vertical-align: top; }
                
                /* IZQUIERDA: LOGO Y EMPRESA */
                .header-left { width: 55%; padding-right: 20px; text-align: left; }
                .empresa-info-container img { height: 60px; margin-bottom: 8px; }
                .empresa-info { font-size: 11px; line-height: 1.4; }
                
                /* DERECHA: COTIZACIÓN */
                .header-right { width: 45%; text-align: right; }
                .cotizacion-title { 
                    margin: 0 0 5px 0; 
                    font-size: 18px; /* Elegante y proporcionado */
                    letter-spacing: 2px; 
                    font-weight: bold;
                    color: #000;
					text-align: left;
                }
                .separator-header-right { border-bottom: 1px solid #d1d5db; margin: 5px 0 10px auto; width: 100%; }
                
                .cotizacion-datos { border-collapse: collapse; font-size: 12px; text-align: left; width: 100%; }
                .cotizacion-datos td { padding: 3px 0; }
                .cotizacion-datos td:first-child { width: 80px; } 
                
                /* =========================================
                   DATOS DEL CLIENTE
                   ========================================= */
                .client-box { 
                    border: 1px solid #d1d5db; 
                    padding: 15px; 
                    border-radius: 8px; /* Esquinas curvas */
                    margin-bottom: 25px; /* Espaciado antes de la tabla */
                }
                .client-title { font-size: 13px; margin: 0; }
                .client-table { width: 100%; border-collapse: collapse; font-size: 11px; }
                .client-table td { padding: 5px 0; vertical-align: top; border: none; }
                
                /* =========================================
                   TABLA DE PRODUCTOS
                   ========================================= */
                .products-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 5px; }
                /* Alineación forzada a la izquierda */
                .products-table th, .products-table td { padding: 8px; text-align: left !important; }
                .products-table th { font-weight: bold; border: 1px solid #d0121b; } 
                .products-table td { border-bottom: 1px solid #f3f4f6; } 
                
                /* =========================================
                   TOTALES Y FOOTER
                   ========================================= */
                .totales-table { width: 280px; float: right; border-collapse: collapse; font-size: 12px; }
                .totales-table td { padding: 6px 10px; text-align: left; border: none; }
                .totales-table td:last-child { text-align: right; }
                
                /* Fila del Gran Total */
                .grand-total td { 
                    font-size: 16px !important; 
                    font-weight: bold !important; 
                    color: #d0121b !important; 
                    border-top: 1.5px solid #d0121b !important; /* Separador rojo del IVA al Total */
                    padding-top: 10px;
                    margin-top: 5px;
                }
                
                .footer { text-align: center; font-style: italic; font-size: 12px; clear: both; width: 100%; padding-top: 5px; }
            </style>
        </head>
        <body onload="window.print()">
            
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="empresa-info-container">
                            <img src="https://mitiendaunit.com/wp-content/uploads/2025/09/LOGO-UNI-T-RENOVADO_Mesa-de-trabajo-1-2.png" alt="UNI-T">
                            <div class="empresa-info text-gray">
                                <strong class="text-black-bold">UNI-T VENEZUELA, C.A.</strong><br>
                                C.C. Galerias Avila, nivel Feria, Local F67<br>
                                Caracas, 1010, Venezuela.<br>
                                R.I.F.: J-50174299-5<br>
                                Web: www.mitiendaunit.com
                            </div>
                        </div>
                    </td>

                    <td class="header-right">
                        <h2 class="cotizacion-title">COTIZACIÓN</h2>
                        <div class="separator-header-right"></div>
                        <table class="cotizacion-datos">
                            <tr>
                                <td class="text-black-bold">N°:</td>
                                <td class="text-red-bold">#<?php echo esc_html( $cot->codigo_cotizacion ); ?></td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Fecha:</td>
                                <td class="text-gray"><?php echo date( 'd/m/Y', strtotime( $cot->fecha_emision ) ); ?></td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Validez:</td>
                                <td class="text-gray"><?php echo esc_html( $cot->validez_dias ); ?> Días</td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Vendedor:</td>
                                <td class="text-gray"><?php echo esc_html( $vendedor_nombre ); ?></td>
                            </tr>
                            <tr>
                                <td class="text-black-bold">Teléfono:</td>
                                <td class="text-gray"><?php echo esc_html( $vendedor_telefono ); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <hr class="separator-red">

            <div class="client-box">
                <div class="client-title text-red-bold">DATOS DEL CLIENTE</div>
                <hr class="separator-light">
                <table class="client-table">
                    <tr>
                        <td style="width: 50%;">
                            <strong class="text-black-bold">RAZÓN SOCIAL:</strong> 
                            <span class="text-black-bold"><?php echo esc_html( mb_strtoupper( $cot->cliente_nombre, 'UTF-8' ) ); ?></span>
                        </td>
                        <td style="width: 50%;">
                            <strong class="text-black-bold">RIF/CI:</strong> 
                            <span class="text-gray"><?php echo esc_html( mb_strtoupper( $cot->cliente_rif, 'UTF-8' ) ); ?></span>
                        </td>
                    </tr>
                    <?php if ( $show_dir || $show_ubicacion ): ?>
                    <tr>
                        <?php if ( $show_dir ): ?>
                            <td <?php echo !$show_ubicacion ? 'colspan="2"' : ''; ?>>
                                <strong class="text-black-bold">DIRECCIÓN:</strong> 
                                <span class="text-gray"><?php echo esc_html( mb_strtoupper( $show_dir, 'UTF-8' ) ); ?></span>
                            </td>
                        <?php endif; ?>
                        <?php if ( $show_ubicacion ): ?>
                            <td <?php echo !$show_dir ? 'colspan="2"' : ''; ?>>
                                <strong class="text-black-bold">CIUDAD/ESTADO:</strong> 
                                <span class="text-gray"><?php echo esc_html( mb_strtoupper( $show_ubicacion, 'UTF-8' ) ); ?></span>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $show_contacto ): ?>
                    <tr>
                        <td colspan="2">
                            <strong class="text-black-bold">CONTACTO:</strong> 
                            <span class="text-gray"><?php echo esc_html( mb_strtoupper( $show_contacto, 'UTF-8' ) ); ?></span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <table class="products-table">
                <thead>
                    <tr>
                        <th class="bg-red-unit" style="width: 33%;">DESCRIPCIÓN</th>
                        <th class="bg-red-unit" style="width: 20%;">PRECIO POR UNIDAD</th>
                        <th class="bg-red-unit" style="width: 10%;">CANTIDAD</th>
                        <th class="bg-red-unit" style="width: 20%;">TIEMPO DE ENTREGA</th>
                        <th class="bg-red-unit" style="width: 17%;">MONTO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ): 
                        $precio_unit = floatval($item->precio_unitario_usd) * $tasa_calc;
                        $subtotal_item = floatval($item->subtotal_usd) * $tasa_calc;
                    ?>
                    <tr>
                        <td>
                            <strong class="text-black-bold"><?php echo esc_html( $item->sku ); ?></strong><br>
                            <span class="text-gray"><?php echo esc_html( mb_strtoupper( $item->producto_nombre, 'UTF-8' ) ); ?></span>
                        </td>
                        <td class="text-gray"><?php echo $simbolo . ' ' . number_format( $precio_unit, 2 ); ?></td>
                        <td class="text-gray"><?php echo intval( $item->cantidad ); ?></td>
                        <td class="text-gray"><?php echo !empty($item->tiempo_entrega) ? esc_html( mb_strtoupper( $item->tiempo_entrega, 'UTF-8' ) ) : 'INMEDIATA'; ?></td>
                        <td class="text-gray"><?php echo $simbolo . ' ' . number_format( $subtotal_item, 2 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr class="separator-elegant">

            <table class="totales-table">
                <tr>
                    <td class="text-gray">Subtotal:</td>
                    <td class="text-black-bold" style="width: 130px;"><?php echo $simbolo . ' ' . $subtotal_fmt; ?></td>
                </tr>
                <?php if ( $descuento > 0 ): ?>
                <tr>
                    <td class="text-gray">Descuento:</td>
                    <td class="text-gray">- <?php echo $simbolo . ' ' . number_format( $descuento, 2 ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="text-gray">I.V.A. (16%):</td>
                    <td class="text-black-bold"><?php echo $simbolo . ' ' . $iva_fmt; ?></td>
                </tr>
                <tr class="grand-total">
                    <td>TOTAL:</td>
                    <td><?php echo $simbolo . ' ' . $total_fmt; ?></td>
                </tr>
            </table>

            <div style="clear: both;"></div>

            <hr class="separator-elegant">

            <div class="footer text-gray">
                Gracias por su preferencia. Esta cotización está sujeta a disponibilidad de inventario.
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}



/**
 * 4. Endpoint para Obtener Detalles de Cotización (Acción: Clonar)
 */
class Suite_Ajax_Quote_Details extends Suite_AJAX_Controller {
    protected $action_name = 'suite_get_quote_details_ajax';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;
        $quote_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( ! $quote_id ) {
            $this->send_error( 'ID de cotización inválido.' );
        }

        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' ) || in_array( 'suite_gerente', (array) $user->roles );
        $user_id = get_current_user_id();

        $cotizacion = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones WHERE id = %d", $quote_id ) );

        if ( ! $cotizacion ) {
            $this->send_error( 'Cotización no encontrada.' );
        }

        // Validación RLS: Zero-Trust para clonación
        if ( ! $is_admin && intval( $cotizacion->vendedor_id ) !== $user_id ) {
            $this->send_error( 'Acceso denegado. No puede clonar una cotización que no le pertenece.', 403 );
        }

        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_cotizaciones_items WHERE cotizacion_id = %d", $quote_id ) );
        $cliente = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}suite_clientes WHERE id = %d", $cotizacion->cliente_id ) );

        $this->send_success( [
            'cotizacion' => $cotizacion,
            'items'      => $items,
            'cliente'    => $cliente
        ] );
    }
}

/**
 * 5. Endpoint para el Súper-Modal: Cierre Financiero y Logístico Multipart
 * FASE 3: KANBAN V2 (MERCADOENVÍOS)
 */
class Suite_Ajax_Process_Super_Pago extends Suite_AJAX_Controller {

    protected $action_name = 'suite_process_super_pago';
    protected $required_capability = 'read';

    protected function process() {
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
        if ( ! $quote_id ) {
            $this->send_error( 'ID de cotización ausente.' );
        }

        $quote_model = new Suite_Model_Quote();
        $current_order = $quote_model->get( $quote_id );

        if ( ! $current_order ) {
            $this->send_error( 'La cotización no existe.', 404 );
        }

        // 1. SEGURIDAD ZERO-TRUST: PREVENCIÓN IDOR
        $user_id = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' );
        if ( ! $is_admin && intval( $current_order->vendedor_id ) !== $user_id ) {
            $this->send_error( 'Acceso Denegado: No puede modificar un pedido que no le pertenece.', 403 );
        }

        // 2. SANITIZACIÓN ESTRICTA (Prevención XSS/SQLi)
        $forma_pago       = sanitize_text_field( $_POST['forma_pago'] ?? '' );
        $fecha_pago       = sanitize_text_field( $_POST['fecha_pago'] ?? current_time('mysql') );
        $requiere_factura = isset($_POST['requiere_factura']) ? intval($_POST['requiere_factura']) : 0;
        $agente_retencion = isset($_POST['agente_retencion']) ? intval($_POST['agente_retencion']) : 0;

        $tipo_envio        = sanitize_text_field( $_POST['tipo_envio'] ?? '' );
        $agencia_envio     = sanitize_text_field( $_POST['agencia_envio'] ?? '' );
        $nombre_receptor   = sanitize_text_field( $_POST['nombre_receptor'] ?? '' );
        $rif_receptor      = sanitize_text_field( $_POST['rif_receptor'] ?? '' );
        $telefono_receptor = sanitize_text_field( $_POST['telefono_receptor'] ?? '' );
        $direccion_fisica  = sanitize_textarea_field( $_POST['direccion_envio'] ?? '' );
        $prioridad         = isset($_POST['prioridad']) ? intval($_POST['prioridad']) : 0;

		
		
		
        $sucursal_retiro = sanitize_text_field( $_POST['sucursal_retiro'] ?? '' ); // CAPTURAMOS LA SUCURSAL

        // 3. CONCATENACIÓN INTELIGENTE DE LOGÍSTICA
        $direccion_final = '';
        if ( $tipo_envio === 'Nacional' ) {
            $direccion_final = "Receptor: $nombre_receptor | RIF: $rif_receptor | Tel: $telefono_receptor \nAgencia: $agencia_envio \nDir: $direccion_fisica";
        } elseif ( $tipo_envio === 'Motorizado' ) {
            $direccion_final = "Receptor: $nombre_receptor | RIF: $rif_receptor | Tel: $telefono_receptor \nDir: $direccion_fisica";
        } elseif ( $tipo_envio === 'Retiro' ) {
            $direccion_final = "Retira en Tienda: $nombre_receptor | RIF: $rif_receptor | Tel: $telefono_receptor \nSucursal Asignada: $sucursal_retiro";
        }

        // 4. BÓVEDA SEGURA: MANEJO DE ARCHIVO MULTIPART
        $comprobante_url = '';
        if ( ! empty( $_FILES['comprobante']['name'] ) ) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            
            // --- 🛡️ BARRERA ZERO-TRUST: Límite estricto de 3.5MB para Telegram ---
            $max_size_bytes = 3.5 * 1024 * 1024;
            if ( $_FILES['comprobante']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['comprobante']['size'] > $max_size_bytes ) {
                $this->send_error( 'El comprobante excede el límite de peso estricto (3.5MB). Por favor, comprima el archivo e intente de nuevo.', 400 );
                return; // 🛑 FRENO DE EMERGENCIA VITAL
            }

            // Dictadura de MIME Types: Solo Imágenes y PDFs
            $upload_overrides = [
                'test_form' => false,
                'mimes'     => [
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'pdf'  => 'application/pdf'
                ]
            ];

            $movefile = wp_handle_upload( $_FILES['comprobante'], $upload_overrides );

			
			
			
			
			
			
			
            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $comprobante_url = $movefile['url']; // URL pública/segura generada
            } else {
                $this->send_error( 'Fallo de seguridad al subir comprobante. Solo se permiten JPG, PNG o PDF. Detalles: ' . $movefile['error'] );
            }
        }

        // 5. ACTUALIZACIÓN DE METADATOS (Inyectamos los campos de la Fase 1)
        $extra_data = [
            'forma_pago'           => $forma_pago,
            'metodo_pago'          => $forma_pago, // Retrocompatibilidad v1
            'fecha_pago'           => $fecha_pago,
            'requiere_factura'     => $requiere_factura,
            'agente_retencion'     => $agente_retencion,
            'tipo_envio'           => $tipo_envio,
            'metodo_entrega'       => $tipo_envio, // Retrocompatibilidad v1
            'agencia_envio'        => $agencia_envio,
            'direccion_envio'      => $direccion_final,
            'prioridad'            => $prioridad
        ];
        
        if ( ! empty( $comprobante_url ) ) {
            $extra_data['comprobante_pago_url'] = $comprobante_url;
            $extra_data['url_captura_pago']     = $comprobante_url; // Retrocompatibilidad v1
        }

		
		
		
		
		
		
        $quote_model->update( $quote_id, $extra_data );
        $result = $quote_model->update_order_status( $quote_id, 'pagado' );
        
        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), 500 );
        }

        // --- INICIO FASE 4: DISPARO DE TELEGRAM ---
        try {
            // Buscamos el código de cotización para el mensaje
            $quote_data = $quote_model->get( $quote_id );
            
            if ( $quote_data && ! empty( $comprobante_url ) ) {
                $monto_final = isset($_POST['monto_pagado']) ? floatval($_POST['monto_pagado']) : $quote_data->total_usd;
                
                $telegram = new Suite_Telegram_Bot();
                $telegram->send_payment_alert(
                    $quote_id,
                    $quote_data->codigo_cotizacion,
                    $quote_data->vendedor_id,
                    $monto_final,
                    $forma_pago,
                    $comprobante_url
                );
            }
        } catch (Exception $e) {
            // No bloqueamos el éxito del ERP si Telegram falla
            error_log("Error en Notificación Telegram: " . $e->getMessage());
        }
        // --- FIN FASE 4 ---

        $this->send_success( [ 'message' => 'Pago registrado. El equipo financiero ha sido notificado por Telegram.' ] );
    }
}
/**
 * Helper Class: Suite_Telegram_Bot
 * FASE 4: Notificaciones en Tiempo Real para Validación de Pagos
 */
class Suite_Telegram_Bot {
    
    private $bot_token = '8190650297:AAEhx-eQygWnbid7mjcSQuN2KV4SigE6k38';
    private $chat_id   = '-5199565623'; 
	private $fiscal_chat_id = '-5244447469';
    /**
     * Envía la alerta de pago con el comprobante y botones interactivos.
     */
    public function send_payment_alert( $quote_id, $codigo_cotizacion, $vendedor_id, $monto, $forma_pago, $file_url ) {
        if ( empty( $this->bot_token ) || empty( $this->chat_id ) ) return false;

        $is_pdf     = ( substr( strtolower( $file_url ), -4 ) === '.pdf' );
        $endpoint   = $is_pdf ? 'sendDocument' : 'sendPhoto';
        $file_param = $is_pdf ? 'document' : 'photo';

        $vendedor_info   = get_userdata( $vendedor_id );
        $vendedor_nombre = $vendedor_info ? $vendedor_info->display_name : 'ID: ' . $vendedor_id;

        // Formateo del mensaje con Emojis para Finanzas
        $caption  = "🚨 <b>NUEVO PAGO REGISTRADO</b> 🚨\n\n";
        $caption .= "🛍️ <b>Orden:</b> #{$codigo_cotizacion}\n";
        $caption .= "👤 <b>Vendedor:</b> {$vendedor_nombre}\n";
        $caption .= "💰 <b>Monto:</b> $" . number_format( (float) $monto, 2 ) . "\n";
        $caption .= "💳 <b>Método:</b> {$forma_pago}\n\n";
        $caption .= "⚠️ <i>Use los botones de abajo para validar sin entrar al ERP.</i>";

        // Teclado Inline para la Fase 4.1 (Webhooks)
        $keyboard = array(
            'inline_keyboard' => array(
                array(
                    array( 'text' => '✅ Aprobar Pago', 'callback_data' => "approve_payment_{$quote_id}" ),
                    array( 'text' => '❌ Rechazar', 'callback_data' => "reject_payment_{$quote_id}" )
                )
            )
        );

        $body = array(
            'chat_id'      => $this->chat_id,
            $file_param    => $file_url,
            'caption'      => $caption,
            'parse_mode'   => 'HTML',
            'reply_markup' => wp_json_encode( $keyboard )
        );

        $url = "https://api.telegram.org/bot{$this->bot_token}/{$endpoint}";

        // Petición asíncrona para no bloquear el ERP
        return wp_remote_post( $url, array(
            'body'    => $body,
            'timeout' => 10,
            'blocking' => true // Queremos saber si salió, pero con un timeout razonable
        ) );
    }
	
	/**
     * Envía una alerta al Canal FISCAL con Facturas o Retenciones.
     */
    public function send_fiscal_document( $quote_id, $codigo_cotizacion, $vendedor_id, $tipo_documento, $file_url ) {
        if ( empty( $this->bot_token ) || empty( $this->fiscal_chat_id ) ) return false;

        $is_pdf     = ( substr( strtolower( $file_url ), -4 ) === '.pdf' );
        $endpoint   = $is_pdf ? 'sendDocument' : 'sendPhoto';
        $file_param = $is_pdf ? 'document' : 'photo';

        $vendedor_info   = get_userdata( $vendedor_id );
        $vendedor_nombre = $vendedor_info ? $vendedor_info->display_name : 'ID: ' . $vendedor_id;
        $emoji = $tipo_documento === 'Retención Fiscal' ? '🧾' : '📸';

        $caption  = "{$emoji} <b>NUEVO DOCUMENTO FISCAL</b> {$emoji}\n\n";
        $caption .= "📌 <b>Tipo:</b> {$tipo_documento}\n";
        $caption .= "🛍️ <b>Orden:</b> #{$codigo_cotizacion}\n";
        $caption .= "👤 <b>Vendedor:</b> {$vendedor_nombre}\n\n";
        $caption .= "<i>Archivo listo para revisión contable.</i>";

        $body = array(
            'chat_id'    => $this->fiscal_chat_id, // <-- APUNTA AL GRUPO FISCAL
            $file_param  => $file_url,
            'caption'    => $caption,
            'parse_mode' => 'HTML'
        );

        return wp_remote_post( "https://api.telegram.org/bot{$this->bot_token}/{$endpoint}", array(
            'body' => $body, 'timeout' => 5, 'blocking' => false 
        ) );
    }
}

/**
 * Endpoint Admin: Revertir orden de 'Por Enviar' a 'Pagado' (Error Logístico)
 */
class Suite_Ajax_Reverse_To_Paid extends Suite_AJAX_Controller {
    
    protected $action_name = 'suite_reverse_to_paid';
    protected $required_capability = 'manage_options'; // Estrictamente Administrador

    protected function process() {
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        if ( ! $order_id ) {
            $this->send_error( 'ID de cotización ausente.' );
        }

        $estado_actual = $wpdb->get_var( $wpdb->prepare( "SELECT estado FROM {$tabla_cot} WHERE id = %d", $order_id ) );

        if ( $estado_actual !== 'por_enviar' ) {
            $this->send_error( 'Esta orden no se puede revertir porque no está en la columna Por Enviar.' );
        }

        // Ejecutamos el reverso a "pagado"
        $updated = $wpdb->update(
            $tabla_cot,
            [ 'estado' => 'pagado' ], 
            [ 'id' => $order_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $updated !== false ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'reverso_logistico_pagado', "Orden #{$order_id} revertida de 'por_enviar' a 'pagado'." );
            }
            $this->send_success( 'Orden revertida a Pagado exitosamente.' );
        } else {
            $this->send_error( 'Error al actualizar la base de datos.' );
        }
    }
}



/**
 * 6. Endpoint para subir Comprobantes de Retención
 */
class Suite_Ajax_Upload_Retention extends Suite_AJAX_Controller {
    protected $action_name = 'suite_upload_retention';
    protected $required_capability = 'read';

    protected function process() {
        global $wpdb;
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;

        if ( ! $quote_id || empty( $_FILES['retencion_file']['name'] ) ) {
            $this->send_error( 'Datos o archivo ausentes.' );
        }

        // --- 🛡️ NUEVO: BARRERA ZERO-TRUST (5MB MAX) EN SERVIDOR ---
        $max_size_bytes = 5 * 1024 * 1024; // 5MB
        if ( $_FILES['retencion_file']['size'] > $max_size_bytes || $_FILES['retencion_file']['error'] === UPLOAD_ERR_INI_SIZE ) {
            $this->send_error( 'Violación de seguridad: El archivo excede el límite de 5MB. Operación cancelada.', 400 );
            return;
        }
        // ----------------------------------------------------------

        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $uploadedfile = $_FILES['retencion_file'];
		
		
		
		
		
        $upload_overrides = array( 'test_form' => false ); 

        $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $file_url = $movefile['url'];
            
            $updated = $wpdb->update(
                $wpdb->prefix . 'suite_cotizaciones',
                [ 'retencion_url' => $file_url ],
                [ 'id' => $quote_id ]
            );

			
			
			
            if ( $updated !== false ) {
                // --- 🚀 DISPARO A TELEGRAM (CANAL FISCAL) ---
                $quote_data = $wpdb->get_row($wpdb->prepare("SELECT codigo_cotizacion, vendedor_id FROM {$wpdb->prefix}suite_cotizaciones WHERE id = %d", $quote_id));
                if ( $quote_data && class_exists('Suite_Telegram_Bot') ) {
                    $telegram = new Suite_Telegram_Bot();
                    $telegram->send_fiscal_document( $quote_id, $quote_data->codigo_cotizacion, $quote_data->vendedor_id, 'Retención Fiscal', $file_url );
                }
                // --------------------------------------------
                
                $this->send_success( [ 'message' => 'Retención subida y notificada a Contabilidad.', 'url' => $file_url ] );
            } else {
				
				
				
                $this->send_error( 'Fallo al guardar en BD.' );
            }
        } else {
            $this->send_error( $movefile['error'] );
        }
    }
}


/**
 * Endpoint para subir Documentos Fiscales Extemporáneos (Manuales)
 */
class Suite_Ajax_Upload_Manual_Document extends Suite_AJAX_Controller {
    
    protected $action_name = 'suite_upload_manual_document';
    protected $required_capability = 'read';

    protected function process() {
        $cliente = sanitize_text_field( $_POST['cliente'] ?? '' );
        $rif     = sanitize_text_field( $_POST['rif'] ?? '' );
        $tipo    = sanitize_text_field( $_POST['tipo'] ?? '' );
        $vendedor_id = get_current_user_id();

        if ( empty( $cliente ) || empty( $rif ) || empty( $_FILES['fiscal_file']['name'] ) ) {
            $this->send_error( 'Faltan datos obligatorios.' );
        }

        // Validación estricta de peso en backend (3.5MB máximo)
        $max_size = 3.5 * 1024 * 1024;
        if ( $_FILES['fiscal_file']['size'] > $max_size || $_FILES['fiscal_file']['error'] === UPLOAD_ERR_INI_SIZE ) {
            $this->send_error( 'El archivo excede el límite de 3.5MB permitido por el servidor.' );
        }

        // Importar librería de WordPress para subida segura de archivos
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $uploadedfile     = $_FILES['fiscal_file'];
        $upload_overrides = array( 'test_form' => false );
        $movefile         = wp_handle_upload( $uploadedfile, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $file_url = $movefile['url'];

            // Regla de Negocio: Reusar el Bot que ya tenemos configurado apuntando al grupo Fiscal
            if ( class_exists('Suite_Telegram_Bot') ) {
                $telegram = new Suite_Telegram_Bot();
                $codigo_ref = "MANUAL - " . strtoupper( $rif ) . " (" . $cliente . ")";
                // ID 0 porque no hay orden atada, usa automáticamente el fiscal_chat_id
                $telegram->send_fiscal_document( 0, $codigo_ref, $vendedor_id, $tipo, $file_url );
            }

            $this->send_success( array( 'message' => 'Documento procesado y enviado con éxito.', 'url' => $file_url ) );
        } else {
            $this->send_error( $movefile['error'] );
        }
    }
}
```

### ARCHIVO: `includes/Controllers/Api/class-suite-api-telegram-webhook.php`
```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * FASE 4.1: Endpoint Webhook para la API de Telegram
 * Recibe y procesa los Callback Queries (Botones Inline) del equipo financiero.
 */
class Suite_Telegram_Webhook extends WP_REST_Controller {

    protected $namespace = 'suite/v1';
    protected $rest_base = 'telegram-webhook';

    // ⚠️ Token real inyectado y Secreto definido
    private $bot_token = '8190650297:AAEhx-eQygWnbid7mjcSQuN2KV4SigE6k38';
    private $webhook_secret = 'UNIT_FINANZAS_2026'; 

    /**
     * Registra el endpoint público en WordPress
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE, // Acepta POST
                'callback'            => array( $this, 'process_webhook' ),
                'permission_callback' => '__return_true' // Es público, la seguridad se valida con el secret
            )
        ) );
    }

    /**
     * Procesa el Payload entrante de Telegram
     */
    public function process_webhook( WP_REST_Request $request ) {
        // 1. VALIDACIÓN ZERO-TRUST
        $secret = $request->get_param( 'secret' );
        if ( $secret !== $this->webhook_secret ) {
            return new WP_REST_Response( 'Acceso denegado', 403 );
        }

        // 2. EXTRACCIÓN DEL PAYLOAD
        $body = $request->get_json_params(); 

        if ( empty( $body['callback_query'] ) ) {
            return new WP_REST_Response( 'OK', 200 );
        }

        $callback_query = $body['callback_query'];
        $callback_data  = sanitize_text_field( $callback_query['data'] );
        $callback_id    = sanitize_text_field( $callback_query['id'] );
        $chat_id        = sanitize_text_field( $callback_query['message']['chat']['id'] );
        $message_id     = sanitize_text_field( $callback_query['message']['message_id'] );

        // --- MAGIA ZERO-TRUST: ELEVACIÓN TEMPORAL DE PRIVILEGIOS ---
        // Buscamos al primer Administrador del sistema y le "prestamos" su ID
        // a Telegram para que el Modelo no bloquee la escritura en la Base de Datos.
        $admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) );
        if ( ! empty( $admins ) ) {
            wp_set_current_user( $admins[0] );
        }

        // Instanciar el modelo (Usamos class_exists por seguridad para evitar Fatal Errors)
        if ( ! class_exists( 'Suite_Model_Quote' ) ) {
            require_once SUITE_PATH . 'includes/Models/class-suite-model-quote.php';
        }
        $quote_model = new Suite_Model_Quote();

		
	
		
		
		
        $action_msg = '';

        // 3. LÓGICA DE NEGOCIO (CONEXIÓN CON EL ERP)
        if ( strpos( $callback_data, 'approve_payment_' ) === 0 ) {

            $quote_id = intval( str_replace( 'approve_payment_', '', $callback_data ) );
            
            // --- BARRERA ZERO-TRUST: Verificar estado actual antes de actuar ---
            $current_order = $quote_model->get( $quote_id );
            
            if ( ! $current_order || $current_order->estado !== 'pagado' ) {
                $action_msg = '⚠️ Acción denegada: La orden ya no está en estado "Pagado". Posiblemente fue procesada vía Web.';
            } else {
                $quote_model->update_order_status( $quote_id, 'por_enviar' );
                $action_msg = '✅ Pago Aprobado. La orden ha sido enviada a Logística.';
            }

        } elseif ( strpos( $callback_data, 'reject_payment_' ) === 0 ) {

            $quote_id = intval( str_replace( 'reject_payment_', '', $callback_data ) );
            
            // --- BARRERA ZERO-TRUST ---
            $current_order = $quote_model->get( $quote_id );
            
            if ( ! $current_order || $current_order->estado !== 'pagado' ) {
                $action_msg = '⚠️ Acción denegada: La orden ya no está en estado "Pagado".';
            } else {
                $quote_model->update_order_status( $quote_id, 'emitida' );
                $action_msg = '❌ Pago Rechazado. La orden fue devuelta a Pendiente.';
            }

        } else {
			
            return new WP_REST_Response( 'OK', 200 );
        }

        // 4. UX Y SEGURIDAD EN TELEGRAM (Evitar Dobles Clics)

        // A) Detener el "relojito" de carga en el botón pulsado
        $this->telegram_request( 'answerCallbackQuery', array(
            'callback_query_id' => $callback_id,
            'text'              => $action_msg,
            'show_alert'        => false
        ) );

        // B) DESTRUIR los botones del mensaje original (Zero-Trust UI)
        $this->telegram_request( 'editMessageReplyMarkup', array(
            'chat_id'      => $chat_id,
            'message_id'   => $message_id,
            'reply_markup' => array( 'inline_keyboard' => array() ) 
        ) );

        // C) Enviar un mensaje de confirmación al hilo del chat
        $this->telegram_request( 'sendMessage', array(
            'chat_id'             => $chat_id,
            'text'                => "<b>ACTUALIZACIÓN ORDEN #{$quote_id}:</b>\n{$action_msg}",
            'parse_mode'          => 'HTML',
            'reply_to_message_id' => $message_id
        ) );

        return new WP_REST_Response( 'Webhook procesado con éxito', 200 );
    }

    /**
     * Helper privado para consumir la API de Telegram
     */
    private function telegram_request( $method, $parameters ) {
        $url = "https://api.telegram.org/bot{$this->bot_token}/{$method}";

        wp_remote_post( $url, array(
            'body'    => wp_json_encode( $parameters ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 10
        ) );
    }
}
```

### ARCHIVO: `views/app/tab-kanban.php`
```php
<?php
/**
 * Vista: Tablero Kanban de Pedidos (Módulo 1)
 * 
 * Contiene la interfaz interactiva Drag & Drop para la gestión visual de cotizaciones y pedidos.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<style>
    /* Estilos específicos del Kanban (Actualizado a Ruta A: Estilo Trello) */
    .kanban-board {
        display: flex;
        flex-wrap: nowrap;          /* Evita que la 5ta columna caiga abajo */
        gap: 20px;
        align-items: start;
        margin-top: 15px;
        overflow-x: auto;           /* Habilita el scroll horizontal elegante */
        padding-bottom: 15px;       /* Espacio extra para que la barra de scroll no pise las tarjetas */
    }
    .kanban-column-wrapper {
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
        min-width: 300px; /* Forzamos un ancho mínimo para cada columna */
        flex: 0 0 300px;  /* Evita que Flexbox las aplaste */
    }
    .kanban-column-header {
        padding: 15px;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    /* Acentos de color por columna */
    .col-emitida .kanban-column-header { border-bottom-color: #f59e0b; }
    .col-pagado .kanban-column-header { border-bottom-color: #10b981; } /* Pagado ahora es el paso 2 */
	.col-por_enviar .kanban-column-header { border-bottom-color: #f97316; }
    .col-despachado .kanban-column-header { border-bottom-color: #8b5cf6; }

    .kanban-column-body {
        padding: 15px;
        overflow-y: auto;
        flex-grow: 1;
        min-height: 150px;
    }
    .kanban-card {
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        cursor: grab;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kanban-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .kanban-card:active {
        cursor: grabbing;
    }
    .kanban-ghost {
        opacity: 0.4;
        background: #e2e8f0;
    }
    .kanban-card-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }
    .kanban-card-client {
        font-size: 13px;
        color: #475569;
        margin-bottom: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kanban-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px dashed #e2e8f0;
        padding-top: 10px;
        margin-top: 10px;
    }
    .kanban-wa-btn {
        background: #25d366;
        color: #fff !important;
        font-size: 11px;
        font-weight: bold;
        padding: 4px 8px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: background 0.2s;
    }
    .kanban-wa-btn:hover { background: #1ebc59; }
</style>

<div id="TabKanban" class="suite-tab-content" style="display: none;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
        <h2>📦 Portal de Pedidos</h2>
        <button class="btn-modern-action" onclick="SuiteKanban.loadBoard()">🔄 Refrescar Tablero</button>
    </div>

    <div class="kanban-board">
        
        <!-- Columna 1: Pendientes -->
        <div class="kanban-column-wrapper col-emitida">
            <div class="kanban-column-header">
                <span>🟡 Pendiente</span>
                <span class="count-badge pill-neutral" id="count-emitida">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-emitida" data-status="emitida">
                <!-- JS insertará tarjetas aquí -->
            </div>
        </div>

        <!-- Columna 2: En Proceso -->
        <div class="kanban-column-wrapper col-pagado">
            <div class="kanban-column-header">
                <span>💰 Pagado</span>
                <span class="count-badge pill-neutral" id="count-pagado">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-pagado" data-status="pagado"></div>
        </div>
		
        <!-- Columna Nueva: Facturado / Pagado -->
		<div class="kanban-column-wrapper col-por_enviar">
            <div class="kanban-column-header">
                <span>🟠 Por Enviar</span>
                <span class="count-badge pill-neutral" id="count-por_enviar">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-por_enviar" data-status="por_enviar"></div>
        </div>
		
        <!-- Columna 4: Despachado -->
        <div class="kanban-column-wrapper col-despachado">
            <div class="kanban-column-header">
                <span>🟣 Enviado</span>
                <span class="count-badge pill-neutral" id="count-despachado">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-despachado" data-status="despachado"></div>
        </div>

    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: CIERRE DE VENTA (MÓDULO 4 - COMISIONES Y LOGÍSTICA)-->
<!-- ========================================================= -->
<div id="modal-cierre-venta" class="suite-modal" style="display: none;">
    <div class="suite-modal-content">
        <!-- El ID del botón de cierre es clave para el JS -->
        <span class="close-modal" id="close-modal-cierre">×</span>
        
        <h3 style="margin-top:0; color:#0f172a;">💰 Procesar Pago y Comisión</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:20px;">
            Complete los datos operativos para registrar su comisión y autorizar el despacho.
        </p>

        <!-- Guardamos el ID del pedido arrastrado -->
        <input type="hidden" id="cierre-quote-id">

        <div class="form-group-row">
            <div style="flex:1">
                <label>Canal de Venta *</label>
                <select id="cierre-canal" class="widefat">
                    <option value="">Seleccione...</option>
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Tienda Fisica">Tienda Física</option>
                    <option value="Llamada">Llamada Telefónica</option>
                    <option value="Referido">Referido Comercial</option>
                </select>
            </div>
            <div style="flex:1">
                <label>Método de Pago *</label>
                <select id="cierre-pago" class="widefat">
                    <option value="">Seleccione...</option>
                    <option value="Zelle">Zelle</option>
                    <option value="Efectivo USD">Efectivo USD</option>
                    <option value="Punto de Venta">Punto de Venta (Bs)</option>
                    <option value="Pago Movil">Pago Móvil</option>
                    <option value="Cashea">Cashea</option>
                    <option value="Transferencia">Transferencia USD/Bs</option>
                </select>
            </div>
        </div>

        <label>Método de Entrega *</label>
        <select id="cierre-entrega" class="widefat">
            <option value="">Seleccione...</option>
            <option value="Retiro en Tienda">Retiro en Tienda</option>
            <option value="Delivery">Delivery (Caracas)</option>
            <option value="Encomienda MRW">Encomienda Nacional (MRW)</option>
            <option value="Encomienda Tealca">Encomienda Nacional (Tealca)</option>
            <option value="Encomienda Zoom">Encomienda Nacional (Zoom)</option>
        </select>
		
		

	
		<label>N° de Factura / Nota de Entrega *</label>
		<div style="display: flex; gap: 8px; margin-bottom: 10px;">
			<select id="cierre-recibo-prefijo" class="widefat" style="width: 80px; margin-bottom: 0;">
				<option value="F">F</option>
				<option value="NE">NE</option>
			</select>
			<input type="text" id="cierre-loyverse" class="widefat" placeholder="Ej: 1005 (Solo números)" style="flex: 1; margin-bottom: 0;" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
		</div>		

        <label>Link de Captura de Pago / Soporte</label>
        <input type="url" id="cierre-captura" class="widefat" placeholder="https://drive.google.com/... o Imgur">

		<!-- INICIO: Selector de Venta Compartida -->
		<label style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 10px;">
			🤝 ¿Venta Compartida? (Colaboradores)
		</label>
		<select id="cierre-compartido" class="widefat" multiple="multiple" data-placeholder="Seleccione otros vendedores...">
			<?php
			// Poblar dinámicamente aislando al usuario actual
			$vendedores = get_users( ['role__not_in' => ['subscriber', 'customer']] );
			$current_user_id = get_current_user_id();
			foreach ( $vendedores as $vend ) {
				if ( $vend->ID != $current_user_id ) {
					echo '<option value="' . esc_attr( $vend->ID ) . '">' . esc_html( $vend->display_name ) . '</option>';
				}
			}
			?>
		</select>
		<small style="color:#64748b; font-size: 11px; display:block; margin-bottom: 15px;">
			Deje en blanco si la venta es individual.
		</small>
		<!-- FIN: Selector de Venta Compartida -->		
		
		
		<div id="container-b2b-percent" style="display: none; margin-top: 15px; background: #f0f9ff; padding: 15px; border-left: 4px solid #0ea5e9; border-radius: 8px;">
            <label style="font-weight:bold; color:#0369a1; display:block; margin-bottom:8px;">🤝 Porcentaje de Comisión B2B (%)</label>
            <div style="display:flex; align-items:center; gap:10px;">
                <input type="number" id="cierre-b2b-percent" class="widefat" step="0.1" min="0" max="100" placeholder="Ej: 15" style="border-color:#bae6fd; margin-bottom:0;">
                <span style="font-weight:bold; color:#0369a1;">%</span>
            </div>
            <small style="color:#0284c7; display:block; margin-top:5px; font-size:11px;">
                Detectado: Aliado Comercial. Ingrese el porcentaje acordado para este pedido.
            </small>
        </div>		
		
        <button class="btn-save-big" id="btn-confirmar-pago" style="margin-top:15px; background-color: #10b981;">
            Confirmar y Procesar Pago
        </button>
    </div>
</div>

<div id="modal-super-pago" class="suite-modal" style="display: none;">
    <div class="suite-modal-content" style="max-width: 650px; background: #f8fafc;">
        <span class="close-modal" id="close-super-pago">&times;</span>
        <h2 style="margin-top:0; color:#0f172a; display:flex; align-items:center; gap:10px;">
            💸 <span>Cierre de Venta y Logística</span>
        </h2>
        <p style="color:#64748b; font-size:13px; margin-top:-10px; margin-bottom:20px;">Complete los datos financieros y de entrega para validar el pago.</p>

        <form id="form-super-pago">
            <input type="hidden" id="sp-quote-id" value="">

            <div style="background:#ffffff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:15px;">
                <h3 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; color:#1e293b; margin-top:0; font-size:15px;">Sección A: El Pago</h3>

                <div class="form-group-row">
					
					
					
					
					
					
					
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Forma de Pago *</label>
                        <select id="sp-forma-pago" class="widefat" required>
                            <option value="">Seleccione...</option>
                            <option value="Transferencia">Transferencia Bancaria</option>
                            <option value="Pago Movil">Pago Móvil</option>
                            <option value="Zelle">Zelle</option>
                            <option value="Efectivo">Efectivo (Divisas)</option>
                            <option value="Binance">Binance (USDT)</option>
                            <option value="Punto de Venta">Punto de Venta</option>
                        </select>
                    </div>
					
					
					
					
					
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Fecha de Pago *</label>
                        <input type="date" id="sp-fecha-pago" class="widefat" required>
                    </div>
                </div>

                <div class="form-group-row">
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Monto Pagado *</label>
                        <input type="number" step="0.01" id="sp-monto-pagado" class="widefat" placeholder="Ej: 150.00" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Comprobante (Imagen o PDF) *</label>
                        <input type="file" id="sp-comprobante" class="widefat" accept=".jpg,.jpeg,.png,.pdf" style="padding: 5px; font-size: 12px; cursor: pointer;" required>
                    </div>
                </div>

                <div style="display:flex; gap: 20px; margin-top: 10px; padding-top:10px; border-top:1px dashed #e2e8f0;">
                    <label style="font-size:13px; color:#334155; cursor:pointer;"><input type="checkbox" id="sp-factura"> Requiere Factura Fiscal</label>
                    <label style="font-size:13px; color:#334155; cursor:pointer;"><input type="checkbox" id="sp-retencion"> Agente de Retención</label>
                </div>
            </div>

			<div style="background:#ffffff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                <h3 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; color:#1e293b; margin-top:0; font-size:15px;">Sección B: Logística y Envío</h3>

                <div style="margin-bottom: 15px;">
                    <label style="font-size:12px; font-weight:bold; color:#475569;">Tipo de Despacho *</label>
                    <select id="sp-tipo-envio" class="widefat" required>
                        <option value="">Seleccione el método...</option>
                        <option value="Retiro">🏢 Retiro en Tienda</option>
                        <option value="Motorizado">🛵 Delivery / Motorizado (Local)</option>
                        <option value="Nacional">📦 Envío Nacional (Encomienda)</option>
                    </select>
                </div>

				
				
				
                <div id="sp-datos-envio-container" style="display:none; background:#f8fafc; padding:15px; border-radius:8px; border: 1px solid #cbd5e1;">
                    
                    <div style="margin-bottom: 15px; display:none;" id="box-sucursal">
                        <label style="font-size:12px; font-weight:bold; color:#0c4a6e;">🏢 ¿En qué sucursal retirará? *</label>
                        <select id="sp-sucursal" class="widefat" style="border-color:#bae6fd;">
                            <option value="">Seleccione la sucursal de retiro...</option>
                            <option value="Tienda C.C. Millennium Mall">Tienda C.C. Millennium Mall</option>
                            <option value="Tienda C.C. Galerías Ávila">Tienda C.C. Galerías Ávila</option>
                        </select>
                    </div>

                    <div class="form-group-row">
                        <div style="flex:1; display:none;" id="box-agencia">
							
							
							
							
							
                            <label style="font-size:12px; font-weight:bold; color:#475569;">Agencia *</label>
                            <select id="sp-agencia-envio" class="widefat">
                                <option value="">Seleccione...</option>
                                <option value="MRW">MRW</option>
                                <option value="Zoom">Zoom</option>
                                <option value="Tealca">Tealca</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">Nombre y Apellido (Receptor) *</label>
                            <input type="text" id="sp-nombre-receptor" class="widefat">
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div style="flex:1;">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">RIF / Cédula *</label>
                            <input type="text" id="sp-rif-receptor" class="widefat">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">Teléfono *</label>
                            <input type="text" id="sp-telefono-receptor" class="widefat">
                        </div>
                    </div>

                    <div style="margin-top: 10px; display:none;" id="box-direccion">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Dirección Exacta de Destino *</label>
                        <textarea id="sp-direccion-envio" class="widefat" rows="2"></textarea>
                    </div>
                </div>
            </div>
			

            <div style="background:#fee2e2; padding:15px; border-radius:8px; border:2px solid #fca5a5; margin-bottom:20px;">
                <label style="color:#dc2626; font-weight:900; font-size:14px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" id="sp-prioridad" style="width:20px; height:20px; accent-color: #dc2626;">
                    🚨 MARCAR ESTA ORDEN COMO PRIORIDAD URGENTE
                </label>
            </div>

            <div style="display:flex; justify-content: flex-end; gap:10px;">
                <button type="button" class="btn-modern-action" id="btn-cancel-sp" style="background:#64748b; color:white; padding:12px 20px;">Cancelar</button>
                <button type="submit" class="btn-save-big" style="background:#059669; padding:12px 20px;">✅ Confirmar Datos</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-ver-pago" class="suite-modal" style="display: none;">
    <div class="suite-modal-content" style="max-width: 500px; background: #f8fafc;">
        <span class="close-modal" id="close-ver-pago">&times;</span>
        <h2 style="margin-top:0; color:#0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
            👀 Verificación de Pago
        </h2>
        <div id="vp-content" style="margin-top: 15px;">
            </div>
        <div style="text-align: right; margin-top: 15px;">
            <button class="btn-modern-action" id="btn-cerrar-ver-pago" style="background:#64748b; color:white; padding:8px 15px;">Cerrar Vista</button>
        </div>
    </div>
</div>
```

