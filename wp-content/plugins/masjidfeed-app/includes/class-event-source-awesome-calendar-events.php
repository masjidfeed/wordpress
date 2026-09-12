<?php
/**
 * Event source backed by the Awesome Calendar Events plugin.
 *
 * Upcoming events are fetched from that plugin's public query API
 * (GET /wp-json/awecal/v1/events, documented in its API.md) without
 * recurring-event expansion, so each feed item represents the event post
 * itself and clients expand occurrences from the recurrence rule. Single
 * post payloads read the `_awecal_` meta keys with a transparent fallback
 * to the legacy `_icob_` prefix.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Event_Source_Awesome_Calendar_Events implements Masjid_Feed_Event_Source {

    const REST_ROUTE = '/awecal/v1/events';
    const MAX_EVENTS = 100;
    const DEFAULT_LIMIT = 20;
    const MAX_PAGES = 10;

    public function get_key() {
        return 'awesome_calendar_events';
    }

    public function get_label() {
        return __('Awesome Calendar Events', 'masjidfeed-app');
    }

    public function is_active() {
        return class_exists('Awesome_Calendar_Events_Plugin');
    }

    public function get_upcoming_events($opts, $limit = null) {
        $events = array();
        $limit = min(absint(null === $limit ? self::DEFAULT_LIMIT : $limit), self::MAX_EVENTS);
        $page_token = '';

        for ($page = 0; $page < self::MAX_PAGES && count($events) < $limit; $page++) {
            $request = new WP_REST_Request('GET', self::REST_ROUTE);
            $request->set_param('per_page', 100);

            $categories = Masjid_Feed_Event_Sources::get_term_slugs($opts['events_categories'] ?? array(), 'category');
            if ($categories) {
                $request->set_param('categories', implode(',', $categories));
            }
            $tags = Masjid_Feed_Event_Sources::get_term_slugs($opts['events_tags'] ?? array(), 'post_tag');
            if ($tags) {
                $request->set_param('tags', implode(',', $tags));
            }
            if ('' !== $page_token) {
                $request->set_param('page_token', $page_token);
            }

            $response = rest_do_request($request);
            if (is_wp_error($response) || !$response instanceof WP_REST_Response || $response->get_status() >= 400) {
                break;
            }

            $items = $response->get_data();
            if (!is_array($items)) {
                break;
            }
            foreach ($items as $item) {
                if (is_array($item) && !empty($item['postId'])) {
                    $events[] = $this->build_event_post(absint($item['postId']), $item);
                }
            }

            $page_token = (string) ($this->get_response_header($response, 'X-WP-NextPageToken') ?? '');
            if ('' === $page_token) {
                break;
            }
        }

        return array_slice($events, 0, $limit);
    }

    private function get_response_header($response, $name) {
        foreach ((array) $response->get_headers() as $key => $value) {
            if (0 === strcasecmp((string) $key, $name)) {
                return $value;
            }
        }
        return null;
    }

    public function build_post($post_id) {
        $post_id = absint($post_id);
        $post = get_post($post_id);
        if (!$post || 'post' !== $post->post_type) {
            return null;
        }
        if ('1' !== (string) $this->get_meta($post_id, 'event_date_enabled')) {
            return null;
        }

        $data = Masjid_Feed_REST_API::build_base_post($post_id);
        $data['isEvent'] = true;
        $data['location'] = (string) $this->get_meta($post_id, 'event_location');
        $data['eventDateTime'] = $this->get_single_event_datetime_iso($post_id);
        $data['recurrenceRule'] = $this->get_recurrence_rule($post_id);

        $alert = $this->get_meta($post_id, 'announcement');
        if ('' !== (string) $alert) {
            $data['alert'] = (string) $alert;
        }

        $alert_end_datetime = $this->get_alert_end_datetime_iso($post_id);
        if ($alert_end_datetime) {
            $data['alertEndDateTime'] = $alert_end_datetime;
        }

        return $data;
    }

    private function build_event_post($post_id, array $item) {
        $event = isset($item['event']) && is_array($item['event']) ? $item['event'] : array();
        $occurrence = isset($item['occurrence']) && is_array($item['occurrence']) ? $item['occurrence'] : array();

        $data = Masjid_Feed_REST_API::build_base_post($post_id);
        $data['isEvent'] = true;
        $data['location'] = isset($event['location']) ? (string) $event['location'] : '';
        $data['eventDateTime'] = $this->get_occurrence_datetime_iso($occurrence, $event);
        $data['recurrenceRule'] = !empty($event['recurrenceRule']) ? (string) $event['recurrenceRule'] : null;

        return $data;
    }

    private function get_occurrence_datetime_iso($occurrence, $event) {
        $date = isset($occurrence['date']) ? trim((string) $occurrence['date']) : '';
        if ('' === $date) {
            $date = isset($event['date']) ? trim((string) $event['date']) : '';
        }
        $start_time = isset($event['startTime']) ? (string) $event['startTime'] : '';
        return $this->format_datetime_iso($date, $start_time);
    }

    private function get_single_event_datetime_iso($post_id) {
        $date = null;
        if (class_exists('Awesome_Calendar_Events_Event_Meta')) {
            $date = Awesome_Calendar_Events_Event_Meta::get_next_occurrence($post_id);
        }
        $start_time = (string) $this->get_meta($post_id, 'event_start_time');
        return $this->format_datetime_iso((string) $date, $start_time);
    }

    private function format_datetime_iso($date, $start_time) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return null;
        }
        if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) $start_time)) {
            $start_time = '00:00';
        }
        try {
            $dt = new DateTime($date . ' ' . $start_time, wp_timezone());
            return $dt->format('c');
        } catch (Exception $e) {
            return null;
        }
    }

    private function get_recurrence_rule($post_id) {
        if (!class_exists('Awesome_Calendar_Events_ICS_Generator')) {
            return null;
        }
        $rule = (new Awesome_Calendar_Events_ICS_Generator())->get_recurrence_rule($post_id);
        return $rule ? (string) $rule : null;
    }

    private function get_alert_end_datetime_iso($post_id) {
        $expiration = (string) $this->get_meta($post_id, 'announcement_expiration');
        if ('' === $expiration) {
            return null;
        }
        try {
            $dt = new DateTime($expiration, wp_timezone());
            return $dt->format('c');
        } catch (Exception $e) {
            return null;
        }
    }

    private function get_meta($post_id, $suffix) {
        $value = get_post_meta($post_id, '_awecal_' . $suffix, true);
        if ('' === $value || null === $value) {
            $value = get_post_meta($post_id, '_icob_' . $suffix, true);
        }
        return $value;
    }
}
