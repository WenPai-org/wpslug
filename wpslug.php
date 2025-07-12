<?php

/*
Plugin Name: WPSlug - Smart Permalink Manager
Plugin URI: https://wpslug.com
Description: Advanced slug management plugin with Chinese Pinyin support and SEO optimization.
Version: 1.0.0
Author: WPSlug.com
Author URI: https://wpslug.com
License: GPL2
Text Domain: wpslug
Domain Path: /languages
*/

if (!defined("ABSPATH")) {
    exit();
}

define("WPSLUG_VERSION", "1.0.0");
define("WPSLUG_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("WPSLUG_PLUGIN_URL", plugin_dir_url(__FILE__));
define("WPSLUG_PLUGIN_BASENAME", plugin_basename(__FILE__));

class WPSlug
{
    private static $instance = null;
    private $core = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action("plugins_loaded", [$this, "loadPlugin"]);
        register_activation_hook(__FILE__, [$this, "activate"]);
        register_deactivation_hook(__FILE__, [$this, "deactivate"]);
        register_uninstall_hook(__FILE__, ["WPSlug", "uninstall"]);
        
        add_action('init', [$this, 'initLanguages']);
    }

    public function loadPlugin()
    {
        if (!$this->checkRequirements()) {
            return;
        }

        $this->loadDependencies();
        $this->loadTextdomain();
        $this->core = new WPSlug_Core();
    }

    private function checkRequirements()
    {
        if (version_compare(PHP_VERSION, "7.0", "<")) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('WP Slug requires PHP 7.0 or higher. Please upgrade your PHP version.', 'wpslug');
                echo '</p></div>';
            });
            return false;
        }

        if (version_compare(get_bloginfo("version"), "5.0", "<")) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('WP Slug requires WordPress 5.0 or higher. Please upgrade your WordPress version.', 'wpslug');
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }

    private function loadDependencies()
    {
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-validator.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-settings.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-pinyin.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-optimizer.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-transliterator.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-translator.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-converter.php";
        require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-core.php";

        if (is_admin()) {
            require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-admin.php";
        }
    }

    public function initLanguages()
    {
        $locale = apply_filters('plugin_locale', get_locale(), 'wpslug');
        $mo_file = WPSLUG_PLUGIN_DIR . "languages/wpslug-{$locale}.mo";
        
        if (file_exists($mo_file)) {
            load_textdomain('wpslug', $mo_file);
        }
    }

    public function loadTextdomain()
    {
        load_plugin_textdomain(
            "wpslug",
            false,
            dirname(plugin_basename(__FILE__)) . "/languages/"
        );
    }

    public function activate()
    {
        if (!function_exists("is_plugin_active")) {
            require_once ABSPATH . "wp-admin/includes/plugin.php";
        }

        if (!$this->checkRequirements()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('WP Slug plugin requirements not met. Please check your PHP and WordPress versions.', 'wpslug'),
                esc_html__('Plugin Activation Error', 'wpslug'),
                array('back_link' => true)
            );
        }

        $this->loadDependencies();
        
        try {
            $settings = new WPSlug_Settings();
            $settings->createDefaultOptions();
            
            $core = new WPSlug_Core();
            $core->activate();
            
            add_option('wpslug_activation_redirect', true);
            
        } catch (Exception $e) {
            error_log('WP Slug activation error: ' . $e->getMessage());
            wp_die(
                esc_html__('An error occurred during plugin activation. Please check your server logs.', 'wpslug'),
                esc_html__('Plugin Activation Error', 'wpslug'),
                array('back_link' => true)
            );
        }
    }

    public function deactivate()
    {
        try {
            if ($this->core) {
                $this->core->deactivate();
            }
            
            delete_option('wpslug_activation_redirect');
            
        } catch (Exception $e) {
            error_log('WP Slug deactivation error: ' . $e->getMessage());
        }
    }

    public static function uninstall()
    {
        try {
            if (class_exists("WPSlug_Settings")) {
                $settings = new WPSlug_Settings();
                $settings->uninstall();
            }
            
            delete_option('wpslug_activation_redirect');
            
        } catch (Exception $e) {
            error_log('WP Slug uninstall error: ' . $e->getMessage());
        }
    }

    public function getCore()
    {
        return $this->core;
    }

    public function getSettings()
    {
        if ($this->core) {
            return $this->core->getSettings();
        }
        return null;
    }

    public function getConverter()
    {
        if ($this->core) {
            return $this->core->getConverter();
        }
        return null;
    }

    public function getOptimizer()
    {
        if ($this->core) {
            return $this->core->getOptimizer();
        }
        return null;
    }
}

function wpslug()
{
    return WPSlug::getInstance();
}

if (is_admin()) {
    add_action('admin_init', function() {
        if (get_option('wpslug_activation_redirect', false)) {
            delete_option('wpslug_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('options-general.php?page=wpslug'));
                exit;
            }
        }
    });
}

wpslug();