/**
 * SuiteInventory - Módulo de Lectura Dinámica de Inventario (CSV)
 * Aplica lógica de visibilidad y formateo condicional basado en Roles (Zero-Trust UI).
 */
const SuiteInventory = (function($) {
    'use strict';
    let table = null;

    const dtLanguage = { 
        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json",
        "search": "🔍 Buscar producto o SKU:",
        "lengthMenu": "Mostrar _MENU_ registros"
    };

    const initTable = function() {
        if ($('#inventoryTable').length && !$.fn.DataTable.isDataTable('#inventoryTable')) {
            // Lectura estricta del perfil desde la inyección de WP
            const isMarketing = suite_vars.is_marketing === '1' || suite_vars.is_marketing === true;

            table = $('#inventoryTable').DataTable({
                responsive: true,
                language: dtLanguage,
                pageLength: 25,
                // NUEVO DOM: Movemos Length (l), Info (i) y Filter/Search (f) a la clase "dt-top-controls"
                dom: '<"dt-top-controls"l i f>rt<"bottom"p><"clear">', 
                columns: [
                    { data: 'sku', className: 'font-mono text-gray-500 font-bold' },
                    { data: 'nombre' },
                    { 
                        data: 'precio_venta',
                        render: function(data) { 
                            let price = parseFloat(data) || 0;
                            return `<span style="color: #10b981; font-weight: bold; font-size: 15px;">$${price.toFixed(2)}</span>`; 
                        }
                    },
                    { 
                        data: 'precio_divisas',
                        defaultContent: "0",
                        render: function(data) { 
                            let valorDecimal = parseFloat(data) || 0;
                            let redondeado = Math.round(valorDecimal);
                            return `<span style="font-weight: bold; color: #0f172a; font-size: 14px;">$${redondeado.toFixed(2)}</span>`; 
                        }
                    },
                    { 
                        data: 'status',
                        className: 'text-center',
                        render: function(data) {
                            // Badges Predictivos de IA
                            let badgeStyle = "background: #f1f5f9; color: #475569;"; // Default Gris
                            let label = (data && data !== '-') ? data.toUpperCase() : 'N/A';

                            if (label === 'OPTIMO' || label === 'ÓPTIMO') {
                                badgeStyle = "background: #d1fae5; color: #0369a1;"; // Verde/Azul
                                
                            // ✅ AHORA CRÍTICO Y SOBRESTOCK COMPARTEN EL MISMO COLOR ROJO
                            } else if (label === 'CRITICO' || label === 'CRÍTICO' || label === 'SOBRESTOCK') {
                                badgeStyle = "background: #fee2e2; color: #b91c1c;"; // Rojo
                                
                            } else if (label === 'REORDER') {
                                badgeStyle = "background: #fef3c7; color: #b45309;"; // Ámbar
                            }

                            return `<span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; ${badgeStyle}; line-height: 1.2;">${label}</span>`;
                        }
                    },
                    // --- BARRERA ZERO-TRUST: Columnas Ocultas para Marketing ---
                    { 
                        data: 'stock_total', 
                        visible: !isMarketing,
                        className: 'text-center',
                        render: function(data) { return formatStock(data); }
                    },
                    { 
                        data: 'disponibilidad_galerias', 
                        visible: !isMarketing,
                        className: 'text-center',
                        render: function(data) { return formatStock(data); }
                    },
                    { 
                        data: 'disponibilidad_millennium', 
                        visible: !isMarketing,
                        className: 'text-center',
                        render: function(data) { return formatStock(data); }
                    },
                    // -----------------------------------------------------------
                    { 
                        data: 'inventario_entrante',
                        className: 'text-center',
                        render: function(data, type) {
                            if (type === 'display') {
                                let isIncoming = (data && data.toString().trim().toLowerCase() === 'si');
                                
                                // REGLA ESPECIAL PARA MARKETING
                                if (isMarketing) {
                                    return isIncoming 
                                        ? '<span style="background:#e0f2fe; color:#0369a1; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold;">En camino</span>' 
                                        : '<span style="color:#cbd5e1;">-</span>';
                                }
                                
                                // REGLA PARA VENDEDORES (Icono de Barco)
                                if (isIncoming) {
                                    return '<span style="background:#f0fdf4; color:#059669; border:1px solid #a7f3d0; padding: 4px 8px; border-radius: 6px; font-size: 13px;" title="Mercancía en Tránsito">🚢 Sí</span>';
                                }
                                return '<span style="color: #cbd5e1;">-</span>';
                            }
                            return data;
                        }
                    }
                ]
            });
        }
    };

    // Función auxiliar para semáforos de stock físico
    const formatStock = function(qty) {
        let stock = parseInt(qty) || 0;
        if (stock <= 0) {
            return `<div style="background: #fff1f2; color: #ef4444; padding: 4px; border-radius: 6px; font-weight: normal; font-size: 13px; width: 40px; margin: 0 auto;">0</div>`;
        }
        return `<div style="background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; padding: 4px; border-radius: 6px; font-weight: bold; font-size: 14px; width: 40px; margin: 0 auto;">${stock}</div>`;
    };

    const loadInventory = function() {
        if (!table) return;
        
        // Bloquear UI mientras carga
        $('#inventoryTable tbody').html('<tr><td colspan="100%" style="text-align:center; padding: 20px; color: #64748b;">⏳ Cargando inventario desde el Data Lake...</td></tr>');
        
        SuiteAPI.post('suite_get_inventory').then(res => {
            if (res.success && res.data) {
                table.clear();
                table.rows.add(res.data);
                table.draw();
            } else {
                alert('⚠️ Error: ' + (res.data.message || 'No se pudo leer el CSV.'));
            }
        }).catch(() => {
            alert('❌ Ocurrió un error de red al intentar descargar el inventario.');
        });
    };

    return {
        init: function() {
            initTable();
            loadInventory();
        }
    };
})(jQuery);