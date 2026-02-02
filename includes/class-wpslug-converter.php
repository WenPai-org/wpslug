<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Converter {
    private $pinyin;
    private $transliterator;
    private $translator;
    private $settings;

    public function __construct() {
        $this->pinyin = WPSlug_Pinyin::getInstance();
        $this->transliterator = new WPSlug_Transliterator();
        $this->translator = new WPSlug_Translator();
        $this->settings = new WPSlug_Settings();
    }

    public function convert($text, $options = array()) {
        if (empty($text)) {
            return '';
        }

        $mode = isset($options['conversion_mode']) ? $options['conversion_mode'] : 'pinyin';
        $start_time = microtime(true);
        
        try {
            switch ($mode) {
                case 'pinyin':
                    $result = $this->convertPinyin($text, $options);
                    break;
                case 'semantic_pinyin':
                    $result = $this->convertSemanticPinyin($text, $options);
                    break;
                case 'transliteration':
                    $result = $this->convertTransliteration($text, $options);
                    break;
                case 'translation':
                    $result = $this->convertTranslation($text, $options);
                    break;
                default:
                    $result = $this->convertPinyin($text, $options);
            }
            
            $execution_time = microtime(true) - $start_time;
            $this->settings->updateConversionStats($mode, true, $execution_time);
            
            return $result;
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            $this->settings->updateConversionStats($mode, false, $execution_time);
            $this->settings->logError('Conversion error in mode ' . $mode . ': ' . $e->getMessage(), array(
                'text' => $text,
                'mode' => $mode,
                'options' => $options
            ));
            
            return $this->cleanBasicSlug($text, $options);
        }
    }

    private function convertPinyin($text, $options) {
        try {
            if ($this->detectLanguage($text) === 'zh') {
                return $this->pinyin->convertToPinyin($text, $options);
            } else {
                return $this->cleanBasicSlug($text, $options);
            }
        } catch (Exception $e) {
            $this->settings->logError('Pinyin conversion error: ' . $e->getMessage());
            return $this->cleanBasicSlug($text, $options);
        }
    }

    /**
     * 语义化拼音转换（使用 WPMind AI）
     * 
     * 与普通拼音不同，这个方法会按词语分隔而非按字分隔
     * 例如 "你好世界" → "nihao-shijie" 而非 "ni-hao-shi-jie"
     */
    private function convertSemanticPinyin($text, $options) {
        $debug_mode = isset($options['debug_mode']) && $options['debug_mode'];
        
        try {
            // 1. 检查是否是中文
            if ($this->detectLanguage($text) !== 'zh') {
                return $this->cleanBasicSlug($text, $options);
            }

            // 2. 检查 WPMind 是否可用
            if (!function_exists('wpmind_pinyin') || !function_exists('wpmind_is_available') || !wpmind_is_available()) {
                if ($debug_mode) {
                    error_log('[WPSlug] WPMind not available for semantic pinyin, falling back to regular pinyin');
                }
                return $this->convertPinyin($text, $options);
            }

            // 3. 文本长度限制（避免超时）
            if (mb_strlen($text) > 200) {
                if ($debug_mode) {
                    error_log('[WPSlug] Text too long for semantic pinyin, using regular pinyin');
                }
                return $this->convertPinyin($text, $options);
            }

            // 4. 调用 WPMind AI 进行语义化拼音转换
            $result = wpmind_pinyin($text, [
                'context'   => 'wpslug_semantic_pinyin',
                'cache_ttl' => 604800, // 7天
            ]);

            // 5. 处理结果
            if (is_wp_error($result)) {
                if ($debug_mode) {
                    error_log('[WPSlug] WPMind pinyin error: ' . $result->get_error_message());
                }
                return $this->convertPinyin($text, $options);
            }

            // 6. 清理结果
            $slug = $this->cleanSemanticPinyinResult($result, $options);

            if (empty($slug)) {
                if ($debug_mode) {
                    error_log('[WPSlug] WPMind returned empty pinyin, using regular pinyin');
                }
                return $this->convertPinyin($text, $options);
            }

            if ($debug_mode) {
                error_log('[WPSlug] Semantic pinyin: "' . $text . '" → "' . $slug . '"');
            }

            return $slug;

        } catch (Exception $e) {
            $this->settings->logError('Semantic pinyin error: ' . $e->getMessage());
            return $this->convertPinyin($text, $options);
        }
    }

    /**
     * 清理语义化拼音结果
     */
    private function cleanSemanticPinyinResult($text, $options) {
        // 移除可能的引号和空格
        $text = trim($text, " \t\n\r\0\x0B\"'");
        
        // 确保只有小写字母、数字和连字符
        $text = preg_replace('/[^a-zA-Z0-9\-]/', '-', $text);
        $text = strtolower($text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        
        return $text;
    }

    private function convertTransliteration($text, $options) {
        try {
            $detected_lang = $this->detectLanguage($text);
            
            if (in_array($detected_lang, array('ru', 'ar', 'el', 'he'))) {
                return $this->transliterator->transliterate($text, $options);
            } else {
                return $this->cleanBasicSlug($text, $options);
            }
        } catch (Exception $e) {
            $this->settings->logError('Transliteration error: ' . $e->getMessage());
            return $this->cleanBasicSlug($text, $options);
        }
    }

    private function convertTranslation($text, $options) {
        try {
            $service = isset($options['translation_service']) ? $options['translation_service'] : 'none';
            
            if ($service === 'none') {
                return $this->convertPinyin($text, $options);
            }
            
            $detected_lang = $this->detectLanguage($text);
            $target_lang = isset($options['translation_target_lang']) ? $options['translation_target_lang'] : 'en';
            
            if ($detected_lang === $target_lang) {
                return $this->cleanBasicSlug($text, $options);
            }
            
            return $this->translator->translate($text, $options);
        } catch (Exception $e) {
            $this->settings->logError('Translation error: ' . $e->getMessage());
            return $this->convertPinyin($text, $options);
        }
    }

    private function cleanBasicSlug($text, $options) {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/&.+?;/', '', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s\-_\p{L}\p{N}]/u', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/\-+/', '-', $text);
        $text = trim($text, '-_');
        
        if (isset($options['force_lowercase']) && $options['force_lowercase']) {
            $text = strtolower($text);
        }
        
        return $text;
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
        if (preg_match('/[\x{0370}-\x{03ff}]/u', $text)) {
            return 'el';
        }
        if (preg_match('/[\x{0100}-\x{017f}]/u', $text)) {
            return 'latin-ext';
        }
        if (preg_match('/[\x{0080}-\x{00ff}]/u', $text)) {
            return 'latin-ext';
        }
        if (preg_match('/[a-zA-Z]/', $text)) {
            return 'en';
        }
        
        return 'unknown';
    }

    public function getSupportedModes() {
        return array('pinyin', 'semantic_pinyin', 'transliteration', 'translation');
    }

    public function isModeSupported($mode) {
        return in_array($mode, $this->getSupportedModes());
    }

    public function batchConvert($items, $options = array()) {
        $results = array();
        
        if (!is_array($items)) {
            return $results;
        }
        
        foreach ($items as $item) {
            try {
                $converted = $this->convert($item, $options);
                $results[] = array(
                    'original' => $item,
                    'converted' => $converted,
                    'mode' => isset($options['conversion_mode']) ? $options['conversion_mode'] : 'pinyin',
                    'detected_language' => $this->detectLanguage($item)
                );
            } catch (Exception $e) {
                $this->settings->logError('Batch convert error: ' . $e->getMessage());
                $results[] = array(
                    'original' => $item,
                    'converted' => sanitize_title($item),
                    'mode' => 'fallback',
                    'detected_language' => 'unknown'
                );
            }
        }
        
        return $results;
    }

    public function testConversion($text, $options = array()) {
        $start_time = microtime(true);
        
        try {
            $detected_lang = $this->detectLanguage($text);
            $converted = $this->convert($text, $options);
            $execution_time = microtime(true) - $start_time;
            
            return array(
                'success' => true,
                'original' => $text,
                'converted' => $converted,
                'detected_language' => $detected_lang,
                'execution_time' => $execution_time,
                'mode' => isset($options['conversion_mode']) ? $options['conversion_mode'] : 'pinyin'
            );
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            return array(
                'success' => false,
                'original' => $text,
                'converted' => sanitize_title($text),
                'detected_language' => 'unknown',
                'execution_time' => $execution_time,
                'mode' => 'fallback',
                'error' => $e->getMessage()
            );
        }
    }

    public function getLanguageInfo($text) {
        $detected_lang = $this->detectLanguage($text);
        $char_count = mb_strlen($text, 'UTF-8');
        $word_count = str_word_count($text, 0, 'àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ');
        
        $info = array(
            'detected_language' => $detected_lang,
            'character_count' => $char_count,
            'word_count' => $word_count,
            'has_chinese' => preg_match('/[\x{4e00}-\x{9fff}]/u', $text) ? true : false,
            'has_cyrillic' => preg_match('/[\x{0400}-\x{04ff}]/u', $text) ? true : false,
            'has_arabic' => preg_match('/[\x{0600}-\x{06ff}]/u', $text) ? true : false,
            'has_greek' => preg_match('/[\x{0370}-\x{03ff}]/u', $text) ? true : false,
            'has_latin' => preg_match('/[a-zA-Z]/', $text) ? true : false,
            'has_numbers' => preg_match('/\d/', $text) ? true : false,
            'has_special_chars' => preg_match('/[^\w\s\p{L}\p{N}]/u', $text) ? true : false
        );
        
        return $info;
    }

    public function recommendMode($text) {
        $lang_info = $this->getLanguageInfo($text);
        $detected_lang = $lang_info['detected_language'];
        
        if ($detected_lang === 'zh') {
            return 'pinyin';
        } elseif (in_array($detected_lang, array('ru', 'ar', 'el', 'he'))) {
            return 'transliteration';
        } elseif ($detected_lang !== 'en' && $detected_lang !== 'unknown') {
            return 'translation';
        } else {
            return 'pinyin';
        }
    }

    public function validateInput($text, $options = array()) {
        $max_length = isset($options['max_length']) ? intval($options['max_length']) : 1000;
        
        if (empty($text)) {
            return array(
                'valid' => false,
                'error' => 'Input text is empty'
            );
        }
        
        if ($max_length > 0 && mb_strlen($text, 'UTF-8') > $max_length) {
            return array(
                'valid' => false,
                'error' => 'Input text exceeds maximum length of ' . $max_length . ' characters'
            );
        }
        
        return array(
            'valid' => true,
            'message' => 'Input is valid'
        );
    }
}