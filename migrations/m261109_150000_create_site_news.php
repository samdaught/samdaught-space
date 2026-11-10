<?php

use yii\db\Migration;

class m261109_150000_create_site_news extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%site_news}}', [
            'id' => $this->primaryKey(),
            'author_id' => $this->integer()->notNull(),
            'title' => $this->string(255)->notNull(),
            'summary' => $this->string(500)->notNull()->defaultValue(''),
            'body' => $this->text()->notNull()->defaultValue(''),
            'is_published' => $this->boolean()->notNull()->defaultValue(false),
            'published_at' => $this->timestamp(),
            'created_at' => $this->timestamp()->notNull(),
            'updated_at' => $this->timestamp()->notNull(),
            'revision' => $this->integer()->notNull(),
        ]);
        $this->createIndex('ix_site_news_author', '{{%site_news}}', 'author_id');
        $this->addForeignKey(
            'fk_site_news_author',
            '{{%site_news}}',
            'author_id',
            '{{%user}}',
            'id',
            'RESTRICT',
            'RESTRICT'
        );
        $this->createIndex('ix_site_news_publication', '{{%site_news}}', ['is_published', 'published_at', 'id']);
        $this->addCheck('ck_site_news_revision', '{{%site_news}}', '[[revision]] >= 1');
        $this->addCheck(
            'ck_site_news_publication',
            '{{%site_news}}',
            'NOT [[is_published]] OR ([[published_at]] IS NOT NULL AND length(btrim([[body]])) > 0)'
        );

        $auth = Yii::$app->authManager;
        $admin = $auth->getRole('admin');
        if ($admin === null) {
            throw new \RuntimeException('Не найдена роль администратора.');
        }
        $permission = $auth->createPermission('manageNews');
        $permission->description = 'Управление новостями сайта';
        $auth->add($permission);
        $auth->addChild($admin, $permission);
    }

    public function safeDown()
    {
        $this->dropTable('{{%site_news}}');
        $auth = Yii::$app->authManager;
        $permission = $auth->getPermission('manageNews');
        if ($permission !== null) {
            $auth->remove($permission);
        }
    }
}
