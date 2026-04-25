# 🧩 MÓDULO LÓGICO: Slice_F_Marketing_BI

### ARCHIVO: `assets/js/modules/marketing.js`
```js
/**
 * SuiteMarketing - Módulo de Data Analytics (BI)
 * 
 * Se conecta al endpoint REST de la Suite y renderiza gráficos 
 * interactivos con Chart.js
 */
const SuiteMarketing = (function($) {
    'use strict';

    // Instancias globales para poder destruirlas y repintarlas
    let chartCanales = null;
    let chartTendencias = null;

    // ==========================================
    // MÉTODOS DE PROCESAMIENTO DE DATOS
    // ==========================================

    

    const processLineData = function(rawData) {
        const aglomerado = {};
        
        // Sumar Cantidad de Operaciones agrupado estrictamente por Fecha
        rawData.forEach(item => {
            const fecha = item.fecha; // Formato YYYY-MM-DD
            if (!aglomerado[fecha]) aglomerado[fecha] = 0;
            aglomerado[fecha] += parseInt(item.cantidad_operaciones);
        });

        // Ordenar fechas cronológicamente
        const fechasOrdenadas = Object.keys(aglomerado).sort();
        const valoresOrdenados = fechasOrdenadas.map(f => aglomerado[f]);

        return {
            labels: fechasOrdenadas,
            values: valoresOrdenados
        };
    };

    // ==========================================
    // RENDERIZADO DE CHART.JS
    // ==========================================

    const renderCharts = function(data) {
        // Destruir gráficos previos si el usuario hace clic en "Refrescar"
        if (chartCanales) chartCanales.destroy();
        if (chartTendencias) chartTendencias.destroy();

        

        // --- 2. GRÁFICO DE LÍNEAS (Tendencias) ---
        const lData = processLineData(data);
        const ctxTendencias = document.getElementById('chart-tendencia-ventas').getContext('2d');

        chartTendencias = new Chart(ctxTendencias, {
            type: 'line',
            data: {
                labels: lData.labels,
                datasets: [{
                    label: 'Operaciones Cerradas',
                    data: lData.values,
                    borderColor: '#dc2626', // Rojo corporativo
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#dc2626',
                    pointRadius: 4,
                    fill: true,
                    tension: 0.3 // Hace que la línea sea curva y suave
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    };

    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        loadDashboard: function() {
            // Utilizamos la ruta dinámica al endpoint REST de WordPress
            const restUrl = suite_vars.rest_url + 'suite/v1/ventas-vs-alcance';

            // Usamos jQuery AJAX inyectando el Nonce nativo de la API REST
            $.ajax({
                url: restUrl,
                method: 'GET',
                headers: { 
                    'X-WP-Nonce': suite_vars.rest_nonce 
                },
				
				
				
                success: function(res) {
                    if (res.success && res.data) {
                        // 1. Renderizar Gráfico de Líneas (Tendencia)
                        // Ya no renderizamos la dona, así que solo llamamos a la función
                        // que procesa la línea. Vamos a ignorar el error si 'chart-canales-venta' no existe.
                        try {
                            renderCharts(res.data);
                        } catch(e) {
                            console.log("Aviso: Gráfico de dona removido del DOM.");
                        }

                        // 2. Procesar Inteligencia Táctica
                        if (res.tactics) {
                            // ... (Gancho y Sobrestock igual que antes)
                            if (res.tactics.estrella) {
                                $('#bi-estrella-nombre').text(res.tactics.estrella.producto_nombre);
                            } else {
                                $('#bi-estrella-nombre').text('Sin data de ventas.');
                            }

                            if (res.tactics.sobrestock) {
                                $('#bi-sobrestock-nombre').text(res.tactics.sobrestock.nombre_producto);
                                $('#bi-sobrestock-qty').text(res.tactics.sobrestock.stock_total);
                            } else {
                                $('#bi-sobrestock-nombre').text('Inventario Saludable ✅');
                                $('#bi-sobrestock-qty').text('');
                            }
                            
                            // 3. Tarjeta de Combo (Sin Copy)
                            if (res.tactics.combo) {
                                $('#bi-combo-sugerido').html(`
                                    <span style="color:#059669; font-size:13px;">⭐ ${res.tactics.combo.gancho}</span><br>
                                    <span style="color:#64748b; font-size:11px;">➕ (En combo con)</span><br>
                                    <span style="color:#dc2626; font-size:13px;">📦 ${res.tactics.combo.impulso}</span>
                                `);
                            } else {
                                $('#bi-combo-sugerido').text('Datos insuficientes para generar combos.');
                            }

                            // 4. Llenar Tabla Top 5 (NUEVAS COLUMNAS DE VELOCIDAD)
                            if (res.tactics.top5 && res.tactics.top5.length > 0) {
                                let htmlTop5 = '';
                                let totalRunway = 0;
                                let productosValidosParaPromedio = 0;

                                res.tactics.top5.forEach(prod => {
                                    // Asegurarnos de que las variables existan (fallback a 0 o 999)
                                    let velocidad = parseFloat(prod.velocidad_venta || 0).toFixed(2);
                                    let runway = parseInt(prod.runway_dias || 999);
                                    
                                    // Lógica del Semáforo para la fila
                                    let colorRunway = '#64748b'; // Gris (Estancado > 60)
                                    let etiquetaRunway = runway + ' días';
                                    
                                    if (runway < 15) {
                                        colorRunway = '#ef4444'; // Rojo (Crítico)
                                        etiquetaRunway = `<b>${runway} días</b> ⚠️`;
                                    } else if (runway >= 15 && runway <= 60) {
                                        colorRunway = '#10b981'; // Verde (Sano)
                                    } else if (runway >= 999) {
                                        etiquetaRunway = 'Estancado';
                                    }

                                    // Sumar para el promedio del Panel Global (ignorar estancados puros)
                                    if(runway < 999) {
                                        totalRunway += runway;
                                        productosValidosParaPromedio++;
                                    }

                                    htmlTop5 += `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 12px; font-weight: 500; color: #0f172a;">${prod.producto_nombre}</td>
                                            <td style="padding: 12px; text-align: center; color: #475569;">${prod.qty}</td>
                                            <td style="padding: 12px; text-align: right; font-weight: bold; color: #059669;">$${parseFloat(prod.ingresos).toFixed(2)}</td>
                                            <td style="padding: 12px; text-align: center; border-left: 1px dashed #cbd5e1; color: #64748b;">${velocidad} u/d</td>
                                            <td style="padding: 12px; text-align: center; color: ${colorRunway};">${etiquetaRunway}</td>
                                        </tr>
                                    `;
                                });
                                $('#bi-top5-body').html(htmlTop5);

                                // 5. Actualizar el Instrumento de "Nave Espacial" (Promedio Global)
                                if(productosValidosParaPromedio > 0) {
                                    let promedioRunway = Math.round(totalRunway / productosValidosParaPromedio);
                                    $('#bi-global-runway').text(promedioRunway);

                                    let barColor = '#10b981';
                                    let barWidth = '50%';
                                    let adviceText = '🟢 Ritmo saludable. Mantener presupuesto de promocion actual.';

                                    if(promedioRunway < 15) {
                                        barColor = '#ef4444'; // Rojo
                                        barWidth = '15%';
                                        adviceText = '🔴 ALERTA: Quiebre de stock inminente en Top Ventas. Pausar promocion publicitaria.';
                                    } else if (promedioRunway > 60) {
                                        barColor = '#f59e0b'; // Amarillo/Naranja
                                        barWidth = '90%';
                                        adviceText = '🟠 ADVERTENCIA: Rotación muy lenta en el Top 5. Inyectar tráfico o armar combos.';
                                    }

                                    $('#bi-global-runway').css('color', barColor).css('text-shadow', `0 0 10px ${barColor}40`);
                                    $('#bi-runway-bar').css('background', barColor).css('width', barWidth);
                                    $('#bi-runway-advice').html(adviceText).css('color', barColor).css('background', `${barColor}15`).css('border', `1px solid ${barColor}30`);

                                } else {
                                    $('#bi-global-runway').text('--');
                                    $('#bi-runway-advice').text('Datos insuficientes para calcular autonomía.');
                                }

                            } else {
                                $('#bi-top5-body').html('<tr><td colspan="5" style="text-align: center; padding: 15px; color: #94a3b8;">Sin ventas en los últimos 30 días.</td></tr>');
                            }
                        }
                    }
                },
				
				
				
				
                error: function(err) {
                    if(err.status === 401 || err.status === 403) {
                        alert('🔒 Acceso Denegado: Su rol no tiene permisos para ver analíticas o su sesión expiró.');
                    } else {
                        $('#chart-tendencia-ventas').parent().html('<div style="padding:20px; color:#dc2626;">Error al cargar datos.</div>');
                    }
                }
            });
        },

        init: function() {
            // Listo para ser llamado por el enrutador de pestañas
        }
    };

})(jQuery);
```

