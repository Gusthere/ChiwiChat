<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;

class Chat {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createChat($data) {
        $stmt = $this->db->prepare("INSERT INTO chats (name) VALUES (:name)");
        $stmt->execute(['name' => $data['name']]);
        return $this->db->lastInsertId();
    }

    public function getChatById($id) {
        $stmt = $this->db->prepare("SELECT * FROM chats WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getChats() {
        $stmt = $this->db->prepare("SELECT * FROM chats");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}