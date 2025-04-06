<?php

namespace Chiwichat\Chats\Utils;

class HttpHelper {
    public static function getJsonData() {
        return json_decode(file_get_contents('php://input'), true);
    }

    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}