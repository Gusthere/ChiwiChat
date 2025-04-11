<?php
use Phinx\Migration\AbstractMigration;

class CreateUsersTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users', [
            'id' => 'id',
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => 'Tabla de usuarios del sistema'
        ]);
        
        $table->addColumn('username', 'string', [
            'limit' => 20,
            'null' => false
        ])
        ->addColumn('nombre', 'string', [
            'limit' => 20,
            'null' => false
        ])
        ->addColumn('apellido', 'string', [
            'limit' => 20,
            'null' => false
        ])
        ->addColumn('email', 'string', [
            'limit' => 100,
            'null' => false
        ])
        ->addColumn('refreshToken', 'text', [
            'null' => true,
            'comment' => 'Access Token for user authentication'
        ])
        ->addColumn('createTime', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
            'comment' => 'Create Time'
        ])
        ->addIndex(['username'], [
            'name' => 'idx_username',
            'unique' => true
        ])
        ->addIndex(['email'], [
            'name' => 'idx_email',
            'unique' => true
        ])
        ->addIndex(['username', 'email'], [
            'name' => 'idx_users_search'
        ])
        ->create();
    }
}