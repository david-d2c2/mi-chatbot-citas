<?php

if (! defined('ABSPATH')) {
    exit;
}

class MCC_Shortcode {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode('chatbot_citas', [$this, 'render_shortcode']);
    }

    public function register_assets() {
        wp_register_style(
            'mcc-widget',
            MCC_PLUGIN_URL . 'assets/css/widget.css',
            [],
            filemtime(MCC_PLUGIN_DIR . 'assets/css/widget.css')
        );

        wp_register_script(
            'mcc-widget',
            MCC_PLUGIN_URL . 'assets/js/widget.js',
            [],
            filemtime(MCC_PLUGIN_DIR . 'assets/js/widget.js'),
            true
        );
    }

    public function render_shortcode($atts = []) {
        $settings = MCC_Settings::get_settings();
        $atts = shortcode_atts([
            'mode' => 'inline',
        ], $atts, 'chatbot_citas');

        wp_enqueue_style('mcc-widget');
        wp_enqueue_script('mcc-widget');

        wp_localize_script('mcc-widget', 'mccWidgetConfig', [
            'restUrl'         => esc_url_raw(rest_url('chatbot-citas/v1/chat')),
            'welcomeMessage'  => $settings['welcome_message'],
            'widgetTitle'     => $settings['widget_title'],
            'buttonLabel'     => $settings['widget_button_label'],
            'companyName'     => $settings['company_name'],
            'mode'            => $atts['mode'],
            'primaryColor'    => $settings['primary_color'],
            'placeholder'     => 'Escribe tu mensaje…',
            'sendingLabel'    => 'Pensando…',
            'errorLabel'      => 'Ha ocurrido un error. Inténtalo de nuevo.',
            'introLabel'      => 'Para empezar, dime qué servicio quieres reservar.',
            'sendLabel'       => 'Enviar',
        ]);

        ob_start();
        $mode = $atts['mode'];
        include MCC_PLUGIN_DIR . 'templates/widget.php';
        return ob_get_clean();
    }
}
