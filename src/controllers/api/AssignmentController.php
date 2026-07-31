<?php

namespace portalium\workspace\controllers\api;

use Yii;
use yii\helpers\ArrayHelper;
use yii\data\ArrayDataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\UnprocessableEntityHttpException;
use portalium\rest\ActiveController as RestActiveController;
use portalium\workspace\models\AssignmentForm;
use portalium\workspace\Module;
use portalium\user\models\User;
use portalium\user\Module as UserModule;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;


class AssignmentController extends RestActiveController
{
    public $modelClass = WorkspaceUser::class;

    public function actions()
    {
        $actions = parent::actions();

        unset(
            $actions['index'],
            $actions['view'],
            $actions['create'],
            $actions['update'],
            $actions['delete']
        );

        return $actions;
    }

    /**
     * GET /workspace/assignment/assignment/:id
     *
     * Returns assignment data for a workspace: available users, currently
     * assigned users, supported modules and available roles.
     *
     * @param int $id Id Workspace
     * @return array
     */
    public function actionAssignment($id)
    {
        $workspace = $this->findModel($id);

        if (!(Yii::$app->user->can('workspaceApiAssignmentView', ['id_module' => 'workspace', 'model' => $workspace]) ||
            (Yii::$app->user->can('workspaceApiAssignmentViewOwn', ['id_module' => 'workspace', 'model' => $workspace]) && $workspace->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if (!Yii::$app->workspace->checkSupportRoles()) {
            throw new BadRequestHttpException(Module::t('Please set default role for workspace module.'));
        }

        $users = User::find()->select(['id_user', 'username'])->asArray()->all();
        $assignedUsers = WorkspaceUser::find()
            ->select([UserModule::$tablePrefix . 'user.id_user', Module::$tablePrefix . 'workspace_user.id_workspace_user', 'username', Module::$tablePrefix . 'workspace_user.role', Module::$tablePrefix . 'workspace_user.id_module'])
            ->leftJoin(UserModule::$tablePrefix . 'user', UserModule::$tablePrefix . 'user.id_user = ' . Module::$tablePrefix . 'workspace_user.id_user')
            ->groupBy(Module::$tablePrefix . 'workspace_user.id_workspace_user')
            ->andWhere([Module::$tablePrefix . 'workspace_user.id_workspace' => $id])
            ->asArray()
            ->all();

        $assignedUserIds = array_column($assignedUsers, 'id_user');
        $availableUsers = array_filter($users, function ($user) use ($assignedUserIds) {
            return !in_array($user['id_user'], $assignedUserIds);
        });

        $assignedUsers = array_map(function ($user) {
            $user['username'] = $user['username'] . ' (' . $user['role'] . (isset($user['id_module']) ? ' / ' . $user['id_module'] : '') . ')';
            return $user;
        }, $assignedUsers);

        $modules = Yii::$app->workspace->getSupportModules();
        $moduleArray = [];
        foreach ($modules as $key => $value) {
            $moduleArray[$key] = isset(Yii::$app->getModule($key)::$name) ? Yii::$app->getModule($key)::$name : $key;
        }
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');

        return [
            'model' => $workspace,
            'users' => ArrayHelper::map($availableUsers, 'id_user', 'username'),
            'assignedUsers' => ArrayHelper::map($assignedUsers, 'id_workspace_user', 'username'),
            'moduleArray' => $moduleArray,
            'availableRoles' => $availableRoles,
        ];
    }

    /**
     * POST /workspace/assignment/assign
     *
     * Assigns a role to one or more users for a workspace.
     *
     * Body: id_workspace, id_module, role, id_users[], type = create / update
     *
     * @return bool
     */
    public function actionAssign()
    {
        $id_workspace = Yii::$app->request->post('id_workspace');

        if(!$id_workspace)
        {
            throw new BadRequestHttpException(Module::t('Cant get id_workspace'));
        }

        $workspace = $this->findModel($id_workspace);

        if (!(Yii::$app->user->can('workspaceApiAssignmentAssign', ['id_module' => 'workspace', 'model' => $workspace]) ||
            (Yii::$app->user->can('workspaceApiAssignmentAssignOwn', ['id_module' => 'workspace', 'model' => $workspace]) && $workspace->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        
        $id_users = Yii::$app->request->post('id_users');

        if(!$id_users || empty($id_users))
        {
            throw new BadRequestHttpException(Module::t('Cant get id_users[]'));
        }

        $model = new AssignmentForm();
        if (!$model->load(Yii::$app->request->getBodyParams(), '')) {
            throw new BadRequestHttpException(Module::t('Invalid payload provided.'));
        }
        
        $model->id = $id_workspace;

        $model->selected_values = $id_users;

        if(!$model->type || empty($model->type))
        {
            $model->type = 'create';
        }

        if (!Yii::$app->workspace->isAvailableRole($model->id_module, $model->role)) {
            throw new UnprocessableEntityHttpException(Module::t('Role is not available for this module.'));
        }
        if (!$model->validate()) {
            throw new UnprocessableEntityHttpException(json_encode($model->getFirstErrors()));
        }

        if ($model->type == 'update') {
            return $this->actionAssignUpdate();
        }

        foreach ($model->selected_values as $user)
        {
            $workspaceUser = WorkspaceUser::find()->where([
            'id_workspace' => $model->id,
            'id_user' => $user,
            'id_module' => $model->id_module,
            'role' => $model->role])->one();

            if (!$workspaceUser) {
                $workspaceUser = new WorkspaceUser();
                $workspaceUser->id_workspace = $model->id;
                $workspaceUser->id_user = $user;
                $workspaceUser->id_module = $model->id_module;
                $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
            }
            $workspaceUser->role = $model->role;
            if (!$workspaceUser->save()) {
                throw new UnprocessableEntityHttpException(
                    json_encode(
                        $workspaceUser->getErrors(),
                        JSON_UNESCAPED_UNICODE
                    )
                );
            }
        }

        return true;
    }

    /**
     * POST /workspace/assignment/assign-update
     *
     * Updates role assignments for existing workspace users.
     *
     * Body: id_workspace, role, id_module, id_workspace_user[]
     *
     * @return bool
     */
    public function actionAssignUpdate()
    {
        $id_workspace = Yii::$app->request->post('id_workspace');
        $workspace = $this->findModel($id_workspace);

        if (!(Yii::$app->user->can('workspaceApiAssignmentAssignUpdate', ['id_module' => 'workspace', 'model' => $workspace]) ||
            (Yii::$app->user->can('workspaceApiAssignmentAssignUpdateOwn', ['id_module' => 'workspace', 'model' => $workspace]) && $workspace->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $role = Yii::$app->request->post('role');
        $ids = Yii::$app->request->post('id_workspace_user');
        $id_module = Yii::$app->request->post('id_module');

        if (!$role || !$id_module || !is_array($ids) || empty($ids)) {
            throw new BadRequestHttpException(
                Module::t(
                    'Missing required parameters: role, id_module or id_workspace_user.'
                )
            );
        }

        $errors = [];
        foreach ($ids as $id)
        {
            $workspaceUser = WorkspaceUser::find()->where(['id_workspace_user' => $id, 'id_workspace' => $id_workspace])->one();
            if(!$workspaceUser)
            {
                $errors[] = Module::t('couldnt find this assignment in this workspace: ' . $id);
                continue;
            }
            $workspaceUser->role = $role;
            $workspaceUser->id_module = $id_module;
            if (!Yii::$app->workspace->isAvailableRole($workspaceUser->id_module, $workspaceUser->role)) {
                $errors[] = Module::t('Role is not available for this module: ' . $role);
                continue;
            }
            if (WorkspaceUser::find()->where(['id_workspace' => $id_workspace, 'id_user' => $workspaceUser->id_user, 'id_module' => $id_module, 'role' => $role])->count() < 1) {
                if (!$workspaceUser->save()) {
                    throw new UnprocessableEntityHttpException(
                        json_encode(
                            $workspaceUser->getErrors(),
                            JSON_UNESCAPED_UNICODE
                        )
                    );
                }
            }
        }

        if (!empty($errors)) {
            throw new UnprocessableEntityHttpException(json_encode($errors));
        }

        return true;
    }

    /**
     * POST /workspace/assignment/remove
     *
     * Removes users role from a workspace.
     * if you remove all roles of a user from a workspace, the user will effectifly removed from the workspace.
     *
     * Body: id_workspace, id_workspace_user[]
     *
     * @return bool
     */
    public function actionRemove()
    {
        $id_workspace = Yii::$app->request->post('id_workspace');
        $workspace = $this->findModel($id_workspace);

        if (!(Yii::$app->user->can('workspaceApiAssignmentRemove', ['id_module' => 'workspace', 'model' => $workspace]) ||
            (Yii::$app->user->can('workspaceApiAssignmentRemoveOwn', ['id_module' => 'workspace', 'model' => $workspace]) && $workspace->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $workspaceUsersIds = Yii::$app->request->post('id_workspace_user');

        $workspaceUsers = [];

        foreach($workspaceUsersIds as $id)
        {
            $workspaceUser = WorkspaceUser::find()->where(['id_workspace_user' => $id, 'id_workspace' => $id_workspace])->one();
            if ($workspaceUser) {
                $workspaceUsers[] = $workspaceUser;
            }
        } 

        $uniqueModule = [];
        foreach ($workspaceUsers as $workspaceUser) {
            $uniqueModule[$workspaceUser->id_module] = $workspaceUser->id_module;
        }
        $workspaceAdminRoles = [];
        foreach ($uniqueModule as $module) {
            try {
                $role = Yii::$app->setting->getValue($module . '::workspace::admin_role');
                if ($role) {
                    $workspaceAdminRoles[$module] = $role;
                }
            } catch (\Exception $e) {
            }
        }

        $deletebleWorkspaceUsers = [];
        $errors = [];
        $checkWorkspacesDataProvider = WorkspaceUser::find()
            ->groupBy(Module::$tablePrefix . 'workspace_user.id_workspace_user')
            ->andWhere([Module::$tablePrefix . 'workspace_user.id_workspace' => $id_workspace])
            ->all();
        foreach ($workspaceUsers as $workspaceUser) {
            $count = count(array_filter($checkWorkspacesDataProvider, function ($workspaceUserCount) use ($workspaceUser) {
                return $workspaceUserCount->role == $workspaceUser->role && $workspaceUserCount->id_module == $workspaceUser->id_module;
            }));
            if (isset($workspaceAdminRoles[$workspaceUser->id_module]) && $workspaceUser->role == $workspaceAdminRoles[$workspaceUser->id_module] && $count < 2 && $workspaceUser->workspace->id_user == $workspaceUser->id_user) {
                $errors[] = sprintf(Module::t('You can not remove user %s from workspace %s because he is an administrator.'), $workspaceUser->user->username, $workspaceUser->workspace->name);
            } else {
                $deletebleWorkspaceUsers[] = $workspaceUser->id_workspace_user;
            }
        }
        WorkspaceUser::deleteAll(['id_workspace_user' => $deletebleWorkspaceUsers]);

        if (!empty($errors)) {
            throw new UnprocessableEntityHttpException(json_encode($errors));
        }

        return true;
    }

 
    /**
     * GET /workspace/assignment/assigned-users/:id
     *
     * Returns the users currently assigned to a workspace — one row per
     * (user, module) assignment 
     *
     * @param int $id Id Workspace
     * @return ArrayDataProvider
     */
    public function actionAssignedUsers($id)
    {
        $workspace = $this->findModel($id);
        if (!(Yii::$app->user->can('workspaceApiAssignmentView', ['id_module' => 'workspace', 'model' => $workspace]) ||
            (Yii::$app->user->can('workspaceApiAssignmentViewOwn', ['id_module' => 'workspace', 'model' => $workspace]) && $workspace->id_user == Yii::$app->user->id))) {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
 
        return new ArrayDataProvider([
            'allModels' => WorkspaceUser::find()
            ->select([UserModule::$tablePrefix . 'user.id_user', Module::$tablePrefix . 'workspace_user.id_workspace_user', 'username', Module::$tablePrefix . 'workspace_user.role', Module::$tablePrefix . 'workspace_user.id_module'])
            ->leftJoin(UserModule::$tablePrefix . 'user', UserModule::$tablePrefix . 'user.id_user = ' . Module::$tablePrefix . 'workspace_user.id_user')
            ->groupBy(Module::$tablePrefix . 'workspace_user.id_workspace_user')
            ->andWhere([Module::$tablePrefix . 'workspace_user.id_workspace' => $id])
            ->asArray()
            ->all(),
            'pagination' => [
                'defaultPageSize' => 10,
                'validatePage' => false,
            ],
            'sort' => [
                'attributes' => ['username', 'role', 'id_module'],
            ],
        ]);
    }

    /**
     * GET /workspace/assignment/get-roles
     *
     * Returns the roles assigned to a user in a worspace
     *
     * @return ArrayDataProvider
     */
    public function actionGetRoles()
    {
        $id_user = Yii::$app->request->get('id_user');
        $id_workspace = Yii::$app->request->get('id_workspace');

        $workspace = $this->findModel($id_workspace);
        if (!(Yii::$app->user->can('workspaceApiAssignmentView', ['id_module' => 'workspace', 'model' => $workspace]) ||
            (Yii::$app->user->can('workspaceApiAssignmentViewOwn', ['id_module' => 'workspace', 'model' => $workspace]) &&
            ($workspace->id_user == Yii::$app->user->id || $id_user == Yii::$app->user->id))))
        {
            throw new ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $roles = WorkspaceUser::find()
            ->select(['role', 'status', 'id_module'])
            ->andWhere(['id_user' => $id_user, 'id_workspace' => $id_workspace])
            ->groupBy('id_module')
            ->asArray()
            ->all();

        return new ArrayDataProvider([
            'allModels' => $roles,
            'pagination' => [
                'defaultPageSize' => 10,
                'validatePage' => false,
            ],
            'sort' => [
                'attributes' => ['role', 'status', 'id_module'],
            ],
        ]);
    }

    /**
     * GET /workspace/assignment/get-role-by-module
     *
     * Returns available roles for a given module.
     * 
     * @param string id_module
     * @return array
     */
    public function actionGetRoleByModule()
    {
        $moduleName = Yii::$app->request->get('id_module');
        if (!$moduleName)
        {
            throw new BadRequestHttpException(Module::t('Missing required parameter: id_module.'));
        }
        
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        $availableRoles = $availableRoles[$moduleName] ?? [];

        return $availableRoles;
    }

    /**
     * Finds the Workspace model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id_workspace Id Workspace
     * @return Workspace the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_workspace)
    {
        if (($model = Workspace::findOne([Module::$tablePrefix . 'workspace.id_workspace' => $id_workspace])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Module::t('The requested page does not exist.'));
    }

}