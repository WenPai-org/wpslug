<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Transliterator {
    private $char_maps;

    public function __construct() {
        $this->initCharMaps();
    }

    public function transliterate($text, $options = array()) {
        if (empty($text)) {
            return '';
        }

        $method = isset($options['transliteration_method']) ? $options['transliteration_method'] : 'basic';
        
        switch ($method) {
            case 'iconv':
                return $this->transliterateIconv($text, $options);
            case 'intl':
                return $this->transliterateIntl($text, $options);
            case 'basic':
            default:
                return $this->transliterateBasic($text, $options);
        }
    }

    private function transliterateBasic($text, $options) {
        $text = $this->applyCharMaps($text);
        $text = $this->cleanSlug($text);
        
        if (isset($options['force_lowercase']) && $options['force_lowercase']) {
            $text = strtolower($text);
        }
        
        return $text;
    }

    private function transliterateIconv($text, $options) {
        if (!function_exists('iconv')) {
            return $this->transliterateBasic($text, $options);
        }
        
        try {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = $this->cleanSlug($text);
            
            if (isset($options['force_lowercase']) && $options['force_lowercase']) {
                $text = strtolower($text);
            }
            
            return $text;
        } catch (Exception $e) {
            return $this->transliterateBasic($text, $options);
        }
    }

    private function transliterateIntl($text, $options) {
        if (!class_exists('Transliterator')) {
            return $this->transliterateIconv($text, $options);
        }
        
        try {
            $transliterator = Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $text = $transliterator->transliterate($text);
                $text = $this->cleanSlug($text);
                
                if (isset($options['force_lowercase']) && $options['force_lowercase']) {
                    $text = strtolower($text);
                }
                
                return $text;
            }
        } catch (Exception $e) {
            if (isset($options['debug_mode']) && $options['debug_mode']) {
                error_log('WPSlug transliterateIntl error: ' . $e->getMessage());
            }
        }
        
        return $this->transliterateIconv($text, $options);
    }

    private function applyCharMaps($text) {
        foreach ($this->char_maps as $from => $to) {
            $text = str_replace($from, $to, $text);
        }
        return $text;
    }

    private function cleanSlug($text) {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/&.+?;/', '', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/\-+/', '-', $text);
        $text = trim($text, '-_');
        
        return $text;
    }

    private function initCharMaps() {
        $this->char_maps = array(
            'А' => 'A', 'а' => 'a', 'Б' => 'B', 'б' => 'b', 'В' => 'V', 'в' => 'v',
            'Г' => 'G', 'г' => 'g', 'Д' => 'D', 'д' => 'd', 'Е' => 'E', 'е' => 'e',
            'Ё' => 'Yo', 'ё' => 'yo', 'Ж' => 'Zh', 'ж' => 'zh', 'З' => 'Z', 'з' => 'z',
            'И' => 'I', 'и' => 'i', 'Й' => 'J', 'й' => 'j', 'К' => 'K', 'к' => 'k',
            'Л' => 'L', 'л' => 'l', 'М' => 'M', 'м' => 'm', 'Н' => 'N', 'н' => 'n',
            'О' => 'O', 'о' => 'o', 'П' => 'P', 'п' => 'p', 'Р' => 'R', 'р' => 'r',
            'С' => 'S', 'с' => 's', 'Т' => 'T', 'т' => 't', 'У' => 'U', 'у' => 'u',
            'Ф' => 'F', 'ф' => 'f', 'Х' => 'H', 'х' => 'h', 'Ц' => 'C', 'ц' => 'c',
            'Ч' => 'Ch', 'ч' => 'ch', 'Ш' => 'Sh', 'ш' => 'sh', 'Щ' => 'Shh', 'щ' => 'shh',
            'Ъ' => '', 'ъ' => '', 'Ы' => 'Y', 'ы' => 'y', 'Ь' => '', 'ь' => '',
            'Э' => 'E', 'э' => 'e', 'Ю' => 'Yu', 'ю' => 'yu', 'Я' => 'Ya', 'я' => 'ya',
            
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y', 'Ÿ' => 'Y',
            'ñ' => 'n', 'Ñ' => 'N',
            'ç' => 'c', 'Ç' => 'C',
            'æ' => 'ae', 'Æ' => 'AE',
            'œ' => 'oe', 'Œ' => 'OE',
            
            'ā' => 'a', 'ē' => 'e', 'ī' => 'i', 'ō' => 'o', 'ū' => 'u',
            'Ā' => 'A', 'Ē' => 'E', 'Ī' => 'I', 'Ō' => 'O', 'Ū' => 'U',
            
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
            'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
            
            'ă' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
            'Ă' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
            
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e',
            'ζ' => 'z', 'η' => 'i', 'θ' => 'th', 'ι' => 'i', 'κ' => 'k',
            'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o',
            'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y',
            'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o',
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E',
            'Ζ' => 'Z', 'Η' => 'I', 'Θ' => 'TH', 'Ι' => 'I', 'Κ' => 'K',
            'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O',
            'Π' => 'P', 'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y',
            'Φ' => 'F', 'Χ' => 'CH', 'Ψ' => 'PS', 'Ω' => 'O',
            
            'ã' => 'a', 'õ' => 'o', 'ç' => 'c',
            'Ã' => 'A', 'Õ' => 'O', 'Ç' => 'C',
            
            'ğ' => 'g', 'ı' => 'i', 'ş' => 's', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c',
            'Ğ' => 'G', 'İ' => 'I', 'Ş' => 'S', 'Ü' => 'U', 'Ö' => 'O', 'Ç' => 'C',
            
            'ک' => 'k', 'گ' => 'g', 'چ' => 'ch', 'پ' => 'p', 'ژ' => 'zh',
            'ی' => 'y', 'ء' => 'a', 'ؤ' => 'w', 'ئ' => 'y', 'ة' => 'h',
            'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
            'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r',
            'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd',
            'ط' => 't', 'ظ' => 'dh', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
            'ق' => 'q', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
            'و' => 'w', 'ي' => 'y'
        );
    }

    public function getSupportedMethods() {
        return array('basic', 'iconv', 'intl');
    }

    public function isMethodSupported($method) {
        return in_array($method, $this->getSupportedMethods());
    }

    public function getAvailableMethods() {
        $methods = array('basic');
        
        if (function_exists('iconv')) {
            $methods[] = 'iconv';
        }
        
        if (class_exists('Transliterator')) {
            $methods[] = 'intl';
        }
        
        return $methods;
    }
}