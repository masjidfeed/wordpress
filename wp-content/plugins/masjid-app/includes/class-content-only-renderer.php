<?php
/**
 * Content-only front-end rendering for mobile app web views.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_App_Content_Only_Renderer {

    const QUERY_VAR = 'render';
    const QUERY_VALUE = 'contentOnly';

    public function __construct() {
        add_filter('query_vars', array($this, 'register_query_var'));
        add_filter('render_block_core/template-part', array($this, 'filter_template_part'), 10, 2);
        add_filter('render_block_core/post-title', array($this, 'filter_post_title'), 10, 3);
        add_filter('template_include', array($this, 'filter_classic_template'), 99);
        add_filter('body_class', array($this, 'add_body_class'));
    }

    public function register_query_var($query_vars) {
        $query_vars[] = self::QUERY_VAR;
        return $query_vars;
    }

    public function filter_template_part($block_content, $block) {
        if (!wp_is_block_theme() || !$this->is_content_only_request()) {
            return $block_content;
        }

        $area = $this->get_template_part_area($block);
        return in_array($area, array('header', 'footer'), true) ? '' : $block_content;
    }

    public function filter_post_title($block_content, $block, $instance) {
        if (!wp_is_block_theme() || !$this->is_content_only_request()) {
            return $block_content;
        }

        $post_id = isset($instance->context['postId']) ? (int) $instance->context['postId'] : 0;
        return get_queried_object_id() === $post_id ? '' : $block_content;
    }

    public function filter_classic_template($template) {
        if (wp_is_block_theme() || !$this->is_content_only_request()) {
            return $template;
        }

        $content_only_template = MASJIDAPP_PLUGIN_DIR . 'templates/content-only.php';
        return is_readable($content_only_template) ? $content_only_template : $template;
    }

    public function add_body_class($classes) {
        if ($this->is_content_only_request()) {
            $classes[] = 'masjidapp-content-only-view';
        }
        return $classes;
    }

    private function is_content_only_request() {
        return !is_admin()
            && is_singular()
            && (bool) Masjid_App_Settings::get_option('content_only_rendering_enabled', 0)
            && self::QUERY_VALUE === get_query_var(self::QUERY_VAR);
    }

    private function get_template_part_area($block) {
        $attributes = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
        $area = sanitize_key($attributes['area'] ?? '');
        if (in_array($area, array('header', 'footer'), true)) {
            return $area;
        }

        $slug = sanitize_key($attributes['slug'] ?? '');
        if ('' === $slug) {
            return '';
        }

        $template_part = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template_part');
        if ($template_part && isset($template_part->area)) {
            return sanitize_key($template_part->area);
        }

        return in_array($slug, array('header', 'footer'), true) ? $slug : '';
    }
}