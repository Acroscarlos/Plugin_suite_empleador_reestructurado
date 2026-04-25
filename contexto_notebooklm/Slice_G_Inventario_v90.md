# 🧩 MÓDULO LÓGICO: Slice_G_Inventario

### ARCHIVO: `assets/js/modules/inventory.js`
```js
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
```

### ARCHIVO: `includes/Controllers/Ajax/class-suite-ajax-inventory.php`
```php
<?php
/**
 * Controlador AJAX: Módulo de Inventario
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Suite_Ajax_Get_Inventory extends Suite_AJAX_Controller {

    protected $action_name = 'suite_get_inventory';
    protected $required_capability = 'read';

    protected function process() {
        // 1. Apuntamos a la nueva matriz unificada
        $csv_path = SUITE_PATH . 'output/Matriz_unificada_Woocommerce.csv';
        
        if ( ! file_exists( $csv_path ) || ( $handle = fopen( $csv_path, 'r' ) ) === false ) {
            $this->send_error( 'El archivo de inventario unificado no se encuentra disponible actualmente.' );
        }

        $headers = fgetcsv( $handle, 2000, ',' );
        if ( ! $headers ) {
            fclose( $handle );
            $this->send_error( 'El archivo CSV está vacío o corrupto.' );
        }

        // 2. Mapeo Heurístico (Smart Indexing)
        $map = [];
        foreach ( $headers as $index => $header ) {
            $h_clean = strtolower( trim( $header ) );
            if ( strpos( $h_clean, 'sku' ) !== false ) $map['sku'] = $index;
            elseif ( strpos( $h_clean, 'nombre' ) !== false ) $map['nombre'] = $index;
            elseif ( strpos( $h_clean, 'precio_venta' ) !== false || $h_clean === 'precio' ) $map['precio_venta'] = $index;
            elseif ( strpos( $h_clean, 'divisa' ) !== false ) $map['precio_divisas'] = $index; 
			elseif ( strpos( $h_clean, 'velocidad' ) !== false ) $map['velocidad_venta'] = $index; 			
            elseif ( strpos( $h_clean, 'status' ) !== false ) $map['status_prediccion'] = $index;
            elseif ( strpos( $h_clean, 'entrante' ) !== false ) $map['inventario_entrante'] = $index;
            elseif ( strpos( $h_clean, 'gale' ) !== false ) $map['disponibilidad_galerias'] = $index;
            elseif ( strpos( $h_clean, 'mille' ) !== false ) $map['disponibilidad_millennium'] = $index;
        }

        $data = [];
        // 3. Procesamiento, Limpieza y Cálculo al Vuelo
        while ( ( $row = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
            
            // Extraer disponibilidades asegurando que sean números
            $disp_gale = isset($map['disponibilidad_galerias'], $row[$map['disponibilidad_galerias']]) ? floatval($row[$map['disponibilidad_galerias']]) : 0;
            $disp_mille = isset($map['disponibilidad_millennium'], $row[$map['disponibilidad_millennium']]) ? floatval($row[$map['disponibilidad_millennium']]) : 0;
            
            // ¡Cálculo de Stock Total al vuelo! 
            $stock_total = $disp_gale + $disp_mille; 
            
            // Precios
            $precio_raw = isset($map['precio_venta'], $row[$map['precio_venta']]) ? trim($row[$map['precio_venta']]) : '0';
            $precio_float = floatval( str_replace( '"', '', $precio_raw ) );

            // Extracción de Divisas
            $divisa_raw = isset($map['precio_divisas'], $row[$map['precio_divisas']]) ? trim($row[$map['precio_divisas']]) : '0';
            $divisa_float = floatval( str_replace( '"', '', $divisa_raw ) );

            // Extracción de Velocidad y Cálculo de Autonomía (NUEVO)
            $velocidad_raw = isset($map['velocidad_venta'], $row[$map['velocidad_venta']]) ? floatval($row[$map['velocidad_venta']]) : 0;
            
            // Protección matemática: Evitar división por cero si la velocidad es 0
            $runway_dias = ($velocidad_raw > 0) ? round($stock_total / $velocidad_raw) : 999; 

            $data[] = [
                'sku' => isset($map['sku'], $row[$map['sku']]) ? sanitize_text_field($row[$map['sku']]) : 'N/D',
                'nombre' => isset($map['nombre'], $row[$map['nombre']]) ? sanitize_text_field($row[$map['nombre']]) : 'N/D',
                'precio_venta' => $precio_float,
                'precio_divisas' => $divisa_float, 
                'velocidad_venta' => $velocidad_raw, // <--- Dato extraído del CSV
                'runway_dias' => $runway_dias,       // <--- Indicador de Inteligencia calculado
                'status' => isset($map['status_prediccion'], $row[$map['status_prediccion']]) ? sanitize_text_field($row[$map['status_prediccion']]) : '-',
                'disponibilidad_galerias' => $disp_gale,
                'disponibilidad_millennium' => $disp_mille,
                'stock_total' => $stock_total, 
                'inventario_entrante' => isset($map['inventario_entrante'], $row[$map['inventario_entrante']]) ? sanitize_text_field($row[$map['inventario_entrante']]) : 'No',
            ];
        }

        fclose( $handle );
        $this->send_success( $data );
    }
}
```

### ARCHIVO: `output/Matriz_unificada_Woocommerce.csv`
```csv
sku,nombre,precio_venta,status_prediccion,inventario_entrante,disponibilidad_millennium,disponibilidad_galerias
10167,UNI-T UT336E-KIT Manometro digital con pinzas de temperatura,458.0,NUEVO,No,0.0,0.0

// [NOTA: CSV truncado]

```

### ARCHIVO: `views/app/tab-inventario.php`
```php
<?php
/**
 * Vista: Inventario Global
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>
/* =========================================================
   ESTILOS: CONTROLES SUPERIORES DATATABLES INVENTARIO
   ========================================================= */
.dt-top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
    background: #f8fafc;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* Forzar el selector de registros a una sola línea horizontal */
.dt-top-controls .dataTables_length label {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 10px;
    margin: 0;
    color: #475569;
    font-size: 14px;
    white-space: nowrap;
}

.dt-top-controls .dataTables_length select {
    margin: 0;
    min-width: 70px;
    padding: 4px 8px;
}

/* Ajustes para el texto de información ("Mostrando 1 a 25...") */
.dt-top-controls .dataTables_info {
    padding-top: 0 !important;
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
}
</style>

<div id="TabInventario" class="suite-tab-content" style="display: none;">
    <div class="suite-header-modern" style="padding-bottom: 0; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin:0; font-size: 22px; color: #0f172a;">📦 Inventario</h2>
            <p style="color:#64748b; font-size:14px; margin-top:5px;">Consulta de disponibilidad, precios en tiempo real y predicciones de IA.</p>
        </div>
    </div>
    
    <div style="padding: 25px;">
        <div class="suite-table-responsive">
            <table class="suite-modern-table" id="inventoryTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 12%;">SKU</th>
                        <th style="width: 28%;">Nombre</th>
                        <th style="width: 10%;">Precio</th>
                        <th style="width: 10%;">Precio Divisas</th>
                        <th style="width: 10%; text-align: center;">Status</th>
                        <th style="width: 7%; text-align: center;">Stock</th>
                        <th style="width: 7%; text-align: center;">Galerías</th>
                        <th style="width: 7%; text-align: center;">Millennium</th>
                        <th style="width: 9%; text-align: center;">Tránsito</th>
                    </tr>
                </thead>
                <tbody id="inventory-tbody">
                    </tbody>
            </table>
        </div>
    </div>
</div>
```

