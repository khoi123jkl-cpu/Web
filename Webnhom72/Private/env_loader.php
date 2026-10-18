<?php

if (!function_exists('appEnv')) {
    function appEnv($key, $default = null) {
        static $fileConfig = null;
        if ($fileConfig === null) {
            $configPath = __DIR__ . '/config.env.php';
            if (file_exists($configPath)) {
                $loaded = require $configPath;
                $fileConfig = is_array($loaded) ? $loaded : [];
            } else {
                $fileConfig = [];
            }
        }
        if (array_key_exists($key, $fileConfig)) {
            return $fileConfig[$key];
        }

        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }


        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return $default;
    }
}
