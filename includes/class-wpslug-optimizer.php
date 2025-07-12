<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Optimizer {

    public function __construct() {
        
    }

    public function optimize($text, $options = array()) {
        if (empty($text)) {
            return '';
        }

        $force_lowercase = isset($options['force_lowercase']) ? $options['force_lowercase'] : true;
        $max_length = isset($options['max_length']) ? (int)$options['max_length'] : 50;
        $conversion_mode = isset($options['conversion_mode']) ? $options['conversion_mode'] : 'pinyin';
        $pinyin_format = isset($options['pinyin_format']) ? $options['pinyin_format'] : 'full';

        $is_first_letter_mode = ($conversion_mode === 'pinyin' && $pinyin_format === 'first');
        $should_apply_seo = isset($options['enable_seo_optimization']) ? $options['enable_seo_optimization'] : true;
        
        if ($should_apply_seo && !$is_first_letter_mode) {
            $text = $this->applySEOOptimizations($text, $options);
        }

        if ($max_length > 0 && strlen($text) > $max_length) {
            $text = $this->truncateSlug($text, $max_length);
        }

        $text = $this->finalCleanup($text);

        if ($force_lowercase) {
            $text = strtolower($text);
        }

        return $text;
    }

    private function applySEOOptimizations($text, $options) {
        if (isset($options['smart_punctuation']) && $options['smart_punctuation']) {
            $text = $this->handleSmartPunctuation($text);
        }

        if (isset($options['mixed_content_optimization']) && $options['mixed_content_optimization']) {
            $text = $this->optimizeMixedContent($text);
        }

        if (isset($options['remove_stop_words']) && $options['remove_stop_words']) {
            $text = $this->removeStopWords($text, $options);
        }

        if (isset($options['seo_max_words']) && $options['seo_max_words'] > 0) {
            $text = $this->limitWords($text, $options['seo_max_words']);
        }

        return $text;
    }

    private function handleSmartPunctuation($text) {
        $text = str_replace(array(':', ';', ','), '-', $text);
        $text = str_replace(array('.', '?', '!', '(', ')', '[', ']', '<', '>'), '', $text);
        $text = str_replace(array('|', '/', '\\'), '-', $text);
        $text = str_replace('&', 'and', $text);
        $text = str_replace('+', 'plus', $text);
        $text = str_replace('=', 'equal', $text);
        $text = str_replace('%', 'percent', $text);
        $text = str_replace('#', 'hash', $text);
        $text = str_replace('@', 'at', $text);
        $text = str_replace('$', 'dollar', $text);
        
        return $text;
    }

    private function optimizeMixedContent($text) {
        $text = preg_replace('/(\d+)(\p{Han})/u', '$1-$2', $text);
        $text = preg_replace('/(\p{Han})(\d+)/u', '$1-$2', $text);
        
        $text = preg_replace('/([a-zA-Z])(\p{Han})/u', '$1-$2', $text);
        $text = preg_replace('/(\p{Han})([a-zA-Z])/u', '$1-$2', $text);
        
        $text = preg_replace('/([a-zA-Z])(\d+)/u', '$1$2', $text);
        $text = preg_replace('/(\d+)([a-zA-Z])/u', '$1$2', $text);
        
        $text = preg_replace('/\s*-\s*/', '-', $text);
        
        return $text;
    }

    private function removeStopWords($text, $options) {
        if (!isset($options['stop_words_list'])) {
            return $text;
        }

        $stop_words_string = $options['stop_words_list'];
        if (empty($stop_words_string)) {
            return $text;
        }

        $stop_words = explode(',', $stop_words_string);
        $stop_words = array_map('trim', $stop_words);
        $stop_words = array_filter($stop_words);

        if (empty($stop_words)) {
            return $text;
        }

        $words = preg_split('/[-\s_]+/', $text);
        $filtered_words = array();

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) {
                continue;
            }

            if (!in_array(strtolower($word), array_map('strtolower', $stop_words))) {
                $filtered_words[] = $word;
            }
        }

        return implode('-', $filtered_words);
    }

    private function limitWords($text, $max_words) {
        if ($max_words <= 0) {
            return $text;
        }

        $words = preg_split('/[-\s_]+/', $text);
        $words = array_filter($words, function($word) {
            return !empty(trim($word));
        });

        if (count($words) <= $max_words) {
            return $text;
        }

        $limited_words = array_slice($words, 0, $max_words);
        return implode('-', $limited_words);
    }

    private function finalCleanup($slug) {
        $slug = preg_replace('/[^a-zA-Z0-9\-_\p{Han}]/u', '', $slug);
        $slug = preg_replace('/\-+/', '-', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '-_');
        
        return $slug;
    }

    public function truncateSlug($slug, $max_length) {
        if (strlen($slug) <= $max_length) {
            return $slug;
        }

        $parts = explode('-', $slug);
        $result = '';
        
        foreach ($parts as $part) {
            if (strlen($result . '-' . $part) <= $max_length) {
                $result .= ($result ? '-' : '') . $part;
            } else {
                break;
            }
        }
        
        return $result ?: substr($slug, 0, $max_length);
    }

    public function generateUniqueSlug($slug, $post_id = 0, $post_type = 'post') {
        global $wpdb;
        
        $original_slug = $slug;
        $suffix = 1;
        
        while (true) {
            $query = $wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_name = %s 
                AND post_type = %s 
                AND ID != %d
                LIMIT 1
            ", $slug, $post_type, $post_id);
            
            if (!$wpdb->get_var($query)) {
                break;
            }
            
            $slug = $original_slug . '-' . $suffix;
            $suffix++;
            
            if ($suffix > 100) {
                break;
            }
        }
        
        return $slug;
    }

    public function validateSlug($slug) {
        if (empty($slug)) {
            return false;
        }
        
        if (strlen($slug) > 200) {
            return false;
        }
        
        if (preg_match('/[^a-zA-Z0-9\-_\p{Han}]/u', $slug)) {
            return false;
        }
        
        return true;
    }

    public function detectLanguage($text) {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            return 'zh';
        }
        
        return 'en';
    }

    public function getSEOScore($original_text, $optimized_slug) {
        $score = 0;
        $max_score = 100;
        
        if (strlen($optimized_slug) > 0) {
            $score += 20;
        }
        
        if (strlen($optimized_slug) <= 60) {
            $score += 15;
        }
        
        if (preg_match('/^[a-zA-Z0-9\-_\p{Han}]+$/u', $optimized_slug)) {
            $score += 15;
        }
        
        if (substr_count($optimized_slug, '-') <= 5) {
            $score += 10;
        }
        
        if (!preg_match('/^-|-$/', $optimized_slug)) {
            $score += 10;
        }
        
        $word_count = count(explode('-', $optimized_slug));
        if ($word_count >= 2 && $word_count <= 5) {
            $score += 15;
        }
        
        if (strtolower($optimized_slug) === $optimized_slug) {
            $score += 5;
        }
        
        if (!preg_match('/\d{4,}/', $optimized_slug)) {
            $score += 5;
        }
        
        if (strlen($optimized_slug) >= 10) {
            $score += 5;
        }
        
        return min($score, $max_score);
    }

    public function suggestAlternatives($text, $options = array()) {
        $suggestions = array();
        
        $base_slug = $this->optimize($text, $options);
        $suggestions[] = $base_slug;
        
        $short_options = $options;
        $short_options['seo_max_words'] = 3;
        $short_slug = $this->optimize($text, $short_options);
        if ($short_slug !== $base_slug) {
            $suggestions[] = $short_slug;
        }
        
        $no_stop_words_options = $options;
        $no_stop_words_options['remove_stop_words'] = true;
        $no_stop_words_slug = $this->optimize($text, $no_stop_words_options);
        if ($no_stop_words_slug !== $base_slug) {
            $suggestions[] = $no_stop_words_slug;
        }
        
        $minimal_options = $options;
        $minimal_options['seo_max_words'] = 2;
        $minimal_options['remove_stop_words'] = true;
        $minimal_slug = $this->optimize($text, $minimal_options);
        if ($minimal_slug !== $base_slug && !in_array($minimal_slug, $suggestions)) {
            $suggestions[] = $minimal_slug;
        }
        
        return array_unique($suggestions);
    }
}