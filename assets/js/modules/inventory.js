/**
 * SuiteInventory - Módulo de Lectura Dinámica de Inventario (CSV)
 * Aplica lógica de visibilidad y formateo condicional basado en Roles (Zero-Trust UI).
 */
const SuiteInventory = (function($) {
    'use strict';
    let table = null;

    const initTable = function() {
        if ($('#inventoryTable').length && !$.fn.DataTable.isDataTable('#inventoryTable')) {
            // Lectura estricta del perfil desde la inyección de WP
            const isMarketing = suite_vars.is_marketing === '1' || suite_vars.is_marketing === true;

            table = $('#inventoryTable').DataTable({
                responsive: true,
                language: { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                columns: [
                    { data: 'sku' },
                    { data: 'nombre' },
                    { data: 'status' },
                    // Ocultamos estas columnas a Marketing (pero los vendedores las ven)
                    { data: 'stock_total', visible: !isMarketing },
                    { data: 'disponibilidad_galerias', visible: !isMarketing },
                    { data: 'disponibilidad_millennium', visible: !isMarketing },
                    { 
                        data: 'cantidad_en_transito',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                let qty = parseInt(data) || 0;
                                
                                // REGLA ESPECIAL PARA MARKETING (Binario)
                                if (isMarketing) {
                                    return qty > 0 
                                        ? '<span class="status-pill pill-neutral" style="background:#e0f2fe; color:#0369a1;">Orden en tránsito</span>' 
                                        : '';
                                }
                                
                                // REGLA PARA VENDEDORES (Número exacto + Badge si viene en camino)
                                let badge = qty > 0 ? ` <span style="font-size:10px; color:#0284c7;">(En tránsito)</span>` : '';
                                return `<strong>${qty}</strong>${badge}`;
                            }
                            return data;
                        }
                    }
                ]
            });
        }
    };

    const loadInventory = function() {
        if (!table) return;
        
        // Bloquear UI mientras carga
        $('#inventoryTable tbody').html('<tr><td colspan="7" style="text-align:center;">Cargando inventario desde el Data Lake...</td></tr>');

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