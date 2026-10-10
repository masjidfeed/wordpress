<?php
/**
 * Parking map configuration for the Masjid App mobile client.
 *
 * Stores the JSON document served by GET /masjid/v1/parking-config and
 * validates it before it can be saved. The document is normally produced by
 * the parking-geojson-to-config.py script (see parking-api/README.md), which
 * performs the same checks; this validator is the last line of defence
 * because the mobile app trusts any 200 response and a badly formed document
 * can crash the app at launch.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Parking_Config {

    const OPTION_KEY = 'masjidfeed_parking_config';
    const DEFAULT_NEAR_RADIUS_METERS = 800;
    const ZONE_TYPES = array('no-parking', 'event-restricted', 'allowed');
    /**
     * No point may be more than this far from "center"; catches swapped
     * latitude/longitude pairs, which move every zone far away.
     */
    const MAX_POINT_DISTANCE_METERS = 3000;

    /**
     * Parse and validate a raw JSON document.
     *
     * Returns array('document' => decoded array or null, 'errors' => list of
     * messages). The document is null when the JSON is unusable or any
     * validation error is found, so it can never be served; a non-empty error
     * list means the previous document must stay in place.
     */
    public static function parse($raw) {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return array('document' => null, 'errors' => array(__('The parking configuration is empty.', 'masjidfeed-app')));
        }

        $document = json_decode($raw, true);
        if (!is_array($document)) {
            return array('document' => null, 'errors' => array(__('The parking configuration is not valid JSON.', 'masjidfeed-app')));
        }

        $errors = self::validate_document($document);
        return array('document' => $errors ? null : $document, 'errors' => $errors);
    }

    /**
     * Decode the saved option. Returns null when nothing has been saved or
     * the stored value cannot be decoded, which makes the endpoint answer 404
     * instead of serving an empty document.
     */
    public static function get_saved_document() {
        $raw = get_option(self::OPTION_KEY, '');
        if (!is_string($raw) || '' === trim($raw)) {
            return null;
        }
        $document = json_decode($raw, true);
        return is_array($document) ? $document : null;
    }

    /**
     * Summarize the saved option for the settings screen.
     */
    public static function summarize($document) {
        if (!is_array($document)) {
            return '';
        }
        return sprintf(
            __('%1$d zone(s), %2$d event(s).', 'masjidfeed-app'),
            isset($document['zones']) && is_array($document['zones']) ? count($document['zones']) : 0,
            isset($document['events']) && is_array($document['events']) ? count($document['events']) : 0
        );
    }

    private static function validate_document($document) {
        $errors = array();

        $center = self::validate_center($document, $errors);
        self::validate_near_radius($document, $errors);
        $zone_ids = self::validate_zones($document, $center, $errors);
        self::validate_events($document, $zone_ids, $errors);

        return $errors;
    }

    private static function validate_center($document, &$errors) {
        if (!isset($document['center']) || !is_array($document['center'])) {
            $errors[] = __('Top-level "center" is required and must be an object.', 'masjidfeed-app');
            return null;
        }

        $center = array();
        foreach (array('latitude', 'longitude') as $key) {
            if (!self::is_number($document['center'][$key] ?? null)) {
                $errors[] = sprintf(__('Center "%1$s" must be a number.', 'masjidfeed-app'), $key);
                continue;
            }
            $center[$key] = (float) $document['center'][$key];
        }

        return count($center) === 2 ? $center : null;
    }

    private static function validate_near_radius($document, &$errors) {
        if (!array_key_exists('nearRadiusMeters', $document)) {
            return;
        }
        $value = $document['nearRadiusMeters'];
        if (!self::is_number($value)) {
            $errors[] = __('"nearRadiusMeters" must be a number.', 'masjidfeed-app');
            return;
        }
        if ((float) $value < 0) {
            $errors[] = __('"nearRadiusMeters" must not be negative.', 'masjidfeed-app');
        }
    }

    private static function validate_zones($document, $center, &$errors) {
        if (!isset($document['zones']) || !is_array($document['zones'])) {
            $errors[] = __('Top-level "zones" is required and must be a list.', 'masjidfeed-app');
            return array();
        }

        $ids = array();
        foreach ($document['zones'] as $index => $zone) {
            $position = $index + 1;
            if (!is_array($zone)) {
                $errors[] = sprintf(__('Zone at position %1$d must be an object.', 'masjidfeed-app'), $position);
                continue;
            }

            $id = $zone['id'] ?? null;
            if (!is_string($id) || '' === $id) {
                $errors[] = sprintf(__('Zone at position %1$d is missing "id".', 'masjidfeed-app'), $position);
                continue;
            }
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $id)) {
                $errors[] = sprintf(__('Zone "%1$s": "id" must use lowercase letters, digits, and hyphens.', 'masjidfeed-app'), $id);
            }
            if (isset($ids[$id])) {
                $errors[] = sprintf(__('Zone "%1$s": "id" is used more than once.', 'masjidfeed-app'), $id);
            }
            $ids[$id] = true;
            $label = sprintf('zone "%s"', $id);

            $name = $zone['name'] ?? null;
            if (!is_string($name) || '' === $name) {
                $errors[] = sprintf(__('%1$s: "name" is required.', 'masjidfeed-app'), $label);
            }

            $type = $zone['type'] ?? null;
            if (!is_string($type) || !in_array($type, self::ZONE_TYPES, true)) {
                $errors[] = sprintf(__('%1$s: "type" must be one of no-parking, event-restricted, allowed.', 'masjidfeed-app'), $label);
            }

            if (array_key_exists('message', $zone) && null !== $zone['message'] && !is_string($zone['message'])) {
                $errors[] = sprintf(__('%1$s: "message" must be text.', 'masjidfeed-app'), $label);
            }

            self::validate_zone_coordinates($zone, $id, $center, $errors);
        }

        return $ids;
    }

    private static function validate_zone_coordinates($zone, $id, $center, &$errors) {
        $coordinates = $zone['coordinates'] ?? null;
        if (!is_array($coordinates) || count($coordinates) < 3) {
            $errors[] = sprintf(
                __('Zone "%1$s": "coordinates" must be a list of at least 3 points.', 'masjidfeed-app'),
                $id
            );
            return;
        }

        foreach ($coordinates as $point_index => $point) {
            if (!is_array($point) || !self::is_number($point['latitude'] ?? null) || !self::is_number($point['longitude'] ?? null)) {
                $errors[] = sprintf(
                    __('Zone "%1$s": coordinate %2$d must be an object with numeric "latitude" and "longitude".', 'masjidfeed-app'),
                    $id,
                    $point_index + 1
                );
                continue;
            }

            if ($center && self::distance_meters(
                $center['latitude'],
                $center['longitude'],
                (float) $point['latitude'],
                (float) $point['longitude']
            ) > self::MAX_POINT_DISTANCE_METERS) {
                $errors[] = sprintf(
                    __('Zone "%1$s": point %2$d is more than %3$d km from the mosque; coordinates may be swapped.', 'masjidfeed-app'),
                    $id,
                    $point_index + 1,
                    (int) ceil(self::MAX_POINT_DISTANCE_METERS / 1000)
                );
            }
        }
    }

    private static function validate_events($document, $zone_ids, &$errors) {
        if (!isset($document['events']) || !is_array($document['events'])) {
            $errors[] = __('Top-level "events" is required and must be a list (it may be empty).', 'masjidfeed-app');
            return;
        }

        foreach ($document['events'] as $index => $event) {
            if (!is_array($event)) {
                $errors[] = sprintf(__('Event at position %1$d must be an object.', 'masjidfeed-app'), $index + 1);
                continue;
            }

            $id = $event['id'] ?? null;
            $label = is_string($id) && '' !== $id
                ? sprintf('event "%s"', $id)
                : sprintf(__('event at position %1$d', 'masjidfeed-app'), $index + 1);
            if (!is_string($id) || '' === $id) {
                $errors[] = sprintf(__('%1$s: "id" is required.', 'masjidfeed-app'), $label);
            }

            foreach (array('name', 'message') as $key) {
                $value = $event[$key] ?? null;
                if (!is_string($value) || '' === $value) {
                    $errors[] = sprintf(__('%1$s: "%2$s" is required.', 'masjidfeed-app'), $label, $key);
                }
            }

            self::validate_schedule($event['schedule'] ?? null, $label, $errors);
            self::validate_zone_ids($event['zoneIds'] ?? null, $zone_ids, $label, $errors);
        }
    }

    private static function validate_schedule($schedule, $label, &$errors) {
        if (!is_array($schedule)) {
            $errors[] = sprintf(__('%1$s: "schedule" is required.', 'masjidfeed-app'), $label);
            return;
        }

        $start = $schedule['start'] ?? null;
        $end = $schedule['end'] ?? null;
        self::validate_time($start, $label, '"start"', $errors);
        self::validate_time($end, $label, '"end"', $errors);
        if (is_string($start) && is_string($end) && $start === $end) {
            $errors[] = sprintf(__('%1$s: schedule "start" and "end" must differ.', 'masjidfeed-app'), $label);
        }

        $type = $schedule['type'] ?? null;
        if ('weekly' === $type) {
            $days = $schedule['days'] ?? null;
            if (!is_array($days) || 0 === count($days)) {
                $errors[] = sprintf(__('%1$s: weekly schedule "days" must list at least one weekday.', 'masjidfeed-app'), $label);
                return;
            }
            foreach ($days as $day) {
                if (!is_int($day) || $day < 0 || $day > 6) {
                    $errors[] = sprintf(__('%1$s: schedule "days" entries must be numbers from 0 (Sunday) to 6 (Saturday).', 'masjidfeed-app'), $label);
                    return;
                }
            }
            return;
        }

        if ('dates' === $type) {
            $dates = $schedule['dates'] ?? null;
            if (!is_array($dates) || 0 === count($dates)) {
                $errors[] = sprintf(__('%1$s: "dates" schedule must list at least one date.', 'masjidfeed-app'), $label);
                return;
            }
            foreach ($dates as $date) {
                if (!self::is_valid_date($date)) {
                    $errors[] = sprintf(__('%1$s: schedule "dates" entries must be real calendar dates in YYYY-MM-DD format.', 'masjidfeed-app'), $label);
                    return;
                }
            }
            return;
        }

        $errors[] = sprintf(__('%1$s: schedule "type" must be one of weekly, dates.', 'masjidfeed-app'), $label);
    }

    private static function validate_zone_ids($zone_ids, $known_ids, $label, &$errors) {
        if ('all' === $zone_ids) {
            return;
        }
        if (!is_array($zone_ids) || 0 === count($zone_ids)) {
            $errors[] = sprintf(__('%1$s: "zoneIds" must be "all" or a list of zone ids.', 'masjidfeed-app'), $label);
            return;
        }
        foreach ($zone_ids as $zone_id) {
            if (!is_string($zone_id) || !isset($known_ids[$zone_id])) {
                $errors[] = sprintf(
                    __('%1$s: zoneIds names "%2$s", which is not a zone.', 'masjidfeed-app'),
                    $label,
                    is_string($zone_id) ? $zone_id : ''
                );
            }
        }
    }

    private static function validate_time($value, $label, $field, &$errors) {
        if (!is_string($value) || !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/D', $value)) {
            $errors[] = sprintf(__('%1$s: schedule %2$s must be a 24-hour HH:mm time.', 'masjidfeed-app'), $label, $field);
        }
    }

    private static function is_valid_date($value) {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches)) {
            return false;
        }
        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private static function is_number($value) {
        return is_int($value) || is_float($value);
    }

    private static function distance_meters($latitude_a, $longitude_a, $latitude_b, $longitude_b) {
        $earth_radius = 6371000.0;
        $phi_a = deg2rad($latitude_a);
        $phi_b = deg2rad($latitude_b);
        $delta_phi = deg2rad($latitude_b - $latitude_a);
        $delta_lambda = deg2rad($longitude_b - $longitude_a);

        $a = sin($delta_phi / 2) * sin($delta_phi / 2)
            + cos($phi_a) * cos($phi_b) * sin($delta_lambda / 2) * sin($delta_lambda / 2);
        return 2 * $earth_radius * asin(min(1.0, sqrt($a)));
    }
}