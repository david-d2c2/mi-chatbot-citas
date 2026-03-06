<?php

if (! defined('ABSPATH')) {
    exit;
}

class MCC_REST {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('chatbot-citas/v1', '/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_chat'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_chat(WP_REST_Request $request) {
        $message    = sanitize_textarea_field((string) $request->get_param('message'));
        $session_id = sanitize_key((string) $request->get_param('session_id'));

        if (empty($session_id)) {
            $session_id = wp_generate_password(12, false, false);
        }

        $rate_key = 'mcc_rate_' . md5($session_id . '|' . $this->get_user_ip());
        $rate     = (int) get_transient($rate_key);

        if ($rate > 40) {
            return new WP_REST_Response([
                'reply'      => 'Demasiados mensajes seguidos. Espera un momento y vuelve a intentarlo.',
                'session_id' => $session_id,
            ], 429);
        }

        set_transient($rate_key, $rate + 1, MINUTE_IN_SECONDS);

        $state = $this->get_state($session_id);

        if (empty($message) && empty($state['initialized'])) {
            $state['initialized'] = true;
            $this->save_state($session_id, $state);

            $settings = MCC_Settings::get_settings();
            return new WP_REST_Response([
                'reply'      => $settings['welcome_message'] . "\n\nPara empezar, dime qué servicio quieres reservar.",
                'session_id' => $session_id,
                'state'      => $state,
            ]);
        }

        if (empty($message)) {
            return new WP_REST_Response([
                'reply'      => 'Escríbeme un mensaje para continuar con la reserva.',
                'session_id' => $session_id,
                'state'      => $state,
            ]);
        }

        $reply = $this->process_booking_flow($message, $state, $session_id);

        return new WP_REST_Response([
            'reply'      => $reply,
            'session_id' => $session_id,
            'state'      => $this->get_state($session_id),
        ]);
    }

    private function get_user_ip() {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (! empty($_SERVER[$key])) {
                $value = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
        }
        return '0.0.0.0';
    }

    private function get_state($session_id) {
        $state = get_transient('mcc_state_' . $session_id);

        if (! is_array($state)) {
            $state = [
                'initialized'     => false,
                'step'            => 'ask_service',
                'name'            => '',
                'email'           => '',
                'phone'           => '',
                'service'         => '',
                'preferred_date'  => '',
                'time_preference' => '',
                'specific_time'   => '',
                'available_slots' => [],
                'selected_slot'   => null,
                'booked_event'    => null,
                'history'         => [],
            ];
        }

        return $state;
    }

    private function save_state($session_id, $state) {
        set_transient('mcc_state_' . $session_id, $state, DAY_IN_SECONDS);
    }

    private function reset_state($session_id) {
        delete_transient('mcc_state_' . $session_id);
    }

    private function process_booking_flow($message, $state, $session_id) {
        $settings = MCC_Settings::get_settings();

        if ($this->is_reset_message($message)) {
            $this->reset_state($session_id);
            return $settings['welcome_message'] . "\n\nPerfecto. Empezamos de nuevo. ¿Qué servicio quieres reservar?";
        }

        $parsed = MCC_OpenAI::parse_user_message($message, $state, $settings);

        $state['history'][] = [
            'user'   => $message,
            'parsed' => $parsed,
            'at'     => current_time('mysql'),
        ];
        $state['history'] = array_slice($state['history'], -12);

        if (! empty($parsed['fields']['name']) && empty($state['name'])) {
            $state['name'] = sanitize_text_field($parsed['fields']['name']);
        }
        if (! empty($parsed['fields']['email']) && empty($state['email'])) {
            $state['email'] = sanitize_email($parsed['fields']['email']);
        }
        if (! empty($parsed['fields']['phone']) && empty($state['phone'])) {
            $state['phone'] = sanitize_text_field($parsed['fields']['phone']);
        }
        if (! empty($parsed['fields']['service']) && empty($state['service'])) {
            $state['service'] = sanitize_text_field($parsed['fields']['service']);
        }
        if (! empty($parsed['fields']['preferred_date'])) {
            $state['preferred_date'] = sanitize_text_field($parsed['fields']['preferred_date']);
        }
        if (! empty($parsed['fields']['time_preference'])) {
            $state['time_preference'] = sanitize_text_field($parsed['fields']['time_preference']);
        }
        if (! empty($parsed['fields']['specific_time'])) {
            $state['specific_time'] = sanitize_text_field($parsed['fields']['specific_time']);
        }

        if (! empty($state['email']) && ! is_email($state['email'])) {
            $state['email'] = '';
        }

        switch ($state['step']) {
            case 'ask_service':
                if (empty($state['service'])) {
                    if (! empty($message)) {
                        $state['service'] = sanitize_text_field($message);
                    }
                }
                $state['step'] = 'ask_name';
                $this->save_state($session_id, $state);
                return 'Perfecto. ¿A nombre de quién debo agendar la cita?';

            case 'ask_name':
                if (empty($state['name'])) {
                    $state['name'] = sanitize_text_field($message);
                }
                $state['step'] = 'ask_email';
                $this->save_state($session_id, $state);
                return 'Gracias, ' . $state['name'] . '. ¿Cuál es tu email?';

            case 'ask_email':
                if (empty($state['email'])) {
                    $candidate = sanitize_email($message);
                    if (! is_email($candidate)) {
                        $this->save_state($session_id, $state);
                        return 'Necesito un email válido para enviar la referencia de la cita. Escríbelo, por favor.';
                    }
                    $state['email'] = $candidate;
                }

                if (! empty($settings['collect_phone'])) {
                    $state['step'] = 'ask_phone';
                    $this->save_state($session_id, $state);
                    return 'Perfecto. ¿Cuál es tu teléfono?';
                }

                $state['step'] = 'ask_date';
                $this->save_state($session_id, $state);
                return 'Genial. ¿Qué día te viene bien? Puedes decirme una fecha exacta como 2026-03-10.';

            case 'ask_phone':
                if (empty($state['phone'])) {
                    $state['phone'] = sanitize_text_field($message);
                }
                $state['step'] = 'ask_date';
                $this->save_state($session_id, $state);
                return 'Perfecto. ¿Qué día te viene bien? Puedes decirme una fecha exacta como 2026-03-10.';

            case 'ask_date':
                if (empty($state['preferred_date'])) {
                    $date = MCC_OpenAI::extract_date_fallback($message);
                    if (! empty($date)) {
                        $state['preferred_date'] = $date;
                    }
                }

                if (empty($state['preferred_date'])) {
                    $this->save_state($session_id, $state);
                    return 'Necesito una fecha clara para seguir. Escríbela en formato YYYY-MM-DD o dime algo como “mañana por la tarde” si tienes OpenAI bien configurado.';
                }

                if (! $this->is_valid_future_date($state['preferred_date'])) {
                    $state['preferred_date'] = '';
                    $this->save_state($session_id, $state);
                    return 'La fecha debe tener formato YYYY-MM-DD y no puede estar en el pasado. Prueba con otra.';
                }

                $state['step'] = 'ask_time';
                $this->save_state($session_id, $state);
                return 'Perfecto. ¿Prefieres mañana, tarde o una hora concreta?';

            case 'ask_time':
                if (empty($state['time_preference']) && empty($state['specific_time'])) {
                    $fallback_time = MCC_OpenAI::extract_time_preference_fallback($message);
                    if (! empty($fallback_time['time_preference'])) {
                        $state['time_preference'] = $fallback_time['time_preference'];
                    }
                    if (! empty($fallback_time['specific_time'])) {
                        $state['specific_time'] = $fallback_time['specific_time'];
                    }
                }

                if (empty($state['time_preference']) && empty($state['specific_time'])) {
                    $this->save_state($session_id, $state);
                    return 'Indícame si prefieres mañana, tarde o una hora concreta. Por ejemplo: 10:30.';
                }

                $calendar = new MCC_Google_Calendar($settings);
                $slots    = $calendar->get_available_slots([
                    'date'             => $state['preferred_date'],
                    'time_preference'  => $state['time_preference'],
                    'specific_time'    => $state['specific_time'],
                    'duration'         => (int) $settings['appointment_duration'],
                    'buffer'           => (int) $settings['appointment_buffer'],
                    'slot_interval'    => (int) $settings['slot_interval'],
                    'max_slots'        => (int) $settings['max_slots_to_offer'],
                ]);

                if (is_wp_error($slots)) {
                    $this->save_state($session_id, $state);
                    return 'No he podido consultar Google Calendar ahora mismo. Revisa la configuración del plugin y las credenciales de Google.';
                }

                if (empty($slots)) {
                    $state['preferred_date']  = '';
                    $state['time_preference'] = '';
                    $state['specific_time']   = '';
                    $state['step']            = 'ask_date';
                    $this->save_state($session_id, $state);
                    return 'No veo huecos disponibles con esas preferencias. Dime otra fecha y lo intentamos de nuevo.';
                }

                $state['available_slots'] = $slots;
                $state['step']            = 'choose_slot';
                $this->save_state($session_id, $state);
                return $this->format_slots_reply($slots, $settings['timezone']);

            case 'choose_slot':
                $slot_index = null;
                if (isset($parsed['fields']['slot_index']) && $parsed['fields']['slot_index'] !== '') {
                    $slot_index = (int) $parsed['fields']['slot_index'];
                } else {
                    $slot_index = MCC_OpenAI::extract_slot_index_fallback($message);
                }

                if ($slot_index < 1 || $slot_index > count($state['available_slots'])) {
                    $this->save_state($session_id, $state);
                    return 'Responde con el número de la opción que prefieras. Por ejemplo: 1.';
                }

                $selected = $state['available_slots'][$slot_index - 1];
                $state['selected_slot'] = $selected;
                $state['step']          = 'confirm';
                $this->save_state($session_id, $state);

                return sprintf(
                    "Perfecto. Voy a reservar esto:\n\n- Servicio: %s\n- Nombre: %s\n- Email: %s\n- Fecha y hora: %s\n\nResponde sí para confirmar o no para elegir otro hueco.",
                    $state['service'],
                    $state['name'],
                    $state['email'],
                    $this->format_datetime_for_human($selected['start'], $settings['timezone'])
                );

            case 'confirm':
                if ($this->is_negative_message($message, $parsed)) {
                    $state['selected_slot'] = null;
                    $state['step']          = 'choose_slot';
                    $this->save_state($session_id, $state);
                    return 'Sin problema. Elige otra opción escribiendo el número que prefieras.';
                }

                if (! $this->is_affirmative_message($message, $parsed)) {
                    $this->save_state($session_id, $state);
                    return 'Necesito una confirmación clara. Responde sí para reservar o no para cambiar el hueco.';
                }

                if (empty($state['selected_slot'])) {
                    $state['step'] = 'choose_slot';
                    $this->save_state($session_id, $state);
                    return 'Se ha perdido la selección. Elige de nuevo una opción.';
                }

                $calendar = new MCC_Google_Calendar($settings);
                $booking  = $calendar->create_event([
                    'summary'     => $state['service'] . ' - ' . $state['name'],
                    'description' => $this->build_event_description($state),
                    'email'       => $state['email'],
                    'name'        => $state['name'],
                    'phone'       => $state['phone'],
                    'start'       => $state['selected_slot']['start'],
                    'end'         => $state['selected_slot']['end'],
                ]);

                if (is_wp_error($booking)) {
                    $this->save_state($session_id, $state);
                    return 'No he podido crear el evento en Google Calendar. Revisa credenciales, permisos y vuelve a intentarlo.';
                }

                $state['booked_event'] = $booking;
                $state['step']         = 'booked';
                $this->save_state($session_id, $state);

                return sprintf(
                    "Listo. Tu cita ha quedado agendada para %s.\n\nTe he tomado estos datos:\n- Servicio: %s\n- Nombre: %s\n- Email: %s\n\nSi quieres empezar una nueva reserva, escribe “reiniciar”.",
                    $this->format_datetime_for_human($state['selected_slot']['start'], $settings['timezone']),
                    $state['service'],
                    $state['name'],
                    $state['email']
                );

            case 'booked':
                $this->save_state($session_id, $state);
                return 'La cita ya está creada. Si quieres empezar otra reserva, escribe “reiniciar”.';
        }

        $this->save_state($session_id, $state);
        return 'No he podido seguir el flujo de reserva. Escribe “reiniciar” y empezamos de nuevo.';
    }

    private function build_event_description($state) {
        $lines = [
            'Reserva creada desde WordPress.',
            'Nombre: ' . $state['name'],
            'Email: ' . $state['email'],
            'Servicio: ' . $state['service'],
        ];

        if (! empty($state['phone'])) {
            $lines[] = 'Teléfono: ' . $state['phone'];
        }

        return implode("\n", $lines);
    }

    private function format_slots_reply($slots, $timezone) {
        $lines   = ['He encontrado estos huecos disponibles:'];
        $counter = 1;

        foreach ($slots as $slot) {
            $lines[] = $counter . '. ' . $this->format_datetime_for_human($slot['start'], $timezone);
            $counter++;
        }

        $lines[] = '';
        $lines[] = 'Responde con el número de la opción que prefieras.';

        return implode("\n", $lines);
    }

    private function format_datetime_for_human($date_time, $timezone) {
        try {
            $dt = new DateTime($date_time, new DateTimeZone($timezone));
            return wp_date('l d/m/Y \a \l\a\s H:i', $dt->getTimestamp(), new DateTimeZone($timezone));
        } catch (Exception $e) {
            return $date_time;
        }
    }

    private function is_reset_message($message) {
        $message = mb_strtolower(trim($message));
        return in_array($message, ['reiniciar', 'reset', 'empezar de nuevo', 'nuevo', 'borrar'], true);
    }

    private function is_affirmative_message($message, $parsed) {
        if (! empty($parsed['intent']) && $parsed['intent'] === 'confirm') {
            return true;
        }

        $message = mb_strtolower(trim($message));
        $yeses = ['si', 'sí', 'confirmo', 'ok', 'vale', 'adelante', 'correcto'];
        return in_array($message, $yeses, true);
    }

    private function is_negative_message($message, $parsed) {
        if (! empty($parsed['intent']) && $parsed['intent'] === 'reject') {
            return true;
        }

        $message = mb_strtolower(trim($message));
        $nos = ['no', 'cambiar', 'otra', 'otro hueco'];
        return in_array($message, $nos, true);
    }

    private function is_valid_future_date($date) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            $timezone = new DateTimeZone(MCC_Settings::get_settings()['timezone'] ?: 'Europe/Madrid');
            $selected = new DateTime($date . ' 00:00:00', $timezone);
            $today    = new DateTime('today', $timezone);
            return $selected >= $today;
        } catch (Exception $e) {
            return false;
        }
    }
}

