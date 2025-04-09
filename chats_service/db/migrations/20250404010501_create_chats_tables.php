<?php
use Phinx\Migration\AbstractMigration;

class CreateChatsTables extends AbstractMigration
{
    public function change()
    {
        // Tabla de conversaciones
        $conversations = $this->table('conversations', [
            'id' => 'conversationId',
        ]);
        $conversations
            ->addColumn('createdAt', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('user1Id', 'integer', [
                'signed' => false,
                'comment' => 'ID del usuario en el servicio de usuarios'
            ])
            ->addColumn('user2Id', 'integer', [
                'signed' => false,
                'comment' => 'ID del usuario en el servicio de usuarios'
            ])
            ->create();

        // Tabla de mensajes
        $messages = $this->table('messages', [
            'id' => 'messageId'
        ]);
        $messages
            ->addColumn('conversationId', 'integer', [
                'signed' => false
            ])
            ->addColumn('sender_id', 'integer', [
                'signed' => false,
                'comment' => 'ID del usuario que envió el mensaje'
            ])
            ->addColumn('encryptedContent', 'text', [
                'null' => false,
                'comment' => 'Contenido cifrado del mensaje'
            ])
            ->addColumn('sentAt', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('status', 'enum', [
                'values' => ['sent', 'delivered', 'read'],
                'default' => 'sent'
            ])
            ->addForeignKey('conversationId', 'conversations', 'conversationId', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addIndex(['sender_id'])
            ->addIndex(['conversationId', 'sentAt'])
            ->create();
    }
}