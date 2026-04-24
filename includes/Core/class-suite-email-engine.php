<?php
/**
 * SuiteEmailEngine - Motor de Plantillas y Envío de Correos
 */
class Suite_Email_Engine {

    private $brand_color = '#dc2626'; // Rojo UNI-T
    private $bg_light    = '#f8fafc';

    /**
     * Envía notificación de Despacho con enlaces a documentos
     */
    public function send_dispatch_notification($quote_id) {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, cli.email FROM {$wpdb->prefix}suite_cotizaciones c 
             JOIN {$wpdb->prefix}suite_clientes cli ON c.cliente_id = cli.id 
             WHERE c.id = %d", $quote_id
        ));

        if (!$order || empty($order->email)) return false;

        $subject = "🚀 ¡Tu pedido #{$order->codigo_cotizacion} ha sido despachado!";
        
        $content = "
            <p>Hola <strong>{$order->cliente_nombre}</strong>,</p>
            <p>¡Buenas noticias! Tu pedido ha sido procesado por nuestro equipo de logística y ya va camino a su destino.</p>
            <div style='background:#ffffff; border:1px solid #e2e8f0; padding:20px; border-radius:8px; margin:20px 0;'>
                <p style='margin:0;'><strong>Método de Envío:</strong> {$order->tipo_envio}</p>
                <p style='margin:5px 0 0 0;'><strong>Destino:</strong> " . nl2br($order->direccion_envio) . "</p>
            </div>
            <p>Puedes acceder a tus documentos fiscales y de seguimiento aquí:</p>
            <div style='text-align:center; margin:30px 0;'>";
        
        if ($order->factura_fiscal_url) {
            $content .= "<a href='{$order->factura_fiscal_url}' style='background:{$this->brand_color}; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:6px; font-weight:bold; margin:5px; display:inline-block;'>📄 Ver Factura Fiscal</a>";
        }
        
        if ($order->pod_url) {
            $content .= "<a href='{$order->pod_url}' style='background:#0f172a; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:6px; font-weight:bold; margin:5px; display:inline-block;'>📦 Guía de Seguimiento</a>";
        }

        $content .= "</div>";

        return $this->dispatch_mail($order->email, $subject, $content);
    }

    /**
     * Plantilla Maestra HTML (Look & Feel Profesional)
     */
    private function dispatch_mail($to, $subject, $body_content) {
        $logo_url = 'https://mitiendaunit.com/wp-content/uploads/2023/logo-unit.png'; // Ajusta a tu logo real
        
        $html = "
        <div style='font-family:sans-serif; background-color:{$this->bg_light}; padding:40px 10px;'>
            <div style='max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05);'>
                <div style='background:{$this->brand_color}; padding:30px; text-align:center;'>
                    <img src='{$logo_url}' alt='UNI-T' style='max-height:50px;'>
                </div>
                <div style='padding:40px; color:#334155; line-height:1.6;'>
                    <h2 style='color:#0f172a; margin-top:0;'>Información de tu Orden</h2>
                    {$body_content}
                    <hr style='border:0; border-top:1px solid #e2e8f0; margin:30px 0;'>
                    <p style='font-size:13px; color:#64748b;'>Si tienes alguna duda, contáctanos vía WhatsApp respondiendo a este correo o mediante nuestro portal web.</p>
                </div>
                <div style='background:#f1f5f9; padding:20px; text-align:center; font-size:12px; color:#94a3b8;'>
                    &copy; " . date('Y') . " UNI-T Venezuela. Todos los derechos reservados.
                </div>
            </div>
        </div>";

        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: UNI-T Venezuela <ventas@mitiendaunit.com>'];
        return wp_mail($to, $subject, $html, $headers);
    }
}