<?php
/**
 * Seed 100 test events for The Events Calendar (tribe_events) into the local WP install.
 * Run inside the wordpress container: php /scripts/seed-tce.php
 */

require '/var/www/html/wp-load.php';

if (!class_exists('Tribe__Events__Main')) {
    echo "The Events Calendar is not active.\n";
    exit(1);
}

mt_srand(42);

$venue_names = ['Main Hall', 'Community Room', 'ICOB Center', 'Rooftop Terrace', 'Library Annex', 'Gymnasium', 'Overflow Parking Lot', 'Zoom (Online)'];
$categories = ['events', 'community', 'youth', 'fundraiser', 'workshop'];
$tag_pool = ['family', 'free', 'outdoor', 'annual', 'charity', 'lecture'];

// Query IDs directly so The Events Calendar's upcoming-event filters do not hide past seed data.
global $wpdb;
$existing = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s",
        'tribe_events',
        $wpdb->esc_like('Seed Event ') . '%'
    )
);
foreach ($existing as $old_id) {
    wp_delete_post($old_id, true);
}

// Also remove venues created by previous runs
$existing_venues = get_posts([
    'post_type' => 'tribe_venue',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
    's' => 'Seed Venue',
]);
foreach ($existing_venues as $old_venue_id) {
    wp_delete_post($old_venue_id, true);
}

// Ensure event categories exist in the TEC taxonomy (tribe_events_cat)
$cat_ids = [];
foreach ($categories as $slug) {
    $term = get_term_by('slug', $slug, Tribe__Events__Main::TAXONOMY);
    if (!$term) {
        $res = wp_insert_term(ucfirst($slug), Tribe__Events__Main::TAXONOMY, ['slug' => $slug]);
        $cat_ids[$slug] = is_wp_error($res) ? null : (int) $res['term_id'];
    } else {
        $cat_ids[$slug] = (int) $term->term_id;
    }
}

// Ensure tags exist
$tag_ids = [];
foreach ($tag_pool as $slug) {
    $term = get_term_by('slug', $slug, 'post_tag');
    if (!$term) {
        $res = wp_insert_term(ucfirst($slug), 'post_tag', ['slug' => $slug]);
        $tag_ids[$slug] = is_wp_error($res) ? null : (int) $res['term_id'];
    } else {
        $tag_ids[$slug] = (int) $term->term_id;
    }
}

// Create one venue per location, reuse for all events
$venue_ids = [];
foreach ($venue_names as $name) {
    $venue_id = tribe_create_venue([
        'post_title' => 'Seed Venue - ' . $name,
        'post_content' => 'Seed venue for ' . $name . '.',
        'post_status' => 'publish',
        'Venue' => $name,
    ]);
    if ($venue_id) {
        $venue_ids[] = (int) $venue_id;
    }
}

$today = strtotime(current_time('Y-m-d') . ' 00:00:00');
$created = [];
$all_day_count = 0;

