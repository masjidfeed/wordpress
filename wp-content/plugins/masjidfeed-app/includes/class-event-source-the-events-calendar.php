<?php
/**
 * Event source backed by The Events Calendar plugin.
 *
 * Events are fetched from that plugin's public REST API
 * (GET /wp-json/tribe/events/v1/events and /tribe/events/v1/events/{id}).
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Event_Source_The_Events_Calendar implements Masjid_Feed_Event_Source {

    const POST_TYPE = 'tribe_events';
    const EVENTS_TAXONOMY = 'tribe_events_cat';
    const ARCHIVE_ROUTE = '/tribe/events/v1/events';
    const SINGLE_ROUTE = '/tribe/events/v1/events/%d';
    const MAX_EVENTS = 20;
    const MAX_PAGES = 5;

    public function get_key() {
        return 'the_events_calendar';
    }

    public function get_label() {
        return __('The Events Calendar', 'masjidfeed-app');
    }

    public function is_active() {
        if (!class_exists('Tribe__Events__Main') || !class_exists('Tribe__Events__REST__V1__System')) {
            return false;
        }
        return (bool) (new Tribe__Events__REST__V1__System())->tec_rest_api_is_enabled();
    }

    public function get_upcoming_events($opts) {
        $events = array();

        for ($page = 1; $page <= self::MAX_PAGES && count($events) < self::MAX_EVENTS; $page++) {
            $request = new WP_REST_Request('GET', self::ARCHIVE_ROUTE);
            $request->set_param('per_page', self::MAX_EVENTS);
            $request->set_param('page', $page);
            $request->set_param('start_date', current_time('Y-m-d H:i:s'));
            $request->set_param('status', 'publish');

            $categories = Masjid_Feed_Event_Sources::get_term_slugs($opts['events_categories'] ?? array(), self::EVENTS_TAXONOMY);
            if ($categories) {
                $request->set_param('categories', implode(',', $categories));
            }
            $tags = Masjid_Feed_Event_Sources::get_term_slugs($opts['events_tags'] ?? array(), 'post_tag');
            if ($tags) {
                $request->set_param('tags', implode(',', $tags));
            }

            $response = rest_do_request($request);
            if (is_wp_error($response) || !$response instanceof WP_REST_Response || $response->get_status() >= 400) {
                break;
            }

            $data = $response->get_data();
            if (!is_array($data) || empty($data['events']) || !is_array($data['events'])) {
                break;
            }
            foreach ($data['events'] as $event) {
                if (is_array($event) && !empty($event['id'])) {
                    $events[] = $this->build_event_post(absint($event['id']), $event);
                }
            }
            if (count($data['events']) < self::MAX_EVENTS) {
                break;
            }
        }

        return array_slice($events, 0, self::MAX_EVENTS);
    }

    public function build_post($post_id) {
        $post_id = absint($post_id);
        $post = get_post($post_id);
        if (!$post || self::POST_TYPE !== $post->post_type) {
            return null;
        }

        $response = rest_do_request(new WP_REST_Request('GET', sprintf(self::SINGLE_ROUTE, $post_id)));
        if (is_wp_error($response) || !$response instanceof WP_REST_Response || $response->get_status() >= 400) {
            return null;
        }
        $event = $response->get_data();
        if (!is_array($event) || empty($event['id'])) {
            return null;
        }

        return $this->build_event_post($post_id, $event);
    }

    private function build_event_post($post_id, array $event) {
        $data = Masjid_Feed_REST_API::build_base_post($post_id);
        $data['isEvent'] = true;
        $data['location'] = $this->get_venue_name($event);
        $data['eventDateTime'] = $this->get_start_datetime_iso($event);
        $data['recurrenceRule'] = null;
        return $data;
    }

    private function get_venue_name(array $event) {
        if (empty($event['venue']) || !is_array($event['venue'])) {
            return '';
        }
        $first = reset($event['venue']);
        if (is_array($first) && isset($first['venue'])) {
            return (string) $first['venue'];
        }
        return '';
    }

    private function get_start_datetime_iso(array $event) {
        $start_date = isset($event['start_date']) ? trim((string) $event['start_date']) : '';
        if ('' === $start_date) {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $start_date, wp_timezone());
        return $dt ? $dt->format('c') : null;
    }
}
