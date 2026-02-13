<?php
/**
 * WPSlug 自动更新器
 *
 * 通过文派云桥 (WenPai Bridge) 检查插件更新。
 * 利用 WordPress 5.8+ 的 Update URI 机制，注册
 * update_plugins_updates.wenpai.net filter。
 *
 * @package WPSlug
 */

if (!defined("ABSPATH")) {
    exit();
}

class WPSlug_Updater
{
    /** @var string 云桥 API 地址 */
    private $api_url = "https://updates.wenpai.net/api/v1";

    /** @var string 插件主文件 basename */
    private $plugin_file;

    /** @var string 插件 slug */
    private $slug;

    /** @var string 当前版本 */
    private $version;

    /**
     * 初始化更新器。
     *
     * @param string $plugin_file 插件主文件路径（plugin_basename 格式）
     * @param string $version     当前插件版本
     */
    public function __construct(string $plugin_file, string $version)
    {
        $this->plugin_file = $plugin_file;
        $this->slug = dirname($plugin_file);
        $this->version = $version;

        $this->register_hooks();
    }

    /**
     * 注册 WordPress hooks。
     */
    private function register_hooks(): void
    {
        // Update URI: https://updates.wenpai.net 触发此 filter
        add_filter(
            "update_plugins_updates.wenpai.net",
            [$this, "check_update"],
            10,
            4
        );

        // 插件详情弹窗
        add_filter("plugins_api", [$this, "plugin_info"], 20, 3);
    }

    /**
     * 检查插件更新。
     *
     * WordPress 在检查更新时，对声明了 Update URI 的插件
     * 触发 update_plugins_{hostname} filter。
     *
     * @param array|false $update     当前更新数据
     * @param array       $plugin_data 插件头信息
     * @param string      $plugin_file 插件文件路径
     * @param string[]    $locales     语言列表
     * @return array|false 更新数据或 false
     */
    public function check_update($update, array $plugin_data, string $plugin_file, array $locales)
    {
        if ($plugin_file !== $this->plugin_file) {
            return $update;
        }

        $response = $this->api_request("update-check", [
            "plugins" => [
                $this->plugin_file => [
                    "Version" => $this->version,
                ],
            ],
        ]);

        if (is_wp_error($response) || empty($response["plugins"][$this->plugin_file])) {
            return $update;
        }

        $data = $response["plugins"][$this->plugin_file];

        return [
            "slug"        => $data["slug"] ?? $this->slug,
            "version"     => $data["version"] ?? "",
            "url"         => $data["url"] ?? "",
            "package"     => $data["package"] ?? "",
            "icons"       => $data["icons"] ?? [],
            "banners"     => $data["banners"] ?? [],
            "requires"    => $data["requires"] ?? "",
            "tested"      => $data["tested"] ?? "",
            "requires_php" => $data["requires_php"] ?? "",
        ];
    }

    /**
     * 插件详情弹窗数据。
     *
     * 当用户在 WP 后台点击"查看详情"时触发。
     *
     * @param false|object|array $result 当前结果
     * @param string             $action API 动作
     * @param object             $args   请求参数
     * @return false|object 插件信息或 false
     */
    public function plugin_info($result, string $action, object $args)
    {
        if ($action !== "plugin_information") {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $response = $this->api_request("plugins/{$this->slug}/info");

        if (is_wp_error($response)) {
            return $result;
        }

        $info = new \stdClass();
        $info->name          = $response["name"] ?? "";
        $info->slug          = $response["slug"] ?? $this->slug;
        $info->version       = $response["version"] ?? "";
        $info->author        = $response["author"] ?? "";
        $info->homepage      = $response["homepage"] ?? "";
        $info->download_link = $response["download_link"] ?? "";
        $info->requires      = $response["requires"] ?? "";
        $info->tested        = $response["tested"] ?? "";
        $info->requires_php  = $response["requires_php"] ?? "";
        $info->last_updated  = $response["last_updated"] ?? "";
        $info->icons         = $response["icons"] ?? [];
        $info->banners       = $response["banners"] ?? [];
        $info->sections      = $response["sections"] ?? [];

        return $info;
    }

    /**
     * 向云桥 API 发送请求。
     *
     * @param string     $endpoint API 端点（不含 /api/v1/ 前缀）
     * @param array|null $body     POST 请求体（null 则用 GET）
     * @return array|\WP_Error 解码后的响应或错误
     */
    private function api_request(string $endpoint, ?array $body = null)
    {
        $url = rtrim($this->api_url, "/") . "/" . ltrim($endpoint, "/");

        $args = [
            "timeout" => 15,
            "headers" => [
                "Accept" => "application/json",
            ],
        ];

        if ($body !== null) {
            $args["headers"]["Content-Type"] = "application/json";
            $args["body"] = wp_json_encode($body);
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new \WP_Error(
                "wenpai_bridge_error",
                sprintf("WenPai Bridge API returned %d", $code)
            );
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return new \WP_Error(
                "wenpai_bridge_parse_error",
                "Invalid JSON response from WenPai Bridge"
            );
        }

        return $data;
    }
}
