<?php
namespace ChiwiCrypt\utils;
class RequestHelper
{
  public function sendResponse(int $statusCode, mixed $data): void
  {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    return;
  }
  public function getPostData(): array
  {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data;
  }
}
