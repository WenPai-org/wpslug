<?php

define("WPSLUG_VERSION", "1.2.2");
define("WPSLUG_PLUGIN_FILE", __DIR__ . "/../wpslug.php");
define("WPSLUG_PLUGIN_DIR", __DIR__ . "/../");
define("WPSLUG_PLUGIN_URL", "https://example.test/wp-content/plugins/wpslug/");
define("WPSLUG_PLUGIN_BASENAME", "wpslug/wpslug.php");


function wpmind_is_available(): bool
{
    return false;
}

function wpmind_translate(string $text, string $from = auto, string $to = en, array $options = []): string
{
    return $text;
}

function wpmind_pinyin(string $text, array $options = []): string
{
    return $text;
}
