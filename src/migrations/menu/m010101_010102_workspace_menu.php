<?php

use portalium\menu\models\Menu;
use portalium\menu\models\MenuItem;
use yii\db\Migration;

class m010101_010102_workspace_menu extends Migration
{

    public function up()
    {
        $id_menu = Menu::find()->where(['slug' => 'web-main-menu'])->one()->id_menu;
        $id_item = MenuItem::find()->where(['slug' => 'site'])->one();

        if(!$id_item){
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
        }else {
            $id_item = MenuItem::find()->where(['slug' => 'site'])->one()->id_item;
        }

        $id_item = MenuItem::find()->where(['slug' => 'site'])->one()->id_item;
        $this->batchInsert('menu_item', ['id_item', 'label', 'slug', 'type', 'style', 'data', 'sort', 'id_menu', 'name_auth', 'id_user', 'date_create', 'date_update'], [
            [NULL, 'Workspace', 'workspace-select', '2', '{"icon":"fa-building","color":"","iconSize":"","display":"1","childDisplay":"3"}', '{"data":{"module":"workspace","routeType":"widget","route":"portalium\\\\workspace\\\\widgets\\\\Workspace","model":"","menuRoute":null,"menuType":"web"}}', 10, $id_menu, 'user', 1,'2022-06-14 13:34:04', '2022-06-14 13:34:04'],
            [NULL, 'Workspaces', 'workspace-workspaces', '2', '{"icon":"","color":"","iconSize":"","display":"","childDisplay":false}', '{"data":{"module":"workspace","routeType":"action","route":"\\/workspace\\/default\\/index","model":null,"menuRoute":null,"menuType":"web"}}', '13', $id_menu, 'workspaceWebDefaultIndex', 1, '2022-06-13 15:32:26', '2022-06-13 15:32:26']
        ]);


        $this->insert('menu_item_child', [
            'id_item' => $id_item,
            'id_child' => MenuItem::find()->where(['slug' => 'workspace-workspaces'])->one()->id_item,
        ]);

        $id_menu_side = Menu::find()->where(['slug' => 'web-side-menu'])->one()->id_menu;

        if ($id_menu_side) {
            $this->batchInsert('menu_item', ['id_item', 'label', 'slug', 'type', 'style', 'data', 'sort', 'id_menu', 'name_auth', 'id_user', 'date_create', 'date_update'], [
                [NULL, 'Workspaces', 'workspaces', '2', '{"icon":"fa-building","color":"","iconSize":"","display":"3","childDisplay":"","placement":"1"}', '{"data":{"module":"workspace","routeType":"action","route":"\\/workspace\\/default\\/index","model":"","menuRoute":null,"menuType":null}}', 2, $id_menu_side, 'user', 1, '2023-07-17 20:15:56', '2024-02-08 11:14:43'],
            ]);
        }

    }

    public function down()
    {

    }
}
