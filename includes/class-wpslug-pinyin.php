<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSlug_Pinyin {
    private $pinyin_dict = array();
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->initPinyinDict();
    }

    public function convertToPinyin($text, $options = array()) {
        if (empty($text)) {
            return '';
        }

        $separator = isset($options['pinyin_separator']) ? $options['pinyin_separator'] : '-';
        $format = isset($options['pinyin_format']) ? $options['pinyin_format'] : 'full';
        $max_length = isset($options['max_length']) ? (int)$options['max_length'] : 100;
        $preserve_english = isset($options['preserve_english']) ? $options['preserve_english'] : true;
        $preserve_numbers = isset($options['preserve_numbers']) ? $options['preserve_numbers'] : true;

        if ($max_length > 0 && mb_strlen($text, 'UTF-8') > $max_length) {
            $text = mb_substr($text, 0, $max_length, 'UTF-8');
        }

        $result = '';
        $text_array = $this->mbStrSplit($text);

        foreach ($text_array as $char) {
            if ($this->isChinese($char)) {
                $pinyin = $this->getPinyinForChar($char);
                if (!empty($pinyin)) {
                    if ($format === 'first') {
                        $first_letter = mb_substr($pinyin, 0, 1, 'UTF-8');
                        $result .= $separator . strtolower($first_letter) . $separator;
                    } else {
                        $result .= $separator . strtolower($pinyin) . $separator;
                    }
                }
            } else {
                if ($preserve_english && preg_match('/[a-zA-Z]/', $char)) {
                    $result .= $char;
                } elseif ($preserve_numbers && preg_match('/[0-9]/', $char)) {
                    $result .= $char;
                } elseif (preg_match('/\s/', $char)) {
                    $result .= $separator;
                }
            }
        }

        if (!empty($separator)) {
            $result = trim(preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $result), $separator);
        }

        return $result;
    }

    public function isChinese($char) {
        return preg_match('/[\x{4e00}-\x{9fff}]/u', $char);
    }

    public function detectLanguage($text) {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            return 'zh';
        }
        return 'en';
    }

    private function getPinyinForChar($char) {
        if (isset($this->pinyin_dict[$char])) {
            return $this->pinyin_dict[$char];
        }
        return '';
    }

    private function mbStrSplit($string, $length = 1) {
        if (function_exists('mb_str_split')) {
            return mb_str_split($string, $length, 'UTF-8');
        }

        $result = array();
        $string_length = mb_strlen($string, 'UTF-8');
        
        for ($i = 0; $i < $string_length; $i += $length) {
            $result[] = mb_substr($string, $i, $length, 'UTF-8');
        }
        
        return $result;
    }

    private function initPinyinDict() {
        $dictionary_file = WPSLUG_PLUGIN_DIR . 'includes/dictionary.php';
        
        if (file_exists($dictionary_file)) {
            $dictionary = include $dictionary_file;
            
            if (is_array($dictionary)) {
                foreach ($dictionary as $pinyin => $chars) {
                    $pinyin_lower = strtolower($pinyin);
                    $char_array = $this->mbStrSplit($chars);
                    
                    foreach ($char_array as $char) {
                        if (!empty($char)) {
                            $this->pinyin_dict[$char] = $pinyin_lower;
                        }
                    }
                }
            }
        } else {
            $this->initFallbackDict();
        }
    }
    
    private function initFallbackDict() {
        $this->pinyin_dict = array(
            '一' => 'yi', '二' => 'er', '三' => 'san', '四' => 'si', '五' => 'wu', 
            '六' => 'liu', '七' => 'qi', '八' => 'ba', '九' => 'jiu', '十' => 'shi',
            '的' => 'de', '了' => 'le', '是' => 'shi', '我' => 'wo', '你' => 'ni', 
            '他' => 'ta', '她' => 'ta', '它' => 'ta', '好' => 'hao', '很' => 'hen',
            '都' => 'dou', '会' => 'hui', '个' => 'ge', '这' => 'zhe', '那' => 'na',
            '中' => 'zhong', '国' => 'guo', '人' => 'ren', '有' => 'you', '来' => 'lai',
            '可' => 'ke', '以' => 'yi', '上' => 'shang', '下' => 'xia', '大' => 'da',
            '小' => 'xiao', '多' => 'duo', '少' => 'shao', '什' => 'shen', '么' => 'me',
            '时' => 'shi', '间' => 'jian', '地' => 'di', '方' => 'fang', '年' => 'nian',
            '月' => 'yue', '日' => 'ri', '天' => 'tian', '水' => 'shui', '火' => 'huo',
            '木' => 'mu', '金' => 'jin', '土' => 'tu', '山' => 'shan', '海' => 'hai',
            '河' => 'he', '学' => 'xue', '校' => 'xiao', '老' => 'lao', '师' => 'shi',
            '生' => 'sheng', '活' => 'huo', '工' => 'gong', '作' => 'zuo', '家' => 'jia',
            '庭' => 'ting', '朋' => 'peng', '友' => 'you', '爱' => 'ai', '情' => 'qing',
            '心' => 'xin', '想' => 'xiang', '知' => 'zhi', '道' => 'dao', '看' => 'kan',
            '见' => 'jian', '听' => 'ting', '说' => 'shuo', '话' => 'hua', '言' => 'yan',
            '文' => 'wen', '字' => 'zi', '书' => 'shu', '读' => 'du', '写' => 'xie',
            '画' => 'hua', '吃' => 'chi', '喝' => 'he', '睡' => 'shui', '觉' => 'jue',
            '走' => 'zou', '跑' => 'pao', '飞' => 'fei', '坐' => 'zuo', '站' => 'zhan',
            '躺' => 'tang', '笑' => 'xiao', '哭' => 'ku', '高' => 'gao', '兴' => 'xing',
            '快' => 'kuai', '乐' => 'le', '难' => 'nan', '过' => 'guo', '新' => 'xin',
            '旧' => 'jiu', '长' => 'chang', '短' => 'duan', '宽' => 'kuan', '窄' => 'zhai',
            '厚' => 'hou', '薄' => 'bao', '深' => 'shen', '浅' => 'qian', '远' => 'yuan',
            '近' => 'jin', '美' => 'mei', '丽' => 'li', '漂' => 'piao', '亮' => 'liang',
            '帅' => 'shuai', '聪' => 'cong', '明' => 'ming', '笨' => 'ben', '懒' => 'lan',
            '勤' => 'qin', '忙' => 'mang', '闲' => 'xian', '累' => 'lei', '轻' => 'qing',
            '重' => 'zhong', '松' => 'song', '紧' => 'jin', '开' => 'kai', '关' => 'guan',
            '门' => 'men', '窗' => 'chuang', '户' => 'hu', '房' => 'fang', '子' => 'zi',
            '屋' => 'wu', '楼' => 'lou', '层' => 'ceng', '街' => 'jie', '路' => 'lu',
            '桥' => 'qiao', '车' => 'che', '船' => 'chuan', '机' => 'ji', '电' => 'dian',
            '话' => 'hua', '视' => 'shi', '脑' => 'nao', '手' => 'shou', '网' => 'wang',
            '络' => 'luo', '游' => 'you', '戏' => 'xi', '音' => 'yin', '影' => 'ying',
            '唱' => 'chang', '歌' => 'ge', '跳' => 'tiao', '舞' => 'wu', '购' => 'gou',
            '物' => 'wu', '买' => 'mai', '卖' => 'mai', '钱' => 'qian', '价' => 'jia',
            '格' => 'ge', '便' => 'bian', '宜' => 'yi', '贵' => 'gui', '医' => 'yi',
            '院' => 'yuan', '病' => 'bing', '痛' => 'tong', '健' => 'jian', '康' => 'kang',
            '运' => 'yun', '动' => 'dong', '锻' => 'duan', '炼' => 'lian'
        );
    }
}