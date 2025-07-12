<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Validator {
    
    public static function validateBoolean($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return ($value === '1' || strtolower($value) === 'true');
        }
        
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        
        return false;
    }
    
    public static function validateInteger($value, $min = null, $max = null) {
        $int_value = intval($value);
        
        if ($min !== null && $int_value < $min) {
            return $min;
        }
        
        if ($max !== null && $int_value > $max) {
            return $max;
        }
        
        return $int_value;
    }
    
    public static function validateString($value, $max_length = 200) {
        $string_value = sanitize_text_field($value);
        
        if (strlen($string_value) > $max_length) {
            return substr($string_value, 0, $max_length);
        }
        
        return $string_value;
    }
    
    public static function validateTextarea($value) {
        return sanitize_textarea_field($value);
    }
    
    public static function validateArray($value, $default = array()) {
        if (!is_array($value)) {
            return $default;
        }
        
        $sanitized = array_map('sanitize_text_field', $value);
        $filtered = array_filter($sanitized);
        
        return array_slice($filtered, 0, 50);
    }
    
    public static function validateSelect($value, $valid_options, $default) {
        return in_array($value, $valid_options, true) ? $value : $default;
    }
    
    public static function validateApiKey($value) {
        return self::validateString($value, 200);
    }
    
    public static function validateSlug($slug) {
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
    
    public static function validateLanguageCode($code) {
        $valid_codes = array(
            'auto', 'zh', 'zh-TW', 'en', 'es', 'fr', 'de', 'ja', 'ko', 'ru', 
            'ar', 'it', 'pt', 'nl', 'pl', 'tr', 'sv', 'da', 'no', 'fi', 'cs', 
            'hu', 'ro', 'bg', 'hr', 'sk', 'sl', 'et', 'lv', 'lt', 'mt', 'el', 'cy'
        );
        
        return in_array($code, $valid_codes, true) ? $code : 'auto';
    }
    
    public static function validatePostTypes($post_types) {
        if (!is_array($post_types)) {
            return array('post', 'page');
        }
        
        $all_post_types = get_post_types(array('public' => true));
        $validated = array();
        
        foreach ($post_types as $post_type) {
            if (in_array($post_type, $all_post_types)) {
                $validated[] = $post_type;
            }
        }
        
        return empty($validated) ? array('post', 'page') : $validated;
    }
    
    public static function validateTaxonomies($taxonomies) {
        if (!is_array($taxonomies)) {
            return array('category', 'post_tag');
        }
        
        $all_taxonomies = get_taxonomies(array('public' => true));
        $validated = array();
        
        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy, $all_taxonomies)) {
                $validated[] = $taxonomy;
            }
        }
        
        return empty($validated) ? array('category', 'post_tag') : $validated;
    }
    
    public static function validateConversionMode($mode) {
        $valid_modes = array('pinyin', 'transliteration', 'translation');
        return self::validateSelect($mode, $valid_modes, 'pinyin');
    }
    
    public static function validateTranslationService($service) {
        $valid_services = array('none', 'google', 'baidu');
        return self::validateSelect($service, $valid_services, 'none');
    }
    
    public static function validateTransliterationMethod($method) {
        $valid_methods = array('basic', 'iconv', 'intl');
        return self::validateSelect($method, $valid_methods, 'basic');
    }
    
    public static function hasRequiredApiCredentials($options) {
        $errors = array();
        
        if ($options['conversion_mode'] === 'translation') {
            $service = $options['translation_service'];
            
            if ($service === 'google' && empty($options['google_api_key'])) {
                $errors[] = __('Google API key is required for Google Translate service.', 'wpslug');
            }
            
            if ($service === 'baidu' && (empty($options['baidu_app_id']) || empty($options['baidu_secret_key']))) {
                $errors[] = __('Baidu App ID and Secret Key are required for Baidu Translate service.', 'wpslug');
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public static function validateSystemRequirements() {
        $requirements = array(
            'php_version' => version_compare(PHP_VERSION, '7.0', '>='),
            'wordpress_version' => version_compare(get_bloginfo('version'), '5.0', '>='),
            'mbstring_extension' => extension_loaded('mbstring'),
            'json_extension' => extension_loaded('json')
        );
        
        $errors = array();
        
        if (!$requirements['php_version']) {
            $errors[] = __('PHP 7.0 or higher is required.', 'wpslug');
        }
        
        if (!$requirements['wordpress_version']) {
            $errors[] = __('WordPress 5.0 or higher is required.', 'wpslug');
        }
        
        if (!$requirements['mbstring_extension']) {
            $errors[] = __('PHP mbstring extension is required.', 'wpslug');
        }
        
        if (!$requirements['json_extension']) {
            $errors[] = __('PHP JSON extension is required.', 'wpslug');
        }
        
        return empty($errors) ? true : $errors;
    }
}