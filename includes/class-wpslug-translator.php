<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Translator {
    private $converter;

    public function __construct() {
        $this->converter = null;
    }

    public function translate($text, $options = array()) {
        if (empty($text)) {
            return '';
        }

        $service = isset($options['translation_service']) ? $options['translation_service'] : 'none';
        
        switch ($service) {
            case 'google':
                return $this->translateGoogle($text, $options);
            case 'baidu':
                return $this->translateBaidu($text, $options);
            case 'none':
            default:
                return $this->fallbackTranslate($text, $options);
        }
    }

    private function translateGoogle($text, $options) {
        $api_key = isset($options['google_api_key']) ? trim($options['google_api_key']) : '';
        $source_lang = isset($options['translation_source_lang']) ? $options['translation_source_lang'] : 'auto';
        $target_lang = isset($options['translation_target_lang']) ? $options['translation_target_lang'] : 'en';

        if (empty($api_key)) {
            return $this->fallbackTranslate($text, $options);
        }

        if (strlen($text) > 5000) {
            return $this->fallbackTranslate($text, $options);
        }

        try {
            $url = 'https://translation.googleapis.com/language/translate/v2';
            $params = array(
                'key' => $api_key,
                'q' => $text,
                'source' => $source_lang,
                'target' => $target_lang,
                'format' => 'text'
            );

            $response = wp_remote_post($url, array(
                'timeout' => 15,
                'body' => $params,
                'headers' => array(
                    'User-Agent' => 'WPSlug/' . WPSLUG_VERSION
                )
            ));

            if (is_wp_error($response)) {
                if (isset($options['debug_mode']) && $options['debug_mode']) {
                    error_log('WPSlug Google Translate Error: ' . $response->get_error_message());
                }
                return $this->fallbackTranslate($text, $options);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                if (isset($options['debug_mode']) && $options['debug_mode']) {
                    error_log('WPSlug Google Translate HTTP Error: ' . $response_code);
                }
                return $this->fallbackTranslate($text, $options);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['data']['translations'][0]['translatedText'])) {
                $translated = $data['data']['translations'][0]['translatedText'];
                return $this->cleanTranslatedText($translated);
            }

            return $this->fallbackTranslate($text, $options);
        } catch (Exception $e) {
            if (isset($options['debug_mode']) && $options['debug_mode']) {
                error_log('WPSlug translateGoogle error: ' . $e->getMessage());
            }
            return $this->fallbackTranslate($text, $options);
        }
    }

    private function translateBaidu($text, $options) {
        $app_id = isset($options['baidu_app_id']) ? trim($options['baidu_app_id']) : '';
        $secret_key = isset($options['baidu_secret_key']) ? trim($options['baidu_secret_key']) : '';
        $source_lang = isset($options['translation_source_lang']) ? $options['translation_source_lang'] : 'auto';
        $target_lang = isset($options['translation_target_lang']) ? $options['translation_target_lang'] : 'en';

        if (empty($app_id) || empty($secret_key)) {
            return $this->fallbackTranslate($text, $options);
        }

        if (strlen($text) > 6000) {
            return $this->fallbackTranslate($text, $options);
        }

        try {
            $salt = wp_rand(10000, 99999);
            $sign = md5($app_id . $text . $salt . $secret_key);

            $url = 'https://fanyi-api.baidu.com/api/trans/vip/translate';
            $params = array(
                'q' => $text,
                'from' => $source_lang,
                'to' => $target_lang,
                'appid' => $app_id,
                'salt' => $salt,
                'sign' => $sign
            );

            $response = wp_remote_post($url, array(
                'timeout' => 15,
                'body' => $params,
                'headers' => array(
                    'User-Agent' => 'WPSlug/' . WPSLUG_VERSION
                )
            ));

            if (is_wp_error($response)) {
                if (isset($options['debug_mode']) && $options['debug_mode']) {
                    error_log('WPSlug Baidu Translate Error: ' . $response->get_error_message());
                }
                return $this->fallbackTranslate($text, $options);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                if (isset($options['debug_mode']) && $options['debug_mode']) {
                    error_log('WPSlug Baidu Translate HTTP Error: ' . $response_code);
                }
                return $this->fallbackTranslate($text, $options);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['trans_result'][0]['dst'])) {
                $translated = $data['trans_result'][0]['dst'];
                return $this->cleanTranslatedText($translated);
            }

            if (isset($data['error_code'])) {
                if (isset($options['debug_mode']) && $options['debug_mode']) {
                    error_log('WPSlug Baidu Translate API Error: ' . $data['error_code']);
                }
            }

            return $this->fallbackTranslate($text, $options);
        } catch (Exception $e) {
            if (isset($options['debug_mode']) && $options['debug_mode']) {
                error_log('WPSlug translateBaidu error: ' . $e->getMessage());
            }
            return $this->fallbackTranslate($text, $options);
        }
    }

    private function fallbackTranslate($text, $options) {
        if ($this->converter === null) {
            $this->converter = new WPSlug_Converter();
        }
        
        $fallback_options = $options;
        $fallback_options['conversion_mode'] = 'pinyin';
        
        return $this->converter->convert($text, $fallback_options);
    }

    private function cleanTranslatedText($text) {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/&.+?;/', '', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/\-+/', '-', $text);
        $text = trim($text, '-_');
        
        return $text;
    }

    public function batchTranslate($items, $options = array()) {
        $results = array();

        if (!is_array($items)) {
            return $results;
        }

        foreach ($items as $item) {
            try {
                $translated = $this->translate($item, $options);
                $results[] = array(
                    'original' => $item,
                    'translated' => $translated,
                    'service' => isset($options['translation_service']) ? $options['translation_service'] : 'none'
                );
            } catch (Exception $e) {
                if (isset($options['debug_mode']) && $options['debug_mode']) {
                    error_log('WPSlug batchTranslate error: ' . $e->getMessage());
                }
                $results[] = array(
                    'original' => $item,
                    'translated' => sanitize_title($item),
                    'service' => 'fallback'
                );
            }
        }

        return $results;
    }

    public function getSupportedServices() {
        return array('none', 'google', 'baidu');
    }

    public function isServiceSupported($service) {
        return in_array($service, $this->getSupportedServices());
    }

    public function isServiceConfigured($service, $options) {
        switch ($service) {
            case 'google':
                return !empty($options['google_api_key']);
            case 'baidu':
                return !empty($options['baidu_app_id']) && !empty($options['baidu_secret_key']);
            case 'none':
            default:
                return true;
        }
    }

    public function detectLanguage($text) {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            return 'zh';
        }
        if (preg_match('/[\x{0400}-\x{04ff}]/u', $text)) {
            return 'ru';
        }
        if (preg_match('/[\x{0590}-\x{05ff}]/u', $text)) {
            return 'he';
        }
        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) {
            return 'ar';
        }
        if (preg_match('/[\x{3040}-\x{309f}]/u', $text)) {
            return 'ja';
        }
        if (preg_match('/[\x{30a0}-\x{30ff}]/u', $text)) {
            return 'ja';
        }
        if (preg_match('/[\x{ac00}-\x{d7af}]/u', $text)) {
            return 'ko';
        }
        
        return 'en';
    }
}