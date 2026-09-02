<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class EventSourcesTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__test_rest_responses'] = [];
        $GLOBALS['__test_rest_requests'] = [];
        $GLOBALS['__test_terms'] = [];
        $GLOBALS['__test_posts'] = [];
    }

    private function queue_rest_response($data, int $status = 200): void {
        $GLOBALS['__test_rest_responses'][] = new WP_REST_Response($data, $status);
    }

    private function register_term(int $term_id, string $taxonomy, string $slug): void {
        $GLOBALS['__test_terms'][$taxonomy . ':' . $term_id] = new WP_Term([
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'slug' => $slug,
            'name' => ucfirst($slug),
        ]);
    }

    public function test_filter_taxonomies_use_the_events_calendar_taxonomy_for_tec(): void {
        $taxonomies = Masjid_Feed_Event_Sources::get_filter_taxonomies('the_events_calendar');
        $this->assertSame(
            Masjid_Feed_Event_Source_The_Events_Calendar::EVENTS_TAXONOMY,
            $taxonomies['categories']
        );
        $this->assertSame('post_tag', $taxonomies['tags']);
    }

    public function test_filter_taxonomies_use_regular_post_taxonomies_by_default(): void {
        $taxonomies = Masjid_Feed_Event_Sources::get_filter_taxonomies('');
        $this->assertSame('category', $taxonomies['categories']);
        $this->assertSame('post_tag', $taxonomies['tags']);
    }

    public function test_get_term_slugs_only_matches_terms_in_the_given_taxonomy(): void {
        $this->register_term(11, 'tribe_events_cat', 'ramadan');
        $this->register_term(99, 'category', 'news');

        $slugs = Masjid_Feed_Event_Sources::get_term_slugs([11, 99], 'tribe_events_cat');
        $this->assertSame(['ramadan'], $slugs);
    }

    public function test_upcoming_events_requests_filter_by_event_category_slugs(): void {
        $this->register_term(11, 'tribe_events_cat', 'ramadan');
        $this->register_term(12, 'tribe_events_cat', 'lectures');
        $this->register_term(99, 'category', 'news');
        $this->register_term(21, 'post_tag', 'youth');
        $this->queue_rest_response([
            'events' => [
                [
                    'id' => 7,
                    'start_date' => '2026-09-05 10:00:00',
                    'venue' => [['venue' => 'Main Hall']],
                    'categories' => [['slug' => 'ramadan']],
                ],
            ],
        ]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $events = $source->get_upcoming_events([
            'events_categories' => [11, 12, 99],
            'events_tags' => [21],
        ]);

        $this->assertCount(1, $GLOBALS['__test_rest_requests']);
        $request = $GLOBALS['__test_rest_requests'][0];
        $this->assertSame(Masjid_Feed_Event_Source_The_Events_Calendar::ARCHIVE_ROUTE, $request->get_route());
        $this->assertSame('ramadan,lectures', $request->get_param('categories'));
        $this->assertSame('youth', $request->get_param('tags'));

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]['isEvent']);
        $this->assertSame(7, $events[0]['postId']);
        $this->assertSame('ramadan', $events[0]['category']);
        $this->assertSame('Main Hall', $events[0]['location']);
        $this->assertNotNull($events[0]['eventDateTime']);
    }

    public function test_upcoming_events_without_category_filters_omit_taxonomy_params(): void {
        $this->queue_rest_response(['events' => []]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $source->get_upcoming_events([]);

        $request = $GLOBALS['__test_rest_requests'][0];
        $this->assertNull($request->get_param('categories'));
        $this->assertNull($request->get_param('tags'));
    }

    public function test_upcoming_events_default_limit_requests_20_events(): void {
        $this->queue_rest_response(['events' => []]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $source->get_upcoming_events([]);

        $request = $GLOBALS['__test_rest_requests'][0];
        $this->assertSame(20, $request->get_param('per_page'));
    }

    public function test_upcoming_events_limit_controls_per_page_and_result_count(): void {
        $this->queue_rest_response(['events' => $this->event_payload_rows([21, 22, 23])]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $events = $source->get_upcoming_events([], 2);

        $request = $GLOBALS['__test_rest_requests'][0];
        $this->assertSame(2, $request->get_param('per_page'));
        $this->assertCount(2, $events);
    }

    public function test_upcoming_events_limit_is_capped_at_100(): void {
        $this->queue_rest_response(['events' => []]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $source->get_upcoming_events([], 5000);

        $request = $GLOBALS['__test_rest_requests'][0];
        $this->assertSame(50, $request->get_param('per_page'));
    }

    public function test_upcoming_events_paginates_until_limit_is_reached(): void {
        $this->queue_rest_response(['events' => $this->event_payload_rows(range(101, 150))]);
        $this->queue_rest_response(['events' => $this->event_payload_rows(range(151, 210))]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $events = $source->get_upcoming_events([], 60);

        $this->assertCount(2, $GLOBALS['__test_rest_requests']);
        $this->assertSame(50, $GLOBALS['__test_rest_requests'][0]->get_param('per_page'));
        $this->assertSame(10, $GLOBALS['__test_rest_requests'][1]->get_param('per_page'));
        $this->assertSame(2, $GLOBALS['__test_rest_requests'][1]->get_param('page'));
        $this->assertCount(60, $events);
    }

    private function event_payload_rows(array $ids): array {
        $rows = [];
        foreach ($ids as $index => $id) {
            $rows[] = [
                'id' => $id,
                'start_date' => sprintf('2026-09-%02d 10:00:00', $index + 1),
                'venue' => [['venue' => 'Main Hall']],
                'categories' => [['slug' => 'community']],
            ];
        }
        return $rows;
    }

    public function test_build_post_includes_event_category_from_payload(): void {
        $GLOBALS['__test_posts'][7] = (object) [
            'post_type' => 'tribe_events',
            'post_title' => 'Community Iftar',
            'post_content' => 'Details',
        ];
        $this->queue_rest_response([
            'id' => 7,
            'start_date' => '2026-09-05 10:00:00',
            'categories' => [
                ['slug' => 'uncategorized'],
                ['slug' => 'community'],
            ],
        ]);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $post = $source->build_post(7);

        $this->assertNotNull($post);
        $this->assertTrue($post['isEvent']);
        $this->assertSame('community', $post['category']);
    }

    public function test_build_post_category_falls_back_to_event_terms(): void {
        $GLOBALS['__test_posts'][8] = (object) [
            'post_type' => 'tribe_events',
            'post_title' => 'Lecture',
            'post_content' => 'Details',
        ];
        $this->register_term(12, 'tribe_events_cat', 'lectures');
        $GLOBALS['__test_terms']['tribe_events_cat:8'] = [new WP_Term([
            'term_id' => 12,
            'taxonomy' => 'tribe_events_cat',
            'slug' => 'lectures',
            'name' => 'Lectures',
        ])];
        $this->queue_rest_response(['id' => 8, 'start_date' => '2026-09-06 09:00:00']);

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $post = $source->build_post(8);

        $this->assertNotNull($post);
        $this->assertSame('lectures', $post['category']);
    }

    public function test_build_post_ignores_non_event_posts(): void {
        $GLOBALS['__test_posts'][9] = (object) ['post_type' => 'post'];

        $source = new Masjid_Feed_Event_Source_The_Events_Calendar();
        $this->assertNull($source->build_post(9));
        $this->assertCount(0, $GLOBALS['__test_rest_requests']);
    }
}
