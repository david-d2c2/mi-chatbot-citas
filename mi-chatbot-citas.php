<?php
/**
 * Plugin Name: Mi Chatbot Citas
 * Plugin URI: https://github.com/david-d2c2/mi-chatbot-citas
 * Description: Chatbot de reservas con shortcode para WordPress, conectado a OpenAI y Google Calendar.
 * Version: 1.0.0
 * Author: David Caraballo_D2C2
 * Author URI: https://github.com/david-d2c2
 * Update URI: https://github.com/david-d2c2/mi-chatbot-citas
 * License: GPL-2.0-or-later
 * Text Domain: mi-chatbot-citas
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('MCC_VERSION')) {
    define('MCC_VERSION', '1.0.0');
}

if (! defined('MCC_PLUGIN_FILE')) {
    define('MCC_PLUGIN_FILE', __FILE__);
}

if (! defined('MCC_PLUGIN_DIR')) {
    define('MCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('MCC_PLUGIN_URL')) {
    define('MCC_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once MCC_PLUGIN_DIR . 'includes/class-settings.php';
require_once MCC_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once MCC_PLUGIN_DIR . 'includes/class-rest.php';
require_once MCC_PLUGIN_DIR . 'includes/class-openai.php';
require_once MCC_PLUGIN_DIR . 'includes/class-google-calendar.php';
require_once MCC_PLUGIN_DIR . 'includes/class-slots.php';

final class Mi_Chatbot_Citas_Plugin {
    public function __construct() {
        register_activation_hook(MCC_PLUGIN_FILE, [$this, 'activate']);

        new MCC_Settings();
        new MCC_Shortcode();
        new MCC_REST();
    }

    public function activate() {
        $defaults = MCC_Settings::get_defaults();
        $current  = get_option('mcc_settings', []);
        update_option('mcc_settings', wp_parse_args($current, $defaults));
    }
}

new Mi_Chatbot_Citas_Plugin();