### ARCHIVO: `includes/Controllers/Api/class-suite-api-stats.php`
```php
<?php
/**
 * Controlador API REST: Estadísticas, Marketing e Inteligencia Artificial
 *
 * Expone endpoints estandarizados para integraciones con PowerBI, Python (Pandas/Prophet)
 * y herramientas de análisis de Marketing.
 *
 * @package SuiteEmpleados\Controllers\Api
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_API_Stats {

    /**
     * Registra las rutas REST al inicializar la API
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Define el namespace y los endpoints
     */
    public function register_routes() {
        $namespace = 'suite/v1';

        // Endpoint 1: Rendimiento de Canales de Venta (Marketing)
        register_rest_route( $namespace, '/ventas-vs-alcance', [
            'methods'             => WP_REST_Server::READABLE, // GET
            'callback'            => [ $this, 'get_ventas_vs_alcance' ],
            'permission_callback' => [ $this, 'check_permissions' ]
        ] );

        // Endpoint 2: Exportación de Series de Tiempo para Machine Learning (Prophet)
        register_rest_route( $namespace, '/export-prophet', [
            'methods'             => WP_REST_Server::READABLE, // GET
            'callback'            => [ $this, 'export_prophet_data' ],
            'permission_callback' => [ $this, 'check_permissions' ]
        ] );
    }

    /**
     * Middleware de Seguridad: Control de Accesos
     * Solo permite a Administradores o al nuevo rol 'suite_marketing'
     *
     * @return bool|WP_Error
     */
	public function check_permissions() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_unauthorized', 'Debe iniciar sesión para acceder a la API.', [ 'status' => 401 ] );
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;

		
		
		
        // VERIFICACIÓN CORREGIDA: Usa current_user_can() para validar la bandera universal
        if ( current_user_can( 'manage_options' ) || current_user_can( 'suite_view_marketing' ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', 'Acceso denegado. Se requiere nivel de Marketing o Administrador.', [ 'status' => 403 ] );
    }
	
	
	

    /**
     * Endpoint: Agrupa las ventas cerradas por Fecha y Canal de Venta (Últimos 30 días)
     * Ideal para graficar en Chart.js o cruzar con inversión publicitaria (CPA/ROAS).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_ventas_vs_alcance( $request ) {
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';

        // Consulta SQL para agrupar ventas efectivas
        $sql = "
            SELECT 
                DATE(fecha_emision) as fecha, 
                canal_venta, 
                COUNT(id) as cantidad_operaciones, 
                SUM(total_usd) as volumen_usd
            FROM {$tabla_cot}
            WHERE estado IN ('pagado', 'despachado') 
            AND fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(fecha_emision), canal_venta
            ORDER BY fecha ASC
        ";

        $resultados = $wpdb->get_results( $sql );

		
		
        // Formatear nulos a canales "No Definidos" y castear tipos para JSON
        foreach ( $resultados as $r ) {
            $r->canal_venta = empty( $r->canal_venta ) ? 'Orgánico / No definido' : $r->canal_venta;
            $r->cantidad_operaciones = intval( $r->cantidad_operaciones );
            $r->volumen_usd = floatval( $r->volumen_usd );
        }
		
		
		
		
		// --- INYECCIÓN DE INTELIGENCIA TÁCTICA (V8.6) ---
        $tabla_items = $wpdb->prefix . 'suite_cotizaciones_items';
        $tabla_inv = $wpdb->prefix . 'suite_inventario_cache';

        // 1. Producto Estrella (Top Ventas por VOLUMEN, no por dinero) para combos lógicos
        $estrella = $wpdb->get_row("
            SELECT i.sku, i.producto_nombre, SUM(i.cantidad) as qty
            FROM {$tabla_items} i
            JOIN {$tabla_cot} c ON i.cotizacion_id = c.id
            WHERE c.estado IN ('pagado', 'despachado', 'completado') 
            AND c.fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY i.sku
            ORDER BY qty DESC LIMIT 1
        ");

        // 2. Top 5 de Enfoque para Pauta Publicitaria
        // [AGREGADO: i.sku para poder cruzar los datos]
        $top5 = $wpdb->get_results("
            SELECT i.sku, i.producto_nombre, SUM(i.cantidad) as qty, SUM(i.subtotal_usd) as ingresos
            FROM {$tabla_items} i
            JOIN {$tabla_cot} c ON i.cotizacion_id = c.id
            WHERE c.estado IN ('pagado', 'despachado', 'completado') 
            AND c.fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY i.sku
            ORDER BY ingresos DESC, qty DESC LIMIT 5
        ");

        // 3. LECTURA DEL CSV (La Fuente de la Verdad Absoluta para Velocidad)
        $csv_path = SUITE_PATH . 'output/Matriz_unificada_Woocommerce.csv';
        $sobrestock = null;
        $diccionario_csv = [];

        if ( file_exists( $csv_path ) && ( $handle = fopen( $csv_path, 'r' ) ) !== false ) {
            $headers = fgetcsv( $handle, 2000, ',' );
            $map = [];
            
            // Mapeo inteligente de las columnas del CSV
            foreach ( $headers as $index => $header ) {
                $h_clean = strtolower( trim( $header ) );
                if ( strpos( $h_clean, 'sku' ) !== false ) $map['sku'] = $index;
                elseif ( strpos( $h_clean, 'nombre' ) !== false ) $map['nombre'] = $index;
                elseif ( strpos( $h_clean, 'velocidad' ) !== false ) $map['velocidad'] = $index;
                elseif ( strpos( $h_clean, 'status' ) !== false ) $map['status'] = $index;
                elseif ( strpos( $h_clean, 'gale' ) !== false ) $map['gale'] = $index;
                elseif ( strpos( $h_clean, 'mille' ) !== false ) $map['mille'] = $index;
            }

            // Escanear el CSV fila por fila
            while ( ( $row = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
                $sku = isset($map['sku'], $row[$map['sku']]) ? trim($row[$map['sku']]) : '';
                if ( $sku ) {
                    // Extraer métricas clave
                    $vel = isset($map['velocidad'], $row[$map['velocidad']]) ? floatval($row[$map['velocidad']]) : 0;
                    $gale = isset($map['gale'], $row[$map['gale']]) ? floatval($row[$map['gale']]) : 0;
                    $mille = isset($map['mille'], $row[$map['mille']]) ? floatval($row[$map['mille']]) : 0;
                    $stock_total = $gale + $mille;
                    $status = isset($map['status'], $row[$map['status']]) ? trim($row[$map['status']]) : '';
                    
                    // A) Guardar en memoria para dárselo luego al Top 5
                    $diccionario_csv[$sku] = [
                        'velocidad' => $vel,
                        'stock' => $stock_total
                    ];

                    // B) Capturar el producto con MAYOR SOBRESTOCK real para el Combo
                    if ( $status === 'SOBRESTOCK' && $stock_total > 0 ) {
                        if ( is_null($sobrestock) || $stock_total > $sobrestock->stock_total ) {
                            $sobrestock = (object) [
                                'nombre_producto' => isset($map['nombre'], $row[$map['nombre']]) ? trim($row[$map['nombre']]) : 'N/D',
                                'stock_total' => $stock_total
                            ];
                        }
                    }
                }
            }
            fclose($handle);
        }

        // 4. Cruzar la base de datos de Ventas con el Diccionario extraído del CSV
        foreach ( $top5 as $item ) {
            $sku = $item->sku;
            if ( isset($diccionario_csv[$sku]) ) {
                // Si el producto está en el Excel, inyectar velocidad y calcular la Autonomía (Runway)
                $item->velocidad_venta = $diccionario_csv[$sku]['velocidad'];
                $stock_actual = $diccionario_csv[$sku]['stock'];
                
                // Matemática Predictiva de Supervivencia
                $item->runway_dias = ($item->velocidad_venta > 0) ? round($stock_actual / $item->velocidad_venta) : 999;
            } else {
                // Failsafe: Si no está en el Excel por alguna razón
                $item->velocidad_venta = 0;
                $item->runway_dias = 999; 
            }
        }

        // 5. Algoritmo Sugeridor de Combos (Limpio)
        $combo = null;
        if ( $estrella && $sobrestock ) {
            $combo = [
                'gancho'  => $estrella->producto_nombre,
                'impulso' => $sobrestock->nombre_producto
            ];
        }

        // 6. ¡Despegue! Enviar datos combinados a JavaScript
        return rest_ensure_response( [
            'success' => true,
            'periodo' => 'Últimos 30 días',
            'data'    => $resultados,
            'tactics' => [
                'estrella'   => $estrella,
                'sobrestock' => $sobrestock,
                'combo'      => $combo,
                'top5'       => $top5
            ]
        ] );
    }

    /**
     * Endpoint: Extrae el Data Lake para Pandas / Facebook Prophet
     * Usa la nomenclatura estricta 'ds' (Datestamp) e 'y' (Variable objetivo)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function export_prophet_data( $request ) {
        global $wpdb;
        $tabla_historico = $wpdb->prefix . 'suite_inventario_historico';

        // Facebook Prophet requiere obligatoriamente que la fecha se llame 'ds'
        // y la métrica a predecir se llame 'y'. En este caso, predeciremos la caída de stock (demanda).
        $sql = "
            SELECT 
                fecha_snapshot as ds, 
                stock_disponible as y, 
                sku, 
                precio, 
                categoria
            FROM {$tabla_historico}
            ORDER BY ds ASC, sku ASC
        ";

        $resultados = $wpdb->get_results( $sql );

        // Casteo estricto para que Pandas no falle al procesar JSON
        foreach ( $resultados as $r ) {
            $r->y = intval( $r->y );
            $r->precio = floatval( $r->precio );
        }

        return rest_ensure_response( [
            'success' => true,
            'model'   => 'Facebook Prophet Compatible',
            'data'    => $resultados
        ] );
    }
}
```

