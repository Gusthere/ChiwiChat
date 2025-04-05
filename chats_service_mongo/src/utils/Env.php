<?php
namespace Chiwichat\Chats\Utils;

class Env {
    public static function env($key) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        
        return $value;
    }
}