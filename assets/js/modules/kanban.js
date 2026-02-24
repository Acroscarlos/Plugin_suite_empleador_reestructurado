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
            waBtnHtml = `<a href="https://api.whatsapp.com/send?phone=${order.wa_phone}&text=${msg}" target="_blank" class="kanban-wa-btn">📱 WA</a>`;
        }

        return `
            <div class="kanban-card" data-id="${order.id}">
                <div class="kanban-card-title">#${order.codigo_cotizacion}</div>
                <div class="kanban-card-client" title="${order.cliente_nombre}">👤 ${order.cliente_nombre || 'Sin Nombre'}</div>
                
                <div class="kanban-card-footer">
                    <strong style="color: #059669; font-size:14px;">$${order.total_fmt}</strong>
                    <div style="display:flex; gap: 8px;">
                        ${waBtnHtml}
                        <a href="${suite_vars.ajax_url}?action=suite_print_quote&id=${order.id}" target="_blank" class="btn-modern-action" style="padding: 4px; font-size:11px;">🖨️</a>
                    </div>
                </div>
            </div>
        `;
    };

    const initSortableColumns = function() {
        const columns = document.querySelectorAll('.kanban-column-body');
        
        columns.forEach(col => {
            new Sortable(col, {
                group: 'kanban', 
                animation: 150,
                ghostClass: 'kanban-ghost',
                onEnd: function (evt) {
                    const itemEl = evt.item;  
                    const toCol = evt.to;     
                    const fromCol = evt.from; 
                    
                    if (toCol === fromCol) return; 

                    const quoteId = itemEl.getAttribute('data-id');
                    const newStatus = toCol.getAttribute('data-status');

                    // ----------------------------------------------------
                    // INTERCEPTOR MÓDULO 4: Cierre de Venta
                    // ----------------------------------------------------
                    if (newStatus === 'pagado') {
                        // 1. Guardar contexto en memoria para no perder la tarjeta
                        pendingDrop = { quoteId, itemEl, fromCol, toCol };
                        
                        // 2. Limpiar modal anterior y setear ID oculto
                        $('#modal-cierre-venta input, #modal-cierre-venta select').val('');
                        $('#cierre-quote-id').val(quoteId);
                        
                        // 3. Abrir Modal (No enviamos AJAX aún)
                        $('#modal-cierre-venta').fadeIn();
                    } else {
                        // Si es otro estado (ej. de Pagado a Despachado), enviar directo
                        updateCounters();
                        updateOrderStatus(quoteId, newStatus, itemEl, fromCol);
                    }
                },
            });
        });
    };

    /**
     * Envía un cambio de estado normal (Sin modal) a la base de datos
     */
    const updateOrderStatus = function(quoteId, newStatus, itemEl, originalCol) {
        SuiteAPI.post('suite_change_status_ajax', { 
            id: quoteId, // Actualizado al formato de Suite_Ajax_Quote_Status
            estado: newStatus 
        }).then(res => {
            if (!res.success) {
                alert('❌ Error: ' + (res.data.message || res.data));
                originalCol.appendChild(itemEl); // Revertir UI
                updateCounters();
            }
        }).catch(() => {
            alert('❌ Fallo de conexión al actualizar el pedido.');
            originalCol.appendChild(itemEl);
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
        
        // 1. Cerrar o cancelar Modal (Botón "X" o click por fuera)
        $('#close-modal-cierre').on('click', function() {
            if (pendingDrop) {
                // Al cancelar, la tarjeta debe devolverse a su columna original
                pendingDrop.fromCol.appendChild(pendingDrop.itemEl);
                pendingDrop = null;
                updateCounters();
            }
            $('#modal-cierre-venta').fadeOut();
        });

        // 2. Procesar Formulario
        $('#btn-confirmar-pago').on('click', function(e) {
            e.preventDefault();
            if (!pendingDrop) return;

            // Recolectar datos
            const payload = {
                action: 'suite_change_status_ajax',
                id: pendingDrop.quoteId,
                estado: 'pagado',
                canal_venta: $('#cierre-canal').val(),
                metodo_pago: $('#cierre-pago').val(),
                metodo_entrega: $('#cierre-entrega').val(),
                recibo_loyverse: $('#cierre-loyverse').val().trim(),
                url_captura: $('#cierre-captura').val().trim()
            };

            // Validar obligatorios
            if (!payload.canal_venta || !payload.metodo_pago || !payload.metodo_entrega || !payload.recibo_loyverse) {
                return alert('⚠️ Por favor complete todos los campos obligatorios (*).');
            }

            const btn = $(this);
            btn.prop('disabled', true).text('Procesando...');

            // Enviar al Backend
            SuiteAPI.post('suite_change_status_ajax', payload).then(res => {
                if (res.success) {
                    alert('✅ ' + (res.data.message || 'Pago procesado y comisión registrada.'));
                    $('#modal-cierre-venta').fadeOut();
                    pendingDrop = null; // Liberar memoria, tarjeta se queda en 'Pagado'
                    updateCounters();
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                    // Revertir UI
                    pendingDrop.fromCol.appendChild(pendingDrop.itemEl);
                    pendingDrop = null;
                    $('#modal-cierre-venta').fadeOut();
                    updateCounters();
                }
            }).catch(() => {
                alert('❌ Error de red. La operación fue cancelada.');
                pendingDrop.fromCol.appendChild(pendingDrop.itemEl);
                pendingDrop = null;
                $('#modal-cierre-venta').fadeOut();
                updateCounters();
            }).finally(() => {
                btn.prop('disabled', false).text('Confirmar y Procesar Pago');
            });
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
                    
                    const columnasPermitidas = ['emitida', 'proceso', 'pagado', 'despachado'];
                    
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