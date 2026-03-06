<?php

if (! defined('ABSPATH')) {
    exit;
}

class MCC_OpenAI {
    public static function parse_user_message($message, $state, $settings) {
        $fallback = self::fallback_parse($message, $state);

        if (empty($settings['openai_api_key'])) {
            return $fallback;
        }

        $system_prompt = "Eres un analizador de mensajes para un chatbot que agenda citas. Responde solo JSON válido.\n" .
            "Debes detectar la intención y extraer campos si aparecen.\n" .
            "Campos posibles: name, email, phone, service, preferred_date, time_preference, specific_time, slot_index.\n" .
            "time_preference debe ser uno de: morning, afternoon, exact_time, anytime, unknown.\n" .
            "preferred_date debe ir en formato YYYY-MM-DD si se puede inferir con seguridad. Si no, cadena vacía.\n" .
            "specific_time debe ir en formato HH:MM si existe. Si no, cadena vacía.\n" .
            "slot_index debe ser un número si el usuario elige una opción tipo 1, 2 o 3.\n" .
            "intent debe ser uno de: provide_info, choose_slot, confirm, reject, reset, other.\n" .
            "No inventes datos.";

        $state_summary = [
            'step'            => $state['step'] ?? '',
            'name'            => $state['name'] ?? '',
            'email'           => $state['email'] ?? '',
            'phone'           => $state['phone'] ?? '',
            'service'         => $state['service'] ?? '',
            'preferred_date'  => $state['preferred_date'] ?? '',
            'time_preference' => $state['time_preference'] ?? '',
            'specific_time'   => $state['specific_time'] ?? '',
        ];

        $body = [
            'model' => $settings['openai_model'] ?: 'gpt-5-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt,
                ],
                [
                    'role' => 'user',
                    'content' => wp_json_encode([
                        'state'   => $state_summary,
                        'message' => $message,
                        'today'   => wp_date('Y-m-d'),
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'temperature' => 0.1,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $settings['openai_api_key'],
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return $fallback;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300 || empty($data['choices'][0]['message']['content'])) {
            return $fallback;
        }

        $parsed = json_decode($data['choices'][0]['message']['content'], true);

        if (! is_array($parsed)) {
            return $fallback;
        }

        return wp_parse_args($parsed, $fallback);
    }

    public static function fallback_parse($message, $state = []) {
        $text = trim((string) $message);
        $lower = mb_strtolower($text);

        $intent = 'provide_info';
        if (preg_match('/^(1|2|3|4|5)$/', $lower)) {
            $intent = 'choose_slot';
        } elseif (in_array($lower, ['si', 'sí', 'confirmo', 'vale', 'ok', 'adelante'], true)) {
            $intent = 'confirm';
        } elseif (in_array($lower, ['no', 'cambiar', 'otra', 'otro'], true)) {
            $intent = 'reject';
        } elseif (in_array($lower, ['reiniciar', 'reset', 'empezar de nuevo'], true)) {
            $intent = 'reset';
        }

        preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $email_match);
        preg_match('/(?:\+?\d[\d\s\-]{7,}\d)/', $text, $phone_match);
        preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $date_match);
        preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $text, $time_match);
        preg_match('/\b([1-9])\b/', $text, $slot_match);

        $time_preference = 'unknown';
        if (str_contains($lower, 'mañana')) {
            $time_preference = 'morning';
        } elseif (str_contains($lower, 'tarde')) {
            $time_preference = 'afternoon';
        } elseif (! empty($time_match[0])) {
            $time_preference = 'exact_time';
        } elseif (str_contains($lower, 'cuando sea') || str_contains($lower, 'me da igual')) {
            $time_preference = 'anytime';
        }

        return [
            'intent' => $intent,
            'fields' => [
                'name'            => (! empty($state['step']) && $state['step'] === 'ask_name') ? sanitize_text_field($text) : '',
                'email'           => $email_match[0] ?? '',
                'phone'           => $phone_match[0] ?? '',
                'service'         => (! empty($state['step']) && $state['step'] === 'ask_service') ? sanitize_text_field($text) : '',
                'preferred_date'  => $date_match[1] ?? '',
                'time_preference' => $time_preference,
                'specific_time'   => $time_match[0] ?? '',
                'slot_index'      => $slot_match[1] ?? '',
            ],
        ];
    }

    public static function extract_date_fallback($message) {
        $parsed = self::fallback_parse($message);
        return $parsed['fields']['preferred_date'] ?? '';
    }

    public static function extract_time_preference_fallback($message) {
        $parsed = self::fallback_parse($message);
        return [
            'time_preference' => $parsed['fields']['time_preference'] ?? '',
            'specific_time'   => $parsed['fields']['specific_time'] ?? '',
        ];
    }

    public static function extract_slot_index_fallback($message) {
        $parsed = self::fallback_parse($message);
        return isset($parsed['fields']['slot_index']) ? (int) $parsed['fields']['slot_index'] : 0;
    }
}