for ($i = 1; $i <= 100; $i++) {
    $offset_days = mt_rand(-90, 180);
    $date = $today + $offset_days * DAY_IN_SECONDS;
    $start_hour = mt_rand(8, 20);
    $duration_hours = mt_rand(1, 6);
    $venue_id = $venue_ids[$i % count($venue_ids)] ?? 0;
    $is_all_day = ($i % 10 === 0); // every 10th event is an all-day event

    $start_date = date('Y-m-d H:i:s', $date + $start_hour * HOUR_IN_SECONDS + ($i % 4 === 0 ? 0 : 30 * MINUTE_IN_SECONDS));
    $end_date = date('Y-m-d H:i:s', strtotime($start_date) + $duration_hours * HOUR_IN_SECONDS);

    $post_id = tribe_create_event([
        'post_title' => 'Seed Event ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'post_content' => 'Test content for seed event ' . $i . '. Venue: ' . $venue_names[$i % count($venue_names)] . '. Date: ' . date('Y-m-d', $date) . '.',
        'post_status' => 'publish',
        'post_date_gmt' => gmdate('Y-m-d H:i:s', time() - mt_rand(0, 86400 * 30)),
        'EventStartDate' => $start_date,
        'EventEndDate' => $end_date,
        'EventAllDay' => $is_all_day,
        'EventShowMapLink' => true,
        'EventShowMap' => true,
        'EventCost' => ($i % 3 === 0) ? '10.00' : '',
        'VenueID' => $venue_id,
        'tax_input' => [
            Tribe__Events__Main::TAXONOMY => array_values(array_filter([
                $cat_ids['events'] ?? 0,
                $cat_ids[$categories[$i % count($categories)]] ?? 0,
            ])),
            'post_tag' => array_values(array_unique(array_filter([
                $tag_ids[$tag_pool[$i % count($tag_pool)]] ?? 0,
                $tag_ids[$tag_pool[($i + 2) % count($tag_pool)]] ?? 0,
            ]))),
        ],
    ]);

    if (!$post_id) {
        echo "ERROR creating event $i\n";
        continue;
    }

    // Some TEC versions drop EventStartDate when created via tribe_create_event
    // in CLI context; write the date meta directly and rebuild TEC's custom
    // tables (wp_tec_events / wp_tec_occurrences) via its repository so the
    // event is queryable and listed.
    update_post_meta($post_id, '_EventStartDate', $start_date);
    update_post_meta($post_id, '_EventEndDate', $end_date);
    update_post_meta($post_id, '_EventAllDay', $is_all_day ? 'yes' : 'no');
    update_post_meta($post_id, '_EventTimezone', wp_timezone_string());
    $start_utc = new DateTime($start_date, wp_timezone());
    $start_utc->setTimezone(new DateTimeZone('UTC'));
    update_post_meta($post_id, '_EventStartDateUTC', $start_utc->format('Y-m-d H:i:s'));
    $end_utc = new DateTime($end_date, wp_timezone());
    $end_utc->setTimezone(new DateTimeZone('UTC'));
    update_post_meta($post_id, '_EventEndDateUTC', $end_utc->format('Y-m-d H:i:s'));

    // An empty _EventHideFromUpcoming meta row hides the event from upcoming
    // queries; make sure it is not present.
    delete_post_meta($post_id, '_EventHideFromUpcoming');

    // tribe_create_event drops tax_input when no user is logged in; assign
    // the event category and tags explicitly.
    wp_set_object_terms($post_id, array_values(array_filter([
        $cat_ids['events'] ?? 0,
        $cat_ids[$categories[$i % count($categories)]] ?? 0,
    ])), Tribe__Events__Main::TAXONOMY, false);
    wp_set_object_terms($post_id, array_values(array_unique(array_filter([
        $tag_ids[$tag_pool[$i % count($tag_pool)]] ?? 0,
        $tag_ids[$tag_pool[($i + 2) % count($tag_pool)]] ?? 0,
    ]))), 'post_tag', false);

    if (class_exists('TEC\\Events\\Custom_Tables\\V1\\Repository\\Events')) {
        (new TEC\Events\Custom_Tables\V1\Repository\Events())->update($post_id, []);
    }

    if ($is_all_day) {
        $all_day_count++;
    }

    $created[] = (int) $post_id;
}

// Flush API response cache + rewrite rules
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_\\_transient\\_tribe\\_%' OR option_name LIKE '\\_\\_transient\\_timeout\\_tribe\\_%'");
global $wp_rewrite;
$wp_rewrite->flush_rules();

echo "Created " . count($created) . " tribe_events (all-day: $all_day_count, venues: " . count($venue_ids) . ")\n";

// Summary
$future = 0;
$past = 0;
foreach ($created as $pid) {
    $start = get_post_meta($pid, '_EventStartDate', true);
    if ($start && strtotime($start) >= $today) {
        $future++;
    } else {
        $past++;
    }
}
echo "Future events: $future, past: $past\n";
