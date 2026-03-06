<?php

if (! defined('ABSPATH')) {
    exit;
}

class MCC_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public static function get_defaults() {
        return [
            'openai_api_key'         => '',
            'openai_model'           => 'gpt-5-mini',
            'company_name'           => get_bloginfo('name'),
            'welcome_message'        => 'Hola. Soy el asistente de reservas. Te ayudo a agendar una cita en unos minutos.',
            'google_client_id'       => '',
            'google_client_secret'   => '',
            'google_refresh_token'   => '',
            'google_calendar_id'     => 'primary',
            'timezone'               => wp_timezone_string() ?: 'Europe/Madrid',
            'business_start'         => '09:00',
            'business_end'           => '18:00',
            'appointment_duration'   => 30,
            'appointment_buffer'     => 15,
            'max_slots_to_offer'     => 3,
            'slot_interval'          => 30,
            'widget_title'           => 'Reserva tu cita',
            'widget_button_label'    => 'Reservar cita',
            'primary_color'          => '#111827',
            'collect_phone'          => 0,
        ];
    }

    public static function get_settings() {
        $settings = get_option('mcc_settings', []);
        return wp_parse_args($settings, self::get_defaults());
    }

    public static function get_status($settings = null) {
        if (! is_array($settings)) {
            $settings = self::get_settings();
        }

        $checks = [
            'openai_api_key'       => ! empty($settings['openai_api_key']),
            'openai_model'         => ! empty($settings['openai_model']),
            'google_client_id'     => ! empty($settings['google_client_id']),
            'google_client_secret' => ! empty($settings['google_client_secret']),
            'google_refresh_token' => ! empty($settings['google_refresh_token']),
            'google_calendar_id'   => ! empty($settings['google_calendar_id']),
            'timezone'             => ! empty($settings['timezone']),
        ];

        return [
            'complete' => ! in_array(false, $checks, true),
            'checks'   => $checks,
        ];
    }

    public function add_menu() {
        add_options_page(
            'Mi Chatbot Citas',
            'Mi Chatbot Citas',
            'manage_options',
            'mi-chatbot-citas',
            [$this, 'render_page']
        );
    }

    public function register_settings() {
        register_setting('mcc_settings_group', 'mcc_settings', [$this, 'sanitize']);
    }

    public function sanitize($input) {
        $defaults = self::get_defaults();
        $output   = [];

        $output['openai_api_key']       = isset($input['openai_api_key']) ? sanitize_text_field($input['openai_api_key']) : $defaults['openai_api_key'];
        $output['openai_model']         = isset($input['openai_model']) ? sanitize_text_field($input['openai_model']) : $defaults['openai_model'];
        $output['company_name']         = isset($input['company_name']) ? sanitize_text_field($input['company_name']) : $defaults['company_name'];
        $output['welcome_message']      = isset($input['welcome_message']) ? sanitize_textarea_field($input['welcome_message']) : $defaults['welcome_message'];
        $output['google_client_id']     = isset($input['google_client_id']) ? sanitize_text_field($input['google_client_id']) : $defaults['google_client_id'];
        $output['google_client_secret'] = isset($input['google_client_secret']) ? sanitize_text_field($input['google_client_secret']) : $defaults['google_client_secret'];
        $output['google_refresh_token'] = isset($input['google_refresh_token']) ? sanitize_text_field($input['google_refresh_token']) : $defaults['google_refresh_token'];
        $output['google_calendar_id']   = isset($input['google_calendar_id']) ? sanitize_text_field($input['google_calendar_id']) : $defaults['google_calendar_id'];
        $output['timezone']             = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : $defaults['timezone'];
        $output['business_start']       = isset($input['business_start']) ? sanitize_text_field($input['business_start']) : $defaults['business_start'];
        $output['business_end']         = isset($input['business_end']) ? sanitize_text_field($input['business_end']) : $defaults['business_end'];
        $output['appointment_duration'] = isset($input['appointment_duration']) ? max(5, absint($input['appointment_duration'])) : $defaults['appointment_duration'];
        $output['appointment_buffer']   = isset($input['appointment_buffer']) ? max(0, absint($input['appointment_buffer'])) : $defaults['appointment_buffer'];
        $output['max_slots_to_offer']   = isset($input['max_slots_to_offer']) ? max(1, absint($input['max_slots_to_offer'])) : $defaults['max_slots_to_offer'];
        $output['slot_interval']        = isset($input['slot_interval']) ? max(5, absint($input['slot_interval'])) : $defaults['slot_interval'];
        $output['widget_title']         = isset($input['widget_title']) ? sanitize_text_field($input['widget_title']) : $defaults['widget_title'];
        $output['widget_button_label']  = isset($input['widget_button_label']) ? sanitize_text_field($input['widget_button_label']) : $defaults['widget_button_label'];
        $output['primary_color']        = isset($input['primary_color']) ? sanitize_hex_color($input['primary_color']) : $defaults['primary_color'];
        if (empty($output['primary_color'])) {
            $output['primary_color'] = $defaults['primary_color'];
        }
        $output['collect_phone'] = ! empty($input['collect_phone']) ? 1 : 0;

        return wp_parse_args($output, $defaults);
    }

    public function render_page() {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        $status   = self::get_status($settings);
        ?>
        <div class="wrap">
            <h1>Mi Chatbot Citas</h1>
            <p>Versión <strong><?php echo esc_html(MCC_VERSION); ?></strong>. Inserta el chatbot con <code>[chatbot_citas]</code> o <code>[chatbot_citas mode="floating"]</code>.</p>

            <?php $this->render_status_box($status); ?>

            <form method="post" action="options.php">
                <?php settings_fields('mcc_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mcc_company_name">Nombre del negocio</label></th>
                        <td><input name="mcc_settings[company_name]" id="mcc_company_name" type="text" class="regular-text" value="<?php echo esc_attr($settings['company_name']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_welcome_message">Mensaje de bienvenida</label></th>
                        <td><textarea name="mcc_settings[welcome_message]" id="mcc_welcome_message" rows="4" class="large-text"><?php echo esc_textarea($settings['welcome_message']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_widget_title">Título del widget</label></th>
                        <td><input name="mcc_settings[widget_title]" id="mcc_widget_title" type="text" class="regular-text" value="<?php echo esc_attr($settings['widget_title']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_widget_button_label">Texto del botón</label></th>
                        <td><input name="mcc_settings[widget_button_label]" id="mcc_widget_button_label" type="text" class="regular-text" value="<?php echo esc_attr($settings['widget_button_label']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_primary_color">Color principal</label></th>
                        <td><input name="mcc_settings[primary_color]" id="mcc_primary_color" type="color" value="<?php echo esc_attr($settings['primary_color']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_openai_api_key">OpenAI API key</label></th>
                        <td><input name="mcc_settings[openai_api_key]" id="mcc_openai_api_key" type="password" class="regular-text" value="<?php echo esc_attr($settings['openai_api_key']); ?>" autocomplete="off"><p class="description">Guarda aquí la clave del cliente. Nunca va en el navegador.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_openai_model">Modelo OpenAI</label></th>
                        <td><input name="mcc_settings[openai_model]" id="mcc_openai_model" type="text" class="regular-text" value="<?php echo esc_attr($settings['openai_model']); ?>"><p class="description">Valor por defecto recomendado para esta versión: <code>gpt-5-mini</code>.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_google_client_id">Google Client ID</label></th>
                        <td><input name="mcc_settings[google_client_id]" id="mcc_google_client_id" type="text" class="large-text" value="<?php echo esc_attr($settings['google_client_id']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_google_client_secret">Google Client Secret</label></th>
                        <td><input name="mcc_settings[google_client_secret]" id="mcc_google_client_secret" type="password" class="large-text" value="<?php echo esc_attr($settings['google_client_secret']); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_google_refresh_token">Google Refresh Token</label></th>
                        <td><input name="mcc_settings[google_refresh_token]" id="mcc_google_refresh_token" type="password" class="large-text" value="<?php echo esc_attr($settings['google_refresh_token']); ?>" autocomplete="off"><p class="description">Se obtiene con el OAuth Playground o con un flujo OAuth propio.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_google_calendar_id">Google Calendar ID</label></th>
                        <td><input name="mcc_settings[google_calendar_id]" id="mcc_google_calendar_id" type="text" class="regular-text" value="<?php echo esc_attr($settings['google_calendar_id']); ?>"><p class="description">Usa <code>primary</code> o el ID exacto del calendario.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_timezone">Zona horaria</label></th>
                        <td><input name="mcc_settings[timezone]" id="mcc_timezone" type="text" class="regular-text" value="<?php echo esc_attr($settings['timezone']); ?>"><p class="description">Ejemplo: <code>Europe/Madrid</code>.</p></td>
                    </tr>
                    <tr>
                        <th scope="row">Horario laboral</th>
                        <td>
                            <input name="mcc_settings[business_start]" type="time" value="<?php echo esc_attr($settings['business_start']); ?>">
                            &nbsp; a &nbsp;
                            <input name="mcc_settings[business_end]" type="time" value="<?php echo esc_attr($settings['business_end']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_appointment_duration">Duración de cita (minutos)</label></th>
                        <td><input name="mcc_settings[appointment_duration]" id="mcc_appointment_duration" type="number" min="5" step="5" value="<?php echo esc_attr($settings['appointment_duration']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_appointment_buffer">Margen entre citas (minutos)</label></th>
                        <td><input name="mcc_settings[appointment_buffer]" id="mcc_appointment_buffer" type="number" min="0" step="5" value="<?php echo esc_attr($settings['appointment_buffer']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_slot_interval">Intervalo entre propuestas (minutos)</label></th>
                        <td><input name="mcc_settings[slot_interval]" id="mcc_slot_interval" type="number" min="5" step="5" value="<?php echo esc_attr($settings['slot_interval']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcc_max_slots_to_offer">Número de huecos a ofrecer</label></th>
                        <td><input name="mcc_settings[max_slots_to_offer]" id="mcc_max_slots_to_offer" type="number" min="1" max="10" value="<?php echo esc_attr($settings['max_slots_to_offer']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Pedir teléfono</th>
                        <td><label><input name="mcc_settings[collect_phone]" type="checkbox" value="1" <?php checked($settings['collect_phone'], 1); ?>> Activar</label></td>
                    </tr>
                </table>
                <?php submit_button('Guardar cambios'); ?>
            </form>
        </div>
        <?php
    }

    private function render_status_box($status) {
        $labels = [
            'openai_api_key'       => 'OpenAI API key',
            'openai_model'         => 'Modelo OpenAI',
            'google_client_id'     => 'Google Client ID',
            'google_client_secret' => 'Google Client Secret',
            'google_refresh_token' => 'Google Refresh Token',
            'google_calendar_id'   => 'Google Calendar ID',
            'timezone'             => 'Zona horaria',
        ];
        ?>
        <div class="notice <?php echo $status['complete'] ? 'notice-success' : 'notice-warning'; ?>" style="padding:12px 16px; margin: 16px 0;">
            <p style="margin-top:0;"><strong><?php echo $status['complete'] ? 'Configuración lista.' : 'Configuración incompleta.'; ?></strong></p>
            <p style="margin-bottom:8px;">Antes de entregar o publicar, revisa este bloque. Evita la clásica situación de “el plugin está instalado, pero no reserva nada”.</p>
            <ul style="margin:0; padding-left: 18px;">
                <?php foreach ($labels as $key => $label) : ?>
                    <li>
                        <?php echo $status['checks'][$key] ? '✅' : '⚠️'; ?>
                        <?php echo esc_html($label); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}
