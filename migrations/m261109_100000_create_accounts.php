<?php

use yii\db\Migration;

class m261109_100000_create_accounts extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(100)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(100)->notNull(),
            'must_change_password' => $this->boolean()->notNull()->defaultValue(false),
        ]);

        $this->createIndex(
            'ux_user_username',
            '{{%user}}',
            'username',
            true
        );

        $this->createTable('{{%login_rate_limit}}', [
            'scope' => $this->string(16)->notNull(),
            'identifier' => $this->string(100)->notNull(),
            'tokens' => $this->decimal(10, 6)->notNull(),
            'updated_at' => $this->bigInteger()->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_login_rate_limit',
            '{{%login_rate_limit}}',
            ['scope', 'identifier']
        );

        $auth = Yii::$app->authManager;

        $resetPassword = $auth->createPermission('resetUserPassword');
        $auth->add($resetPassword);
        $accessAdmin = $auth->createPermission('accessAdmin');
        $auth->add($accessAdmin);
        $viewUsers = $auth->createPermission('viewUsers');
        $auth->add($viewUsers);

        $userRole = $auth->createRole('user');
        $auth->add($userRole);

        $adminRole = $auth->createRole('admin');
        $auth->add($adminRole);
        $auth->addChild($adminRole, $userRole);
        $auth->addChild($adminRole, $resetPassword);
        $auth->addChild($adminRole, $accessAdmin);
        $auth->addChild($adminRole, $viewUsers);

        $catalogPermission = $auth->createPermission('manageCatalog');
        $catalogPermission->description = 'Управление архивом';

        $auth->add($catalogPermission);
        $auth->addChild($adminRole, $catalogPermission);
    }

    public function safeDown()
    {
        $this->dropTable('{{%login_rate_limit}}');
        $this->dropTable('{{%user}}');
        $auth = Yii::$app->authManager;
        foreach (['admin', 'user'] as $name) {
            $auth->remove($auth->getRole($name));
        }
        foreach (['manageCatalog', 'viewUsers', 'accessAdmin', 'resetUserPassword'] as $name) {
            $auth->remove($auth->getPermission($name));
        }
    }
}
