/**
 * SuiteHistorial - Módulo de Historial de Cotizaciones
 * Permite buscar, imprimir, enviar por WA y CLONAR pedidos hacia el Cotizador.
 */
const SuiteHistorial = (function($) {
    'use strict';
    let table = null;

    const initTable = function() {
        if ($('#historyTable').length && !$.fn.DataTable.isDataTable('#historyTable')) {
            table = $('#historyTable').DataTable({
                responsive: true,
                language: { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[0, 'desc']] // Ordenar por fecha de más reciente a antiguo
            });
        }
    };

    const loadHistory = function() {
        if (!table) return;
        
        SuiteAPI.post('suite_get_history_ajax').then(res => {
            if (res.success && res.data) {
                table.clear();
                
                res.data.forEach(r => {
                    let badgeClass = (r.estado === 'pagado' || r.estado === 'despachado') ? 'pill-critico' : 'pill-neutral';
                    
                    // Botón WhatsApp
                    let waBtn = '';
                    if (r.wa_phone) {
                        let msg = encodeURIComponent(`Hola ${r.cliente_nombre}, le enviamos su cotización ${r.codigo_cotizacion}: ${suite_vars.ajax_url}?action=suite_print_quote&id=${r.id}`);
                        waBtn = `<a href="https://api.whatsapp.com/send?phone=${r.wa_phone}&text=${msg}" target="_blank" class="btn-modern-action small" style="color:#10b981; border-color:#a7f3d0;" title="WhatsApp">📱 WA</a>`;
                    }

                    // Botón Imprimir PDF
                    let printBtn = `<a href="${suite_vars.ajax_url}?action=suite_print_quote&id=${r.id}&nonce=${suite_vars.nonce}" target="_blank" class="btn-modern-action small" style="color:#475569;" title="Imprimir PDF">🖨️</a>`;

                    // Botón Clonar (La acción estrella)
                    let cloneBtn = `<button class="btn-modern-action small btn-clone-quote" data-id="${r.id}" style="color:#0073aa; border-color:#bae6fd;" title="Clonar al Cotizador">🔄 Clonar</button>`;

                    table.row.add([
                        r.fecha_fmt,
                        `<strong>#${r.codigo_cotizacion}</strong>`,
                        r.cliente_nombre,
                        `$${r.total_fmt}`,
                        `<span class="status-pill ${badgeClass}">${r.estado.toUpperCase()}</span>`,
                        `<div style="display:flex; gap:5px;">${printBtn} ${waBtn} ${cloneBtn}</div>`
                    ]);
                });
                
                table.draw();
            }
        });
    };

    const bindEvents = function() {
        // Evento Delegado: CLONAR COTIZACIÓN
        $('#historyTable').on('click', '.btn-clone-quote', function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');
            const btn = $(this);
            
            if (!confirm('¿Desea clonar esta cotización? Los datos actuales del cotizador se sobrescribirán.')) return;
            
            btn.prop('disabled', true).text('⏳...');

            SuiteAPI.post('suite_get_quote_details_ajax', { id: quoteId }).then(res => {
                if (res.success) {
                    const data = res.data;
                    
                    // 1. Inyectar Cliente en el formulario del Cotizador
                    if (data.cliente) {
                        const c = data.cliente;
                        const prefix = c.rif_ci.charAt(0).toUpperCase();
                        const number = c.rif_ci.substring(1);
                        
                        $('#cli-rif-prefix').val(["V","E","J","G","P","C"].includes(prefix) ? prefix : 'V');
                        $('#cli-rif-number').val(number);
                        $('#cli-nombre').val(c.nombre_razon);
                        $('#cli-tel').val(c.telefono);
                        $('#cli-email').val(c.email);
                        $('#cli-dir').val(c.direccion);
                        $('#cli-ciudad').val(c.ciudad);
                        $('#cli-estado').val(c.estado);
                        $('#cli-contacto').val(c.contacto_persona);
                        
                        // NOTA: No bloqueamos el form (lockClientForm) para permitirle modificarlo si gusta
                    }
                    
                    // 2. Mapear y setear el Carrito (Se usa la API pública de SuiteQuoter)
                    if (data.items) {
                        const newCart = data.items.map(item => ({
                            sku: item.sku,
                            name: item.producto_nombre,
                            price: parseFloat(item.precio_unitario_usd),
                            qty: parseInt(item.cantidad),
                            time: item.tiempo_entrega || 'Inmediata'
                        }));
                        SuiteQuoter.setCart(newCart);
                    }
                    
                    // 3. Redirección visual automática a la Pestaña del Cotizador
                    const cotizadorBtn = $('.tab-btn:contains("Cotizador")');
                    if (cotizadorBtn && typeof window.openSuiteTab === 'function') {
                        window.openSuiteTab('TabPos', cotizadorBtn);
                    }

                } else {
                    alert('❌ Error al clonar: ' + (res.data.message || res.data));
                }
            }).catch(() => {
                alert('❌ Ocurrió un error de red al intentar clonar.');
            }).finally(() => {
                btn.prop('disabled', false).html('🔄 Clonar');
            });
        });
    };

    return {
        init: function() {
            initTable();
            bindEvents();
            loadHistory();
        },
        refresh: loadHistory
    };
})(jQuery);