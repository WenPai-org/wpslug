<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Translator {
    private $converter;

    /**
     * 防止循环调用的静态标志
     * 当使用 WPMind 翻译时，WPMind 内部可能触发 sanitize_title filter
     * 如果没有保护，会导致无限递归
     * 
     * @var bool
     */
    private static $is_translating = false;

    public function __construct() {
        $this->converter = null;
    }

    public function translate($text, $options = array()) {
        if (empty($text)) {
            return '';
        }

        // 防止循环调用：如果正在翻译中，直接回退到拼音
        if (self::$is_translating) {
            return $this->fallbackTranslate($text, $options);
        }

        $service = isset($options['translation_service']) ? $options['translation_service'] : 'none';
        
        switch ($service) {
            case 'wpmind':
                // WPMind 服务需要循环保护
                self::$is_translating = true;
                try {
                    $result = $this->translateWPMind($text, $options);
                } finally {
                    self::$is_translating = false;
                }
                return $result;
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

    /**
     * 使用 WPMind AI 翻译
     *
     * @param string $text 要翻译的文本
     * @param array $options 选项
     * @return string 翻译后的文本（slug 格式）
     */
    private function translateWPMind($text, $options) {
        $debug_mode = isset($options['debug_mode']) && $options['debug_mode'];
        
        // 1. 先检查本地缓存（避免重复调用 API）
        $cache_key = 'wpslug_wpmind_' . md5($text);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            if ($debug_mode) {
                error_log('[WPSlug] WPMind cache hit for: ' . $text);
            }
            return $cached;
        }

        // 2. 检查 WPMind 是否可用
        if (!function_exists('wpmind_is_available') || !wpmind_is_available()) {
            if ($debug_mode) {
                error_log('[WPSlug] WPMind not available, falling back to pinyin');
            }
            return $this->fallbackTranslate($text, $options);
        }

        // 3. 文本长度限制（避免超时）
        if (mb_strlen($text) > 200) {
            if ($debug_mode) {
                error_log('[WPSlug] Text too long (' . mb_strlen($text) . ' chars), using pinyin');
            }
            return $this->fallbackTranslate($text, $options);
        }

        // 4. 中文字符数限制
        $chinese_count = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
        if ($chinese_count > 50) {
            if ($debug_mode) {
                error_log('[WPSlug] Too many Chinese characters (' . $chinese_count . '), using pinyin');
            }
            return $this->fallbackTranslate($text, $options);
        }

        // 5. 获取语言设置
        $source_lang = isset($options['translation_source_lang']) ? $options['translation_source_lang'] : 'zh';
        $target_lang = isset($options['translation_target_lang']) ? $options['translation_target_lang'] : 'en';

        // 6. 调用 WPMind API
        $start_time = microtime(true);
        
        $result = wpmind_translate($text, $source_lang, $target_lang, [
            'context'     => 'wpslug_translation',
            'format'      => 'slug',
            'cache_ttl'   => 86400,  // WPMind 内部缓存 1 天
            'max_tokens'  => 100,
            'temperature' => 0.3,
        ]);

        $elapsed_time = round((microtime(true) - $start_time) * 1000);

        // 7. 处理结果
        if (is_wp_error($result)) {
            if ($debug_mode) {
                error_log('[WPSlug] WPMind error: ' . $result->get_error_message() . ' (took ' . $elapsed_time . 'ms)');
            }
            return $this->fallbackTranslate($text, $options);
        }

        $slug = $this->cleanTranslatedText($result);

        // 8. 验证结果有效性
        if (empty($slug)) {
            if ($debug_mode) {
                error_log('[WPSlug] WPMind returned empty result, using pinyin');
            }
            return $this->fallbackTranslate($text, $options);
        }

        // 9. 缓存结果（7 天）
        set_transient($cache_key, $slug, 7 * DAY_IN_SECONDS);

        if ($debug_mode) {
            error_log('[WPSlug] WPMind translated "' . $text . '" to "' . $slug . '" in ' . $elapsed_time . 'ms');
        }

        return $slug;
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