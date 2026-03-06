<?php

if (! defined('ABSPATH')) {
    exit;
}

class MCC_Google_Calendar {
    private $settings;

    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function get_available_slots($args) {
        $date = sanitize_text_field($args['date'] ?? '');
        if (empty($date)) {
            return new WP_Error('mcc_missing_date', 'Falta la fecha.');
        }

        $timezone = $this->settings['timezone'] ?: 'Europe/Madrid';
        $busy = $this->query_busy_ranges($date);

        if (is_wp_error($busy)) {
            return $busy;
        }

        $candidate_slots = MCC_Slots::generate_candidate_slots(
            $date,
            $timezone,
            $this->settings['business_start'],
            $this->settings['business_end'],
            (int) ($args['duration'] ?? 30),
            (int) ($args['slot_interval'] ?? 30)
        );

        $available = MCC_Slots::filter_by_busy($candidate_slots, $busy, (int) ($args['buffer'] ?? 0));
        $available = MCC_Slots::filter_by_preference(
            $available,
            $args['time_preference'] ?? '',
            $args['specific_time'] ?? ''
        );

        return array_slice($available, 0, (int) ($args['max_slots'] ?? 3));
    }

    public function create_event($args) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $calendar_id = rawurlencode($this->settings['google_calendar_id'] ?: 'primary');
        $timezone    = $this->settings['timezone'] ?: 'Europe/Madrid';

        $body = [
            'summary'     => sanitize_text_field($args['summary'] ?? 'Cita'),
            'description' => sanitize_textarea_field($args['description'] ?? ''),
            'start'       => [
                'dateTime' => sanitize_text_field($args['start'] ?? ''),
                'timeZone' => $timezone,
            ],
            'end'         => [
                'dateTime' => sanitize_text_field($args['end'] ?? ''),
                'timeZone' => $timezone,
            ],
            'attendees'   => [],
        ];

        if (! empty($args['email']) && is_email($args['email'])) {
            $body['attendees'][] = [
                'email' => sanitize_email($args['email']),
                'displayName' => sanitize_text_field($args['name'] ?? ''),
            ];
        }

        $response = wp_remote_post('https://www.googleapis.com/calendar/v3/calendars/' . $calendar_id . '/events', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('mcc_google_create_event_failed', $this->extract_google_error_message($data, 'No se pudo crear el evento.'));
        }

        return [
            'id'        => $data['id'] ?? '',
            'htmlLink'  => $data['htmlLink'] ?? '',
            'status'    => $data['status'] ?? '',
            'start'     => $data['start']['dateTime'] ?? '',
            'end'       => $data['end']['dateTime'] ?? '',
        ];
    }

    private function query_busy_ranges($date) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $timezone = $this->settings['timezone'] ?: 'Europe/Madrid';
        $tz       = new DateTimeZone($timezone);
        $time_min = new DateTime($date . ' 00:00:00', $tz);
        $time_max = new DateTime($date . ' 23:59:59', $tz);

        $body = [
            'timeMin' => $time_min->format(DateTime::ATOM),
            'timeMax' => $time_max->format(DateTime::ATOM),
            'timeZone' => $timezone,
            'items'   => [
                [
                    'id' => $this->settings['google_calendar_id'] ?: 'primary',
                ],
            ],
        ];

        $response = wp_remote_post('https://www.googleapis.com/calendar/v3/freeBusy', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('mcc_google_freebusy_failed', $this->extract_google_error_message($data, 'No se pudo consultar la disponibilidad.'));
        }

        $calendar_id = $this->settings['google_calendar_id'] ?: 'primary';
        return $data['calendars'][$calendar_id]['busy'] ?? [];
    }

    private function get_access_token() {
        $client_id     = $this->settings['google_client_id'] ?? '';
        $client_secret = $this->settings['google_client_secret'] ?? '';
        $refresh_token = $this->settings['google_refresh_token'] ?? '';

        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return new WP_Error('mcc_google_missing_credentials', 'Faltan credenciales de Google en el plugin.');
        }

        $cached = get_transient('mcc_google_access_token');
        if (! empty($cached)) {
            return $cached;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300 || empty($data['access_token'])) {
            return new WP_Error('mcc_google_token_failed', $this->extract_google_error_message($data, 'No se pudo obtener el access token de Google.'));
        }

        $ttl = ! empty($data['expires_in']) ? max(60, ((int) $data['expires_in']) - 60) : HOUR_IN_SECONDS;
        set_transient('mcc_google_access_token', sanitize_text_field($data['access_token']), $ttl);

        return sanitize_text_field($data['access_token']);
    }

    private function extract_google_error_message($data, $fallback) {
        if (! empty($data['error_description'])) {
            return sanitize_text_field($data['error_description']);
        }
        if (! empty($data['error']['message'])) {
            return sanitize_text_field($data['error']['message']);
        }
        if (! empty($data['error'])) {
            return is_string($data['error']) ? sanitize_text_field($data['error']) : $fallback;
        }
        return $fallback;
    }
}
