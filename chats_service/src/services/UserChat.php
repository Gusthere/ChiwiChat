<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;

class UserChat {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function addUserToChat($data) {
        $stmt = $this->db->prepare("INSERT INTO user_chats (user_id, chat_id) VALUES (:user_id, :chat_id)");
        $stmt->execute([
            'user_id' => $data['user_id'],
            'chat_id' => $data['chat_id']
        ]);
        return $this->db->lastInsertId();
    }

    public function getUsersByChatId($chatId) {
        $stmt = $this->db->prepare("SELECT users.* FROM users INNER JOIN user_chats ON users.id = user_chats.user_id WHERE user_chats.chat_id = :chat_id");
        $stmt->execute(['chat_id' => $chatId]);
        return $stmt->fetchAll();
    }
}