### ARCHIVO: `views/app/tab-marketing.php`
```php
<?php
/**
 * Vista: Cerebro de Demanda y Marketing (Módulo 5)
 * * Muestra visualizaciones de BI (Business Intelligence) utilizando Chart.js 
 * y alimentándose del Data Lake (REST API).
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="TabMarketing" class="suite-tab-content" style="display: none;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
        <div>
            <h2 style="margin:0; color:#0f172a; font-size: 24px; font-weight: 800;">📈 Cerebro de Demanda (BI & Marketing)</h2>
            <p style="color:#64748b; margin-top:5px; font-size: 14px;">Inteligencia Predictiva, Velocidad de Ventas y Rendimiento Publicitario.</p>
        </div>
        <button class="btn-modern-action" onclick="SuiteMarketing.loadDashboard()">🔄 Refrescar Datos</button>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid #f59e0b; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 5px 0; color:#475569; font-size:12px; text-transform:uppercase;">🔥 Gancho Publicitario Ideal</h4>
            <div id="bi-estrella-nombre" style="font-size:15px; font-weight:bold; color:#0f172a; margin-bottom:5px;">Cargando...</div>
            <div style="font-size:12px; color:#64748b;">Producto con mayores ingresos en los últimos 30 días.</div>
        </div>

        <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid #ef4444; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 5px 0; color:#475569; font-size:12px; text-transform:uppercase;">⚠️ Urge Crear Oferta</h4>
            <div id="bi-sobrestock-nombre" style="font-size:15px; font-weight:bold; color:#0f172a; margin-bottom:5px;">Cargando...</div>
            <div style="font-size:12px; color:#64748b;">En riesgo de obsolescencia. <span id="bi-sobrestock-qty" style="font-weight:bold; color:#ef4444;">0</span> unidades paradas.</div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid #8b5cf6; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 5px 0; color:#475569; font-size:12px; text-transform:uppercase;">💡 Combo Sugerido (IA)</h4>
            <div id="bi-combo-sugerido" style="font-size:14px; font-weight:bold; color:#0f172a; line-height:1.4; margin-top: 10px;">Cargando estrategia...</div>
        </div>
    </div>
    
    <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 15px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
            🎯 Top 5: Enfoque Promociones Publicitarias (Ads)
        </h3>
        <div class="suite-table-responsive" style="margin-top: 15px;">
            <table class="suite-modern-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 13px;">
                        <th style="padding: 10px;">Producto / Modelo</th>
                        <th style="padding: 10px; text-align: center;">Unidades Vendidas</th>
                        <th style="padding: 10px; text-align: right;">Ingresos (USD)</th>
                        <th style="padding: 10px; text-align: center; border-left: 1px dashed #cbd5e1;">Velocidad Diaria</th>
                        <th style="padding: 10px; text-align: center;">Autonomía</th>
                    </tr>
                </thead>
                <tbody id="bi-top5-body">
                    <tr><td colspan="5" style="text-align: center; padding: 15px; color: #94a3b8;">Cargando métricas y proyecciones...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        
        <div style="background: #1e293b; padding: 25px; border-radius: 12px; border: 1px solid #334155; box-shadow: inset 0 2px 10px rgba(0,0,0,0.5); color: white;">
            <h3 style="margin-top: 0; color: #94a3b8; font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #334155; padding-bottom: 10px; letter-spacing: 1px;">
                <i class="dashicons dashicons-dashboard" style="vertical-align: middle;"></i> Estado Global del Inventario Activo
            </h3>
            
            <div style="display: flex; justify-content: center; align-items: center; flex-direction: column; height: 260px; margin-top: 15px;">
                <div style="font-size: 14px; color: #cbd5e1; margin-bottom: 5px;">PROMEDIO DE AUTONOMÍA GLOBAL</div>
                
                <div id="bi-global-runway" style="font-size: 72px; font-weight: 900; font-family: monospace; color: #10b981; text-shadow: 0 0 10px rgba(16, 185, 129, 0.4); line-height: 1;">--</div>
                
                <div style="font-size: 16px; color: #94a3b8; margin-top: -5px; margin-bottom: 20px;">DÍAS DE STOCK RESTANTE</div>
                
                <div style="width: 80%; height: 8px; background: #334155; border-radius: 4px; overflow: hidden; position: relative;">
                    <div id="bi-runway-bar" style="height: 100%; width: 50%; background: #10b981; border-radius: 4px; transition: width 0.5s ease, background 0.5s ease;"></div>
                </div>
                
                <div id="bi-runway-advice" style="margin-top: 20px; font-size: 14px; font-weight: bold; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 8px 15px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.2);">
                    Cargando directriz del sistema...
                </div>
            </div>
        </div>

        <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <h3 style="margin-top: 0; color: #1e293b; font-size: 15px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                Tendencia de Cierres de Venta en el Tiempo
            </h3>
            <div style="position: relative; height: 320px; width: 100%; margin-top: 15px;">
                <canvas id="chart-tendencia-ventas"></canvas>
            </div>
        </div>

    </div>
    
</div>
```

