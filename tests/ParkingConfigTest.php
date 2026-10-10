<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ParkingConfigTest extends TestCase {

    private static function fixture_raw(): string {
        $raw = file_get_contents(__DIR__ . '/parking-config.json');
        if (false === $raw) {
            throw new RuntimeException('tests/parking-config.json could not be read.');
        }
        return $raw;
    }

    private static function fixture_document(): array {
        return json_decode(self::fixture_raw(), true);
    }

    private static function mutated(callable $change): string {
        $document = self::fixture_document();
        $change($document);
        return json_encode($document, JSON_UNESCAPED_SLASHES);
    }

    private function assert_single_error(string $raw, string $expected_substring): void {
        $result = Masjid_Feed_Parking_Config::parse($raw);
        $this->assertNull($result['document']);
        $this->assertNotEmpty($result['errors']);
        $joined = implode(' | ', $result['errors']);
        $this->assertStringContainsString($expected_substring, $joined);
    }

    public function test_fixture_document_is_valid(): void {
        $result = Masjid_Feed_Parking_Config::parse(self::fixture_raw());
        $this->assertSame([], $result['errors']);
        $this->assertIsArray($result['document']);
        $this->assertCount(14, $result['document']['zones']);
        $this->assertCount(1, $result['document']['events']);
    }

    public function test_numbers_stay_numbers(): void {
        $result = Masjid_Feed_Parking_Config::parse(self::fixture_raw());
        $document = $result['document'];

        $this->assertSame(47.7779, $document['center']['latitude']);
        $this->assertSame(-122.1906, $document['center']['longitude']);
        $this->assertSame(800, $document['nearRadiusMeters']);
        $this->assertSame([5], $document['events'][0]['schedule']['days']);
        $this->assertSame(47.77882298016584, $document['zones'][0]['coordinates'][0]['latitude']);
    }

    public function test_rejects_invalid_json(): void {
        $result = Masjid_Feed_Parking_Config::parse('{ not json');
        $this->assertNull($result['document']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_empty_input(): void {
        $result = Masjid_Feed_Parking_Config::parse('   ');
        $this->assertNull($result['document']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_missing_top_level_keys(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { unset($d['center']); }),
            '"center" is required'
        );
        $this->assert_single_error(
            self::mutated(function (&$d) { unset($d['zones']); }),
            '"zones" is required'
        );
        $this->assert_single_error(
            self::mutated(function (&$d) { unset($d['events']); }),
            '"events" is required'
        );
    }

    public function test_rejects_string_center(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['center']['latitude'] = '47.7779'; }),
            'Center "latitude" must be a number'
        );
    }

    public function test_rejects_string_near_radius(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['nearRadiusMeters'] = '800'; }),
            '"nearRadiusMeters" must be a number'
        );
    }

    public function test_allows_missing_near_radius(): void {
        $raw = self::mutated(function (&$d) { unset($d['nearRadiusMeters']); });
        $result = Masjid_Feed_Parking_Config::parse($raw);
        $this->assertSame([], $result['errors']);
    }

    public function test_rejects_unknown_zone_type(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['zones'][0]['type'] = 'restricted'; }),
            'zone "north-lot": "type" must be one of no-parking, event-restricted, allowed'
        );
    }

    public function test_rejects_duplicate_zone_ids(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['zones'][1]['id'] = $d['zones'][0]['id']; }),
            'is used more than once'
        );
    }

    public function test_rejects_bad_zone_id_format(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['zones'][0]['id'] = 'North Lot'; }),
            '"id" must use lowercase letters, digits, and hyphens'
        );
    }

    public function test_rejects_fewer_than_three_coordinates(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['zones'][0]['coordinates'] = [$d['zones'][0]['coordinates'][0]]; }),
            'must be a list of at least 3 points'
        );
    }

    public function test_rejects_string_coordinates(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['zones'][0]['coordinates'][0]['latitude'] = '47.77882298016584'; }),
            'numeric "latitude" and "longitude"'
        );
    }

    public function test_rejects_swapped_coordinates(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) {
                $point = $d['zones'][0]['coordinates'][0];
                $d['zones'][0]['coordinates'][0] = [
                    'latitude' => $point['longitude'],
                    'longitude' => $point['latitude'],
                ];
            }),
            'coordinates may be swapped'
        );
    }

    public function test_rejects_event_without_schedule(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { unset($d['events'][0]['schedule']); }),
            'event "jummah": "schedule" is required'
        );
    }

    public function test_rejects_event_without_zone_ids(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { unset($d['events'][0]['zoneIds']); }),
            'event "jummah": "zoneIds" must be "all" or a list of zone ids'
        );
    }

    public function test_rejects_event_without_message(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { unset($d['events'][0]['message']); }),
            'event "jummah": "message" is required'
        );
    }

    public function test_rejects_unknown_zone_id_in_event(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['events'][0]['zoneIds'] = ['eid-lot']; }),
            'zoneIds names "eid-lot", which is not a zone'
        );
    }

    public function test_rejects_bad_schedule_time(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['events'][0]['schedule']['start'] = '24:00'; }),
            '"start" must be a 24-hour HH:mm time'
        );
    }

    public function test_rejects_equal_schedule_times(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) {
                $d['events'][0]['schedule']['start'] = '11:30';
                $d['events'][0]['schedule']['end'] = '11:30';
            }),
            '"start" and "end" must differ'
        );
    }

    public function test_rejects_bad_schedule_days(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['events'][0]['schedule']['days'] = [7]; }),
            'numbers from 0 (Sunday) to 6 (Saturday)'
        );
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['events'][0]['schedule']['days'] = ['5']; }),
            'numbers from 0 (Sunday) to 6 (Saturday)'
        );
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['events'][0]['schedule']['days'] = []; }),
            'at least one weekday'
        );
    }

    public function test_accepts_valid_dates_schedule(): void {
        $raw = self::mutated(function (&$d) {
            $d['events'][0]['schedule'] = [
                'type' => 'dates',
                'dates' => ['2027-03-10'],
                'start' => '06:30',
                'end' => '11:00',
            ];
        });
        $result = Masjid_Feed_Parking_Config::parse($raw);
        $this->assertSame([], $result['errors']);
    }

    public function test_rejects_bad_schedule_dates(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) {
                $d['events'][0]['schedule'] = [
                    'type' => 'dates',
                    'dates' => ['2027-02-30'],
                    'start' => '06:30',
                    'end' => '11:00',
                ];
            }),
            'real calendar dates'
        );
        $this->assert_single_error(
            self::mutated(function (&$d) {
                $d['events'][0]['schedule'] = [
                    'type' => 'dates',
                    'dates' => ['March 10'],
                    'start' => '06:30',
                    'end' => '11:00',
                ];
            }),
            'real calendar dates'
        );
    }

    public function test_rejects_unknown_schedule_type(): void {
        $this->assert_single_error(
            self::mutated(function (&$d) { $d['events'][0]['schedule']['type'] = 'daily'; }),
            '"type" must be one of weekly, dates'
        );
    }

    public function test_get_saved_document_roundtrip(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = self::fixture_raw();
        $document = Masjid_Feed_Parking_Config::get_saved_document();
        $this->assertNotNull($document);
        $this->assertCount(14, $document['zones']);

        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = '';
        $this->assertNull(Masjid_Feed_Parking_Config::get_saved_document());

        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = '{ broken';
        $this->assertNull(Masjid_Feed_Parking_Config::get_saved_document());

        unset($GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY]);
        $this->assertNull(Masjid_Feed_Parking_Config::get_saved_document());
    }

    public function test_summarize_counts_zones_and_events(): void {
        $this->assertSame('14 zone(s), 1 event(s).', Masjid_Feed_Parking_Config::summarize(self::fixture_document()));
        $this->assertSame('', Masjid_Feed_Parking_Config::summarize(null));
    }
}