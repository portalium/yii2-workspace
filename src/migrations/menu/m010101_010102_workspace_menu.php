<?php

use portalium\menu\models\Menu;
use yii\db\Migration;

class m010101_010102_workspace_menu extends Migration
{

    public function up()
    {
        $id_menu = Menu::find()->where(['slug' => 'web-main-menu'])->one()->id_menu;
        $id_item = MenuItem::find()->where(['slug' => 'site'])->one()->id_item;

        if($id_item){
            $this->insert('menu_item', [
                'id_item' => NULL,
                'label' => 'Site',
                'slug' => 'site',
                'type' => '3',
                'style' => '{"icon":"fa-cog","color":"","iconSize":"","display":"1","childDisplay":"3"}',
                'data' => '{"data":{"url":"#"}}',
                'sort' => '1',
                'id_menu' => $id_menu,
                'name_auth' => 'admin',
                'id_user' => '1',
                'date_create' => '2022-06-13 15:32:26',
                'date_update' => '2022-06-13 15:32:26',
            ]);
        }

        $id_item = MenuItem::find()->where(['slug' => 'site'])->one()->id_item;
        $this->batchInsert('menu_item', ['id_item', 'label', 'slug', 'type', 'style', 'data', 'sort', 'id_menu', 'name_auth', 'id_user', 'date_create', 'date_update'], [
            [NULL, 'Workspace', 'workspace-select', '2', '{"icon":"fa-building","color":"","iconSize":"","display":"1","childDisplay":"3"}', '{"data":{"module":"organization","routeType":"widget","route":"portalium\\\\organization\\\\widgets\\\\Organization","model":"","menuRoute":null,"menuType":"web"}}', 10, $id_menu, 'user', 1,'2022-06-14 13:34:04', '2022-06-14 13:34:04'],
        ]);

        $ids = $this->db->createCommand('SELECT id_item FROM menu_item WHERE slug in ("setting","language","login")')->queryColumn();


        foreach ($ids as $id) {
            $this->insert('menu_item_child', [
                'id_item' => $id_item,
                'id_child' => $id
            ]);
        }

    }

    public function down()
    {

    }
}
