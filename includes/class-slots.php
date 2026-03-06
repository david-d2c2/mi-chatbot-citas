<?php

if (! defined('ABSPATH')) {
    exit;
}

class MCC_Slots {
    public static function generate_candidate_slots($date, $timezone, $start_time, $end_time, $duration, $interval) {
        $slots = [];

        try {
            $tz = new DateTimeZone($timezone);
            $start = new DateTime($date . ' ' . $start_time, $tz);
            $end = new DateTime($date . ' ' . $end_time, $tz);

            while ($start < $end) {
                $slot_start = clone $start;
                $slot_end   = clone $start;
                $slot_end->modify('+' . (int) $duration . ' minutes');

                if ($slot_end <= $end) {
                    $slots[] = [
                        'start' => $slot_start->format(DateTime::ATOM),
                        'end'   => $slot_end->format(DateTime::ATOM),
                    ];
                }

                $start->modify('+' . (int) $interval . ' minutes');
            }
        } catch (Exception $e) {
            return [];
        }

        return $slots;
    }

    public static function filter_by_busy($candidate_slots, $busy_ranges, $buffer = 0) {
        if (empty($busy_ranges)) {
            return $candidate_slots;
        }

        $available = [];

        foreach ($candidate_slots as $slot) {
            $slot_start = strtotime($slot['start']);
            $slot_end   = strtotime($slot['end']);

            $is_free = true;

            foreach ($busy_ranges as $busy) {
                $busy_start = strtotime($busy['start']) - ((int) $buffer * 60);
                $busy_end   = strtotime($busy['end']) + ((int) $buffer * 60);

                if ($slot_start < $busy_end && $slot_end > $busy_start) {
                    $is_free = false;
                    break;
                }
            }

            if ($is_free) {
                $available[] = $slot;
            }
        }

        return $available;
    }

    public static function filter_by_preference($slots, $time_preference = '', $specific_time = '') {
        if (empty($slots)) {
            return [];
        }

        $filtered = [];

        foreach ($slots as $slot) {
            try {
                $dt = new DateTime($slot['start']);
                $hour = (int) $dt->format('G');
                $minute = $dt->format('H:i');
            } catch (Exception $e) {
                continue;
            }

            if ($time_preference === 'morning' && $hour >= 14) {
                continue;
            }

            if ($time_preference === 'afternoon' && $hour < 14) {
                continue;
            }

            if ($time_preference === 'exact_time' && ! empty($specific_time) && $minute !== $specific_time) {
                continue;
            }

            $filtered[] = $slot;
        }

        return $filtered;
    }
}
