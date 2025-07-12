<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Core {
    private $settings;
    private $converter;
    private $optimizer;
    private $admin;

    public function __construct() {
        $this->settings = new WPSlug_Settings();
        $this->converter = new WPSlug_Converter();
        $this->optimizer = new WPSlug_Optimizer();

        if (is_admin()) {
            $this->admin = new WPSlug_Admin();
        }

        $this->initHooks();
    }

    private function initHooks() {
        add_filter('sanitize_title', array($this, 'processSanitizeTitle'), 9, 3);
        add_filter('wp_insert_post_data', array($this, 'processPostData'), 10, 2);
        add_filter('wp_insert_term_data', array($this, 'processTermData'), 10, 3);
        add_filter('wp_update_term_data', array($this, 'processTermDataUpdate'), 10, 4);
        add_filter('sanitize_file_name', array($this, 'processFileName'), 10, 2);
        add_filter('wp_unique_post_slug', array($this, 'processUniquePostSlug'), 10, 6);
        add_filter('pre_category_nicename', array($this, 'preCategoryNicename'), 10, 2);
        
        add_action('transition_post_status', array($this, 'handlePostStatusTransition'), 10, 3);

        if (is_admin()) {
            add_filter('manage_posts_columns', array($this, 'addSlugColumn'));
            add_action('manage_posts_custom_column', array($this, 'displaySlugColumn'), 10, 2);
            add_filter('manage_pages_columns', array($this, 'addSlugColumn'));
            add_action('manage_pages_custom_column', array($this, 'displaySlugColumn'), 10, 2);
        }
    }

    public function processSanitizeTitle($title, $raw_title = '', $context = 'display') {
        if ($context !== 'save' || empty($title)) {
            return $title;
        }

        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion']) {
                return $title;
            }

            $processed_title = $this->converter->convert($title, $options);
            $processed_title = $this->optimizer->optimize($processed_title, $options);

            return $processed_title;
        } catch (Exception $e) {
            $this->settings->logError('processSanitizeTitle error: ' . $e->getMessage());
            return $title;
        }
    }

    public function processPostData($data, $postarr) {
        if (empty($data['post_title'])) {
            return $data;
        }

        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || !$options['auto_convert']) {
                return $data;
            }

            $post_type = $data['post_type'];
            if (!$this->settings->isPostTypeEnabled($post_type)) {
                return $data;
            }

            if (isset($postarr['wpslug_disable_conversion']) && $postarr['wpslug_disable_conversion']) {
                return $data;
            }

            if (!empty($data['post_name']) && !$this->shouldUpdateSlug($postarr)) {
                return $data;
            }

            $slug = $this->converter->convert($data['post_title'], $options);
            $slug = $this->optimizer->optimize($slug, $options);

            if (!empty($slug) && $slug !== $data['post_name']) {
                $data['post_name'] = $slug;
            }

            return $data;
        } catch (Exception $e) {
            $this->settings->logError('processPostData error: ' . $e->getMessage());
            return $data;
        }
    }

    public function processTermData($data, $taxonomy, $args) {
        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || !$options['auto_convert']) {
                return $data;
            }

            if (!$this->settings->isTaxonomyEnabled($taxonomy)) {
                return $data;
            }

            if (!empty($data['name']) && empty($args['slug'])) {
                $slug = $this->converter->convert($data['name'], $options);
                $slug = $this->optimizer->optimize($slug, $options);

                if (!empty($slug)) {
                    $data['slug'] = $slug;
                }
            }

            return $data;
        } catch (Exception $e) {
            $this->settings->logError('processTermData error: ' . $e->getMessage());
            return $data;
        }
    }

    public function processTermDataUpdate($data, $term_id, $taxonomy, $args) {
        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || !$options['auto_convert']) {
                return $data;
            }

            if (!$this->settings->isTaxonomyEnabled($taxonomy)) {
                return $data;
            }

            if (!empty($data['name']) && empty($args['slug'])) {
                $slug = $this->converter->convert($data['name'], $options);
                $slug = $this->optimizer->optimize($slug, $options);

                if (!empty($slug)) {
                    $data['slug'] = wp_unique_term_slug($slug, (object) $args);
                }
            }

            return $data;
        } catch (Exception $e) {
            $this->settings->logError('processTermDataUpdate error: ' . $e->getMessage());
            return $data;
        }
    }

    public function processFileName($filename, $filename_raw = '') {
        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || $options['disable_file_convert']) {
                return $filename;
            }

            $media_mode = $this->settings->getOption('media_conversion_mode', 'normal');
            $media_prefix = $this->settings->getOption('media_file_prefix', '');

            $parts = explode('.', $filename);
            $extension = '';
            
            if (count($parts) > 1) {
                $extension = array_pop($parts);
                $name = implode('.', $parts);
            } else {
                $name = $filename;
            }

            switch ($media_mode) {
                case 'md5':
                    $converted_name = md5($name . time());
                    break;
                    
                case 'none':
                    $converted_name = $name;
                    break;
                    
                case 'normal':
                default:
                    if ($this->needsConversion($name)) {
                        $converted_name = $this->converter->convert($name, $options);
                        $converted_name = $this->optimizer->optimize($converted_name, $options);
                    } else {
                        $converted_name = $name;
                    }
                    break;
            }

            if (!empty($media_prefix)) {
                $converted_name = $media_prefix . $converted_name;
            }

            return $extension ? $converted_name . '.' . $extension : $converted_name;
        } catch (Exception $e) {
            $this->settings->logError('processFileName error: ' . $e->getMessage());
            return $filename;
        }
    }

    public function processUniquePostSlug($slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug) {
        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || !$options['auto_convert']) {
                return $slug;
            }

            if ($post_type === 'attachment') {
                return $slug;
            }

            if (!$this->settings->isPostTypeEnabled($post_type)) {
                return $slug;
            }

            $old_status = get_post_field('post_status', $post_ID, 'edit');

            if ($old_status !== 'publish' && $post_status === 'publish') {
                $converted_slug = $this->converter->convert($slug, $options);
                $converted_slug = $this->optimizer->optimize($converted_slug, $options);
                if (!empty($converted_slug)) {
                    return $converted_slug;
                }
            }

            return $slug;
        } catch (Exception $e) {
            $this->settings->logError('processUniquePostSlug error: ' . $e->getMessage());
            return $slug;
        }
    }

    public function preCategoryNicename($slug, $name = '') {
        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || !$options['auto_convert']) {
                return $slug;
            }

            if ($slug) {
                return $slug;
            }

            $tag_name = isset($_POST['tag-name']) ? sanitize_text_field($_POST['tag-name']) : $name;

            if ($tag_name) {
                $converted_slug = $this->converter->convert($tag_name, $options);
                $converted_slug = $this->optimizer->optimize($converted_slug, $options);
                return sanitize_title($converted_slug);
            }

            return $slug;
        } catch (Exception $e) {
            $this->settings->logError('preCategoryNicename error: ' . $e->getMessage());
            return $slug;
        }
    }

    public function handlePostStatusTransition($new_status, $old_status, $post) {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        try {
            $options = $this->settings->getOptions();

            if (!$options['enable_conversion'] || !$options['auto_convert']) {
                return;
            }

            if (!$this->settings->isPostTypeEnabled($post->post_type)) {
                return;
            }

            $new_slug = $this->converter->convert($post->post_title, $options);
            $new_slug = $this->optimizer->optimize($new_slug, $options);

            if (!empty($new_slug) && $new_slug !== $post->post_name) {
                $unique_slug = $this->optimizer->generateUniqueSlug($new_slug, $post->ID, $post->post_type);

                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_name' => $unique_slug
                ));
            }
        } catch (Exception $e) {
            $this->settings->logError('handlePostStatusTransition error: ' . $e->getMessage());
        }
    }

    public function addSlugColumn($columns) {
        try {
            $options = $this->settings->getOptions();

            if (isset($options['show_slug_column']) && $options['show_slug_column']) {
                $columns['wpslug_slug'] = __('Slug', 'wpslug');
            }

            return $columns;
        } catch (Exception $e) {
            return $columns;
        }
    }

    public function displaySlugColumn($column_name, $post_id) {
        if ($column_name !== 'wpslug_slug') {
            return;
        }

        try {
            $post = get_post($post_id);
            if (!$post || $post->post_status === 'trash') {
                echo '<span class="wpslug-na">' . esc_html__('N/A', 'wpslug') . '</span>';
                return;
            }

            $permalink = get_permalink($post_id);
            $home_url = trailingslashit(home_url());

            if ($permalink && strpos($permalink, $home_url) === 0) {
                $slug = str_replace($home_url, '/', $permalink);
            } else {
                $slug = '/' . $post->post_name;
            }

            echo '<code class="wpslug-slug">' . esc_html($slug) . '</code>';
        } catch (Exception $e) {
            echo '<span class="wpslug-error">' . esc_html__('Error', 'wpslug') . '</span>';
        }
    }

    private function needsConversion($text) {
        return preg_match('/[^\x00-\x7F]/', $text);
    }

    private function shouldUpdateSlug($postarr) {
        if (isset($postarr['post_status']) && $postarr['post_status'] === 'auto-draft') {
            return true;
        }

        if (isset($postarr['ID']) && $postarr['ID'] > 0) {
            $existing_post = get_post($postarr['ID']);
            if ($existing_post && $existing_post->post_status === 'auto-draft') {
                return true;
            }
        }

        return false;
    }

    public function activate() {
        try {
            $this->settings->createDefaultOptions();
            flush_rewrite_rules();
        } catch (Exception $e) {
            $this->settings->logError('activate error: ' . $e->getMessage());
        }
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function getSettings() {
        return $this->settings;
    }

    public function getConverter() {
        return $this->converter;
    }

    public function getOptimizer() {
        return $this->optimizer;
    }

    public function getAdmin() {
        return $this->admin;
    }
}