<?php
/**
 * Event source backed by The Events Calendar plugin.
 *
 * Events are fetched from that plugin's public REST API
 * (GET /wp-json/tribe/events/v1/events and /tribe/events/v1/events/{id}).
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Event_Source_The_Events_Calendar implements Masjid_Feed_Event_Source {

    const KEY = 'the_events_calendar';
    const POST_TYPE = 'tribe_events';
    const EVENTS_TAXONOMY = 'tribe_events_cat';
    const ARCHIVE_ROUTE = '/tribe/events/v1/events';
    const SINGLE_ROUTE = '/tribe/events/v1/events/%d';
    const MAX_EVENTS = 100;
    const DEFAULT_LIMIT = 20;
    const MAX_PAGES = 5;
    const PER_PAGE = 50;

    public function get_key() {
        return self::KEY;
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

    public function get_upcoming_events($opts, $limit = null) {
        $events = array();
        $limit = min(absint(null === $limit ? self::DEFAULT_LIMIT : $limit), self::MAX_EVENTS);

        for ($page = 1; $page <= self::MAX_PAGES && count($events) < $limit; $page++) {
            $per_page = min($limit - count($events), self::PER_PAGE);
            $request = new WP_REST_Request('GET', self::ARCHIVE_ROUTE);
            $request->set_param('per_page', $per_page);
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
            if (count($data['events']) < $per_page) {
                break;
            }
        }

        return array_slice($events, 0, $limit);
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
        $data['category'] = $this->get_event_category_slug($post_id, $event);
        return $data;
    }

    /**
     * Slug of the event's The Events Calendar category, preferring the first
     * non-"uncategorized" term.
     */
    private function get_event_category_slug($post_id, array $event) {
        $categories = isset($event['categories']) && is_array($event['categories']) ? $event['categories'] : array();
        $first = '';
        foreach ($categories as $category) {
            if (!is_array($category) || !isset($category['slug'])) {
                continue;
            }
            if ('uncategorized' !== $category['slug']) {
                return (string) $category['slug'];
            }
            if ('' === $first) {
                $first = (string) $category['slug'];
            }
        }
        if ('' !== $first) {
            return $first;
        }

        $terms = get_the_terms($post_id, self::EVENTS_TAXONOMY);
        if (!is_array($terms)) {
            return '';
        }
        foreach ($terms as $term) {
            if ($term instanceof WP_Term && 'uncategorized' !== $term->slug) {
                return $term->slug;
            }
        }
        return isset($terms[0]) && $terms[0] instanceof WP_Term ? $terms[0]->slug : '';
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
