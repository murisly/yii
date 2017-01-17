<?php

use yii\db\Migration;

class m170105_070109_add_column_in_user extends Migration
{
    private $table = '{{%user}}';
    
    public function up()
    {
        $this->addColumn($this->table,'access_token',$this->string()->notNull()->comment('access token')->after('password_reset_token'));
    }

    public function down()
    {
        $this->dropColumn($this->table, 'access_token');
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
