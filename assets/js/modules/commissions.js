/**
 * SuiteCommissions - Módulo del Dashboard Financiero y Gamificación
 * 
 * Se encarga de solicitar las estadísticas mensuales del vendedor y 
 * actualizar los líderes de los premios en la vista.
 */
const SuiteCommissions = (function($) {
    'use strict';

    // ==========================================
    // MÉTODOS PRIVADOS
    // ==========================================

    const renderDashboard = function(data) {
        // 1. Rendimiento Personal
        $('#dash-mes-actual').text(data.mes_evaluado);
        $('#dash-comision-actual').text('$' + data.comision_actual);

        // 2. Líder "Pez Gordo" (Dinero)
        const pezGordo = data.gamificacion.pez_gordo;
        if (pezGordo) {
            $('#pez-gordo-name').text('👑 ' + pezGordo.display_name);
            $('#pez-gordo-amount').text('$' + pezGordo.total_vendido);
        } else {
            $('#pez-gordo-name').text('Aún sin ventas');
            $('#pez-gordo-amount').text('$0.00');
        }

        // 3. Líder "Deja pa' los demás" (Cantidad)
        const dejaPa = data.gamificacion.deja_pa_los_demas;
        if (dejaPa) {
            $('#deja-pa-name').text('🚀 ' + dejaPa.display_name);
            $('#deja-pa-count').text(dejaPa.cantidad_ventas + ' ventas cerradas');
        } else {
            $('#deja-pa-name').text('Aún sin ventas');
            $('#deja-pa-count').text('0 ventas');
        }
    };

	
	// ==========================================
    // EVENT LISTENERS
    // ==========================================
    const bindEvents = function() {
        
        // Acción de Cierre de Mes (Exclusiva de Gerencia)
        $('#btn-cierre-mes').on('click', function(e) {
            e.preventDefault();
            
            // 1. Confirmación de Doble Vía (Seguridad Anti-Errores)
            const seguro = confirm('⚠️ ATENCIÓN: Esta acción es IRREVERSIBLE.\n\nTodos los registros "pendientes" en el Ledger de Comisiones pasarán a "pagado" y se congelarán.\n\n¿Está absolutamente seguro de proceder con el Cierre Contable de Mes?');
            
            if (!seguro) return;

            const btn = $(this);
            btn.prop('disabled', true).text('⏳ Procesando Cierre...');

            // Formatear fecha actual de corte segura para MySQL (YYYY-MM-DD HH:mm:ss)
            const fechaCorte = new Date().toISOString().slice(0, 19).replace('T', ' ');

            // 2. Disparar el Endpoint AJAX
            SuiteAPI.post('suite_freeze_commissions', {
                fecha_corte: fechaCorte
            }).then(res => {
                if (res.success) {
                    alert('✅ ' + (res.data.message || res.data));
                    location.reload(); // Recarga agresiva para repintar la Billetera a 0
                } else {
                    alert('❌ Error de validación: ' + (res.data.message || res.data));
                    btn.prop('disabled', false).text('🔒 Ejecutar Cierre de Mes');
                }
            }).catch(err => {
                alert('❌ Ocurrió un error crítico de red al intentar congelar el Ledger.');
                btn.prop('disabled', false).text('🔒 Ejecutar Cierre de Mes');
            });
        });
        
    };
	
	
    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        /**
         * Llama a la API para obtener la data fresca y pinta la interfaz
         */
        loadDashboard: function() {
            // Mostrar estado de carga visual sutil
            $('#dash-comision-actual').css('opacity', '0.5');

            SuiteAPI.post('suite_get_dashboard_stats').then(res => {
                if (res.success) {
                    renderDashboard(res.data);
                } else {
                    console.error("Error cargando comisiones:", res.data);
                }
            }).catch(err => {
                console.error("Error de red al cargar dashboard de comisiones.");
            }).finally(() => {
                $('#dash-comision-actual').css('opacity', '1');
            });
        },

        init: function() {
			bindEvents();
            
			// Se puede cargar automáticamente, o esperar a que el usuario haga clic en la pestaña
            // Lo dejamos listo para ser invocado por el controlador de pestañas.
        }
    };

})(jQuery);