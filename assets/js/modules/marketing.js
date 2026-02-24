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

    const processDoughnutData = function(rawData) {
        const aglomerado = {};
        
        // Sumar Volumen USD agrupado estrictamente por Canal
        rawData.forEach(item => {
            const canal = item.canal_venta || 'No Definido';
            if (!aglomerado[canal]) aglomerado[canal] = 0;
            aglomerado[canal] += parseFloat(item.volumen_usd);
        });

        return {
            labels: Object.keys(aglomerado),
            values: Object.values(aglomerado)
        };
    };

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

        // --- 1. GRÁFICO DE DONA (Canales) ---
        const dData = processDoughnutData(data);
        const ctxCanales = document.getElementById('chart-canales-venta').getContext('2d');
        
        chartCanales = new Chart(ctxCanales, {
            type: 'doughnut',
            data: {
                labels: dData.labels,
                datasets: [{
                    data: dData.values,
                    backgroundColor: [
                        '#0073aa', // Azul WP
                        '#10b981', // Verde Éxito
                        '#f59e0b', // Naranja/Amarillo
                        '#8b5cf6', // Púrpura
                        '#dc2626', // Rojo RV
                        '#64748b'  // Gris
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 13 } } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed);
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

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
            // Utilizamos la ruta absoluta al endpoint REST de WordPress
            const restUrl = '/wp-json/suite/v1/ventas-vs-alcance';

            // Usamos jQuery AJAX para aprovechar las cookies de sesión nativas de WP
            $.ajax({
                url: restUrl,
                method: 'GET',
                success: function(res) {
                    if (res.success && res.data) {
                        renderCharts(res.data);
                    }
                },
                error: function(err) {
                    console.error('Error al cargar datos REST del Cerebro de Demanda', err);
                    if(err.status === 401 || err.status === 403) {
                        alert('🔒 Acceso Denegado: Su rol no tiene permisos para ver analíticas.');
                    }
                }
            });
        },

        init: function() {
            // Listo para ser llamado por el enrutador de pestañas
        }
    };

})(jQuery);