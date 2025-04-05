<?php
use Phinx\Migration\AbstractMigration;

class CreateChatsTables extends AbstractMigration
{
    public function change()
    {
        // Tabla de conversaciones
        $conversations = $this->table('conversations', [
            'id' => 'conversation_id',
        ]);
        $conversations
            ->addColumn('created_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('user1_id', 'integer', [
                'signed' => false,
                'comment' => 'ID del usuario en el servicio de usuarios'
            ])
            ->addColumn('user2_id', 'integer', [
                'signed' => false,
                'comment' => 'ID del usuario en el servicio de usuarios'
            ])
            ->create();

        // Tabla de mensajes
        $messages = $this->table('messages', [
            'id' => 'message_id'
        ]);
        $messages
            ->addColumn('conversation_id', 'integer', [
                'signed' => false
            ])
            ->addColumn('sender_id', 'integer', [
                'signed' => false,
                'comment' => 'ID del usuario que envió el mensaje'
            ])
            ->addColumn('encrypted_content', 'text', [
                'null' => false,
                'comment' => 'Contenido cifrado del mensaje'
            ])
            ->addColumn('sent_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('status', 'enum', [
                'values' => ['sent', 'delivered', 'read'],
                'default' => 'sent'
            ])
            ->addForeignKey('conversation_id', 'conversations', 'conversation_id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addIndex(['sender_id'])
            ->addIndex(['conversation_id', 'sent_at'])
            ->create();
    }
}