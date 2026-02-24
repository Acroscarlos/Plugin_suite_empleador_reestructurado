/**
 * SuiteAPI - Orquestador de Peticiones AJAX
 * * Centraliza las llamadas al servidor, inyectando automáticamente 
 * credenciales de seguridad (Nonces) y enrutamiento (URL).
 */
const SuiteAPI = (function($) {
    'use strict';

    // Variables privadas extraídas de la localización de WP
    const apiUrl = suite_vars.ajax_url;
    const sysNonce = suite_vars.nonce;

    /**
     * Interceptor Global de Errores
     * Captura expiración de sesión (401) o fallos de permisos (403)
     */
    const handleAjaxError = function(error, reject) {
        if (error.status === 401 || error.status === 403) {
            alert('🔒 Su sesión ha expirado o fue cerrada por seguridad. Por favor, recargue la página e inicie sesión nuevamente.');
        }
        reject(error);
    };

    /**
     * Petición POST estándar para JSON
     * * @param {string} action - El nombre del hook de WP (ej. 'suite_search_client_ajax')
     * @param {object} data - Datos a enviar
     * @returns {Promise}
     */
    const post = function(action, data = {}) {
        return new Promise((resolve, reject) => {
            // Inyección automática de parámetros obligatorios
            const payload = {
                ...data,
                action: action,
                nonce: sysNonce
            };

            $.post(apiUrl, payload)
                .done(response => resolve(response))
                .fail(error => handleAjaxError(error, reject)); // <-- Interceptor inyectado
        });
    };

    /**
     * Petición POST para subir archivos (FormData)
     * Utilizado para la importación de CSV o futuras subidas de fotos (POD)
     * * @param {string} action - El nombre del hook de WP
     * @param {FormData} formData - Objeto FormData instanciado
     * @returns {Promise}
     */
    const postForm = function(action, formData) {
        return new Promise((resolve, reject) => {
            // Inyección en FormData
            formData.append('action', action);
            formData.append('nonce', sysNonce);

            $.ajax({
                url: apiUrl,
                type: 'POST',
                data: formData,
                processData: false, // Vital para que jQuery no procese el archivo
                contentType: false, // Vital para que el navegador asigne el boundary multipart
                success: response => resolve(response),
                error: error => handleAjaxError(error, reject) // <-- Interceptor inyectado
            });
        });
    };

    // API Pública Revelada
    return {
        post: post,
        postForm: postForm
    };

})(jQuery);