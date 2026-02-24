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
            // Se puede cargar automáticamente, o esperar a que el usuario haga clic en la pestaña
            // Lo dejamos listo para ser invocado por el controlador de pestañas.
        }
    };

})(jQuery);