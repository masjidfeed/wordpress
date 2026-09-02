<?php
/**
 * Event source registry for the Masjid App mobile client.
 *
 * An event source is a supported third-party plugin that provides event data
 * for the /events endpoint and event fields on /posts/{id}. Each source is
 * implemented as a class implementing Masjid_Feed_Event_Source and registered
 * here with a stable settings key.
 */

if (!defined('ABSPATH')) { exit; }

interface Masjid_Feed_Event_Source {

    /**
     * Stable settings key for this source.
     */
    public function get_key();

    /**
     * Human-readable plugin name shown in settings and errors.
     */
    public function get_label();

    /**
     * Whether the plugin backing this source is installed and usable.
     */
    public function is_active();

    /**
     * Build the upcoming-event feed for the mobile client.
     *
     * @param array $opts MasjidFeed settings.
     * @return array List of Masjid App post objects, each with isEvent set.
     */
    public function get_upcoming_events($opts);

    /**
     * Build the Masjid App post object for one post, or null when this
     * source does not handle the post (caller falls back to default).
     *
     * @param int $post_id
     * @return array|null
     */
    public function build_post($post_id);
}

class Masjid_Feed_Event_Sources {

    /**
     * Map of settings keys to event source classes.
     */
    public static function get_source_classes() {
        return array(
            'awesome_calendar_events' => 'Masjid_Feed_Event_Source_Awesome_Calendar_Events',
            'the_events_calendar' => 'Masjid_Feed_Event_Source_The_Events_Calendar',
        );
    }

    /**
     * Resolve a settings key to an active source instance, or null when the
     * key is unknown or its plugin is not usable.
     */
    public static function get_source($key) {
        $classes = self::get_source_classes();
        if (!is_string($key) || !isset($classes[$key])) {
            return null;
        }
        $source = new $classes[$key]();
        return $source->is_active() ? $source : null;
    }

    /**
     * Choices for the settings dropdown, with plugin availability.
     */
    public static function get_choices() {
        $choices = array();
        foreach (self::get_source_classes() as $key => $class) {
            $source = new $class();
            $choices[$key] = array(
                'label' => $source->get_label(),
                'available' => (bool) $source->is_active(),
            );
        }
        return $choices;
    }

    /**
     * Slugs for configured term IDs; missing terms are skipped.
     */
    public static function get_term_slugs($term_ids, $taxonomy) {
        $slugs = array();
        foreach ((array) $term_ids as $term_id) {
            $term = get_term(absint($term_id), $taxonomy);
            if ($term instanceof WP_Term) {
                $slugs[] = $term->slug;
            }
        }
        return array_values(array_unique($slugs));
    }
}
