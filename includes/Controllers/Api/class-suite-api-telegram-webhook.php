<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * FASE 4.1: Endpoint Webhook para la API de Telegram
 * Recibe y procesa los Callback Queries (Botones Inline) del equipo financiero.
 */
class Suite_Telegram_Webhook extends WP_REST_Controller {

    protected $namespace = 'suite/v1';
    protected $rest_base = 'telegram-webhook';

    // ⚠️ Token real inyectado y Secreto definido
    private $bot_token = '8190650297:AAEhx-eQygWnbid7mjcSQuN2KV4SigE6k38';
    private $webhook_secret = 'UNIT_FINANZAS_2026'; 

    /**
     * Registra el endpoint público en WordPress
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE, // Acepta POST
                'callback'            => array( $this, 'process_webhook' ),
                'permission_callback' => '__return_true' // Es público, la seguridad se valida con el secret
            )
        ) );
    }

    /**
     * Procesa el Payload entrante de Telegram
     */
    public function process_webhook( WP_REST_Request $request ) {
        // 1. VALIDACIÓN ZERO-TRUST
        $secret = $request->get_param( 'secret' );
        if ( $secret !== $this->webhook_secret ) {
            return new WP_REST_Response( 'Acceso denegado', 403 );
        }

        // 2. EXTRACCIÓN DEL PAYLOAD
        $body = $request->get_json_params(); 

        if ( empty( $body['callback_query'] ) ) {
            return new WP_REST_Response( 'OK', 200 );
        }

        $callback_query = $body['callback_query'];
        $callback_data  = sanitize_text_field( $callback_query['data'] );
        $callback_id    = sanitize_text_field( $callback_query['id'] );
        $chat_id        = sanitize_text_field( $callback_query['message']['chat']['id'] );
        $message_id     = sanitize_text_field( $callback_query['message']['message_id'] );

        // --- MAGIA ZERO-TRUST: ELEVACIÓN TEMPORAL DE PRIVILEGIOS ---
        // Buscamos al primer Administrador del sistema y le "prestamos" su ID
        // a Telegram para que el Modelo no bloquee la escritura en la Base de Datos.
        $admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) );
        if ( ! empty( $admins ) ) {
            wp_set_current_user( $admins[0] );
        }

        // Instanciar el modelo (Usamos class_exists por seguridad para evitar Fatal Errors)
        if ( ! class_exists( 'Suite_Model_Quote' ) ) {
            require_once SUITE_PATH . 'includes/Models/class-suite-model-quote.php';
        }
        $quote_model = new Suite_Model_Quote();

		
	
		
		
		
        $action_msg = '';

        // 3. LÓGICA DE NEGOCIO (CONEXIÓN CON EL ERP)
        if ( strpos( $callback_data, 'approve_payment_' ) === 0 ) {

            $quote_id = intval( str_replace( 'approve_payment_', '', $callback_data ) );
            
            // --- BARRERA ZERO-TRUST: Verificar estado actual antes de actuar ---
            $current_order = $quote_model->get( $quote_id );
            
            if ( ! $current_order || $current_order->estado !== 'pagado' ) {
                $action_msg = '⚠️ Acción denegada: La orden ya no está en estado "Pagado". Posiblemente fue procesada vía Web.';
            } else {
                $quote_model->update_order_status( $quote_id, 'por_enviar' );
                $action_msg = '✅ Pago Aprobado. La orden ha sido enviada a Logística.';
            }

        } elseif ( strpos( $callback_data, 'reject_payment_' ) === 0 ) {

            $quote_id = intval( str_replace( 'reject_payment_', '', $callback_data ) );
            
            // --- BARRERA ZERO-TRUST ---
            $current_order = $quote_model->get( $quote_id );
            
            if ( ! $current_order || $current_order->estado !== 'pagado' ) {
                $action_msg = '⚠️ Acción denegada: La orden ya no está en estado "Pagado".';
            } else {
                $quote_model->update_order_status( $quote_id, 'emitida' );
                $action_msg = '❌ Pago Rechazado. La orden fue devuelta a Pendiente.';
            }

        } else {
			
            return new WP_REST_Response( 'OK', 200 );
        }

        // 4. UX Y SEGURIDAD EN TELEGRAM (Evitar Dobles Clics)

        // A) Detener el "relojito" de carga en el botón pulsado
        $this->telegram_request( 'answerCallbackQuery', array(
            'callback_query_id' => $callback_id,
            'text'              => $action_msg,
            'show_alert'        => false
        ) );

        // B) DESTRUIR los botones del mensaje original (Zero-Trust UI)
        $this->telegram_request( 'editMessageReplyMarkup', array(
            'chat_id'      => $chat_id,
            'message_id'   => $message_id,
            'reply_markup' => array( 'inline_keyboard' => array() ) 
        ) );

        // C) Enviar un mensaje de confirmación al hilo del chat
        $this->telegram_request( 'sendMessage', array(
            'chat_id'             => $chat_id,
            'text'                => "<b>ACTUALIZACIÓN ORDEN #{$quote_id}:</b>\n{$action_msg}",
            'parse_mode'          => 'HTML',
            'reply_to_message_id' => $message_id
        ) );

        return new WP_REST_Response( 'Webhook procesado con éxito', 200 );
    }

    /**
     * Helper privado para consumir la API de Telegram
     */
    private function telegram_request( $method, $parameters ) {
        $url = "https://api.telegram.org/bot{$this->bot_token}/{$method}";

        wp_remote_post( $url, array(
            'body'    => wp_json_encode( $parameters ),
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 10
        ) );
    }
}