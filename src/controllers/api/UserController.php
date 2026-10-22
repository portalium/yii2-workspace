<?php

namespace portalium\workspace\controllers\api;

use portalium\rest\ActiveController as RestActiveController;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\Module;
use Yii;
use yii\db\Query;
use yii\web\ForbiddenHttpException;

/**
 * Kullanıcının kendi çalışma alanı üyeliklerini okur; çalışma alanı başına tek satır döner.
 * Üyelik ekleme, değiştirme ve silme yetki kontrolü olan workspace/assignment uçlarıyla,
 * aktif çalışma alanı seçimi workspace/default/set-workspace ile yapılır; bu uçta kapalıdır.
 */
class UserController extends RestActiveController
{
    public $modelClass = 'portalium\workspace\models\WorkspaceUser';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create'], $actions['update'], $actions['delete']);
        $actions['index'] = [
            'class' => 'yii\rest\IndexAction',
            'modelClass' => $this->modelClass,
            'dataFilter' => [
                'class' => \yii\data\ActiveDataFilter::class,
                'searchModel' => $this->modelClass,
            ],
        ];
        $actions['index']['prepareSearchQuery'] = function ($query) {
            $table = WorkspaceUser::tableName();
            $maxStatus = (new Query())
                ->select('MAX(wu2.status)')
                ->from($table . ' wu2')
                ->where('wu2.id_user = ' . $table . '.id_user AND wu2.id_workspace = ' . $table . '.id_workspace');
            return $query
                ->andWhere([$table . '.id_user' => Yii::$app->user->id])
                ->andWhere([$table . '.status' => $maxStatus])
                ->groupBy($table . '.id_workspace');
        };
        return $actions;
    }

    public function checkAccess($action, $model = null, $params = [])
    {
        if ($model !== null && (int) $model->id_user !== (int) Yii::$app->user->id) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
    }
}
