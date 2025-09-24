<?php

class m000000_000000_initial extends \humhub\components\Migration
{
    public function up()
    {
        $this->insert('setting', [
            'name' => 'apiBaseUrl',
            'value' => 'https://api.greenmeteor.com/v1',
            'module_id' => 'bazaar'
        ]);

        $this->insert('setting', [
            'name' => 'cacheTimeout', 
            'value' => '3600',
            'module_id' => 'bazaar'
        ]);

        $this->insert('setting', [
            'name' => 'enablePurchasing',
            'value' => '1', 
            'module_id' => 'bazaar'
        ]);
    }

    public function down()
    {
        $this->delete('setting', ['module_id' => 'bazaar']);
    }
}