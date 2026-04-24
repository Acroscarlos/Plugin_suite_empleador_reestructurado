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