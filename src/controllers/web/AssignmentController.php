<?php

namespace portalium\workspace\controllers\web;

use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use portalium\workspace\Module;
use portalium\user\models\User;
use yii\web\NotFoundHttpException;
use portalium\user\Module as UserModule;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;
use portalium\web\Controller as WebController;
use yii\base\DynamicModel;

class AssignmentController extends WebController
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Displays the user assignment page.
     *
     * This function displays a page where users can be assigned to a specific workspace. It retrieves a list of all
     * users from the database and filters out those that are already assigned to the workspace. It also retrieves a
     * list of all available roles from the authManager component and passes these to the view.
     *
     * @param int $id_workspace The ID of the workspace for which users are to be assigned.
     * @return string The rendered view for user assignment.
     */
    public function actionAssignment($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultAssignment', ['id_module' => 'workspace', 'model' => $this->findModel($id)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if (!Yii::$app->workspace->checkSupportRoles()) {
            Yii::$app->session->setFlash('error', Module::t('Please set default role for workspace module.'));
            return $this->redirect(['/workspace/default/index']);
        }
        $workspace = $this->findModel($id);

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
        $dynamicModuleModel = new DynamicModel();
        $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');
        $dynamicModuleModel->defineAttribute('_labels');
        $dynamicModuleModel->addRule(['_labels'], 'safe');
        $dynamicModuleModel->_labels = [];
        $labels = [];
        foreach ($modules as $key => $value) {
            if (!isset($availableRoles[$key])) {
                continue;
            }
                
            $dynamicModuleModel->defineAttribute($key);
            // add rule for dynamic attributes
            $dynamicModuleModel->addRule($key, 'in', ['range' => $availableRoles[$key]]);
            // set _labels for dynamic attributes
            $labels[$key] = isset(Yii::$app->getModule($key)::$name) ? Yii::$app->getModule($key)::$name : $key;
        }
        $dynamicModuleModel->_labels = $labels;
        return $this->render('assignment', [
            'model' => $workspace,
            'users' => ArrayHelper::map($availableUsers, 'id_user', 'username'),
            'assignedUsers' => ArrayHelper::map($assignedUsers, 'id_workspace_user', 'username'),
            'moduleArray' => $moduleArray,
            'dynamicModuleModel' => $dynamicModuleModel,
            'availableRoles' => $availableRoles,
        ]);
    }


    /**
     * Assigns the specified role to the specified users for the specified workspace.
     *
     * This function assigns the specified role to the specified users for the specified workspace. It retrieves the
     * role, users, and workspace ID from the request. It then updates or inserts records into the Module::$tablePrefix . `_workspace_user`
     * table to reflect the new role assignments. Finally, it displays a success message to the user.
     *
     * @return bool True if the action was successful.
     */
    public function actionAssign()
    {
        $id_workspace = Yii::$app->request->get('id');
        if (!\Yii::$app->user->can('workspaceWebDefaultAssign', ['id_module' => 'workspace', 'model' => $this->findModel($id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        // Retrieve role, users, and workspace ID from the request
        $role = Yii::$app->request->get('role');
        $users = Yii::$app->request->get('selected_values');
        $id_module = Yii::$app->request->get('id_module');
        $type = Yii::$app->request->get('type');

        if ($type == 'update') {
            return $this->actionAssignUpdate();
        }

        $workspaceUsers = WorkspaceUser::find()->where(['id_workspace' => $id_workspace, 'id_user' => $users, 'id_module' => $id_module, 'role' => $role])->all();

        foreach ($users as $user) {
            $workspaceUser = array_filter($workspaceUsers, function ($workspaceUser) use ($user) {
                return $workspaceUser->id_user == $user;
            });
            $workspaceUser = array_shift($workspaceUser);
            if (!$workspaceUser) {
                $workspaceUser = new WorkspaceUser();
                $workspaceUser->id_workspace = $id_workspace;
                $workspaceUser->id_user = $user;
                $workspaceUser->id_module = $id_module;
                $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
            }
            $workspaceUser->role = $role;
            if (!Yii::$app->workspace->isAvailableRole($workspaceUser->id_module, $workspaceUser->role)) {
                Yii::$app->session->addFlash('error', Module::t('Role is not available for this module.'));
                return false;
            }
            $workspaceUser->save();
        }

        // Display success message to user
        Yii::$app->session->addFlash('success', 'Users assigned to role successfully.');

        // Return true if the action was successful
        return true;
    }


    /**
     * Updates the role assignments for the specified users in the specified workspace.
     *
     * This function updates the role assignments for the specified users in the specified workspace. It retrieves the
     * role, users, and workspace ID from the request. It then updates the corresponding records in the Module::$tablePrefix . `_workspace_user`
     * table to reflect the new role assignments. Finally, it displays a success message to the user.
     *
     * @return bool True if the action was successful.
     */
    public function actionAssignUpdate()
    {
        $id_workspace = Yii::$app->request->get('id');
        if (!\Yii::$app->user->can('workspaceWebDefaultAssignUpdate', ['id_module' => 'workspace', 'model' => $this->findModel($id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        // Retrieve role, users, and workspace ID from the request
        $role = Yii::$app->request->get('role');
        $workspaceUsers = Yii::$app->request->get('selected_values');
        $id_module = Yii::$app->request->get('id_module');

        $workspaceUsers = WorkspaceUser::find()->where(['id_workspace_user' => $workspaceUsers])->all();

        foreach ($workspaceUsers as $workspaceUser) {
            $workspaceUser->role = $role;
            $workspaceUser->id_module = $id_module;
            if (!Yii::$app->workspace->isAvailableRole($workspaceUser->id_module, $workspaceUser->role)) {
                Yii::$app->session->addFlash('error', Module::t('Role is not available for this module.'));
                continue;
            }
            if (WorkspaceUser::find()->where(['id_workspace' => $id_workspace, 'id_user' => $workspaceUser->id_user, 'id_module' => $id_module, 'role' => $role])->count() < 1) {
                $workspaceUser->save();
            } else {
            }
        }

        // Display success message to user
        Yii::$app->session->addFlash('success', 'Users assigned to role successfully.');

        // Return true if the action was successful
        return true;
    }

    /**
     * Removes the specified users from the specified workspace.
     *
     * This function removes the specified users from the specified workspace. It retrieves the users and workspace ID
     * from the request, and then deletes the corresponding records from the Module::$tablePrefix . `_workspace_user` table. Finally, it displays
     * a success message to the user.
     *
     * @return bool True if the action was successful.
     */
    public function actionRemove()
    {
        $id_workspace = Yii::$app->request->get('id_workspace');
        if (!\Yii::$app->user->can('workspaceWebDefaultRemove', ['id_module' => 'workspace', 'model' => $this->findModel($id_workspace)])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $workspaceUsersIds = Yii::$app->request->get('selected_values');
        $workspaceUsers = WorkspaceUser::find()->where(['id_workspace_user' => $workspaceUsersIds])->groupBy(['id_workspace_user'])->all();
        $uniqueModule = [];
        $workspaceAdminRoles = [];
        foreach ($workspaceUsers as $workspaceUser) {
            $uniqueModule[$workspaceUser->id_module] = $workspaceUser->id_module;
        }
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
        $checkWorkspacesDataProvider = WorkspaceUser::find()
            ->groupBy(Module::$tablePrefix . 'workspace_user.id_workspace_user')
            ->andWhere([Module::$tablePrefix . 'workspace_user.id_workspace' => $id_workspace])
            ->all();
        foreach ($workspaceUsers as $workspaceUser) {
            $count = count(array_filter($checkWorkspacesDataProvider, function ($workspaceUserCount) use ($workspaceUser) {
                return $workspaceUserCount->role == $workspaceUser->role && $workspaceUserCount->id_module == $workspaceUser->id_module;
            }));
            if (isset($workspaceAdminRoles[$workspaceUser->id_module]) && $workspaceUser->role == $workspaceAdminRoles[$workspaceUser->id_module] && $count < 2) {
                Yii::$app->session->addFlash('error', sprintf(Module::t('You can not remove user %s from workspace %s because he is an administrator.'), $workspaceUser->user->username, $workspaceUser->workspace->name));
            } else {
                $deletebleWorkspaceUsers[] = $workspaceUser->id_workspace_user;
                unset($checkWorkspacesDataProvider[array_search($workspaceUser, $workspaceUsers)]);
            }
        }

        WorkspaceUser::deleteAll(['id_workspace_user' => $deletebleWorkspaceUsers]);
        Yii::$app->session->addFlash('success', 'Users removed from role successfully.');
        return true;
    }

    public function actionSetWorkspace($id)
    {

        $workspaceUserModel = WorkspaceUser::findOne(['id_workspace_user' => $id, 'id_user' => Yii::$app->user->id]);
        if ($id == 0 || !$workspaceUserModel) {
            Yii::$app->session->addFlash('error', Module::t('You are not allowed to set this workspace.'));
            if (Yii::$app->request->referrer)
                return $this->redirect(Yii::$app->request->referrer);
            else
                return $this->redirect(['/']);
        }
        if (!\Yii::$app->user->can('workspaceWebDefaultSetWorkspace', ['id_module' => 'workspace', 'model' => Workspace::findOne(['id_workspace' => $workspaceUserModel->id_workspace])])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }

        $workspaceUsers = WorkspaceUser::find(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE])->groupBy('id_workspace_user')->all();
        if ($workspaceUsers) {
            // $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
            // $workspaceUser->save();
            foreach ($workspaceUsers as $workspaceUser) {
                $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
                $workspaceUser->save();
            }
        }
        $workspaceUser = WorkspaceUser::findOne(['id_workspace_user' => $id]);
        if ($workspaceUser) {
            $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
            $workspaceUser->save();
        }
        // return $this->goBack(Yii::$app->request->referrer);

        if (Yii::$app->request->referrer)
            return $this->redirect(Yii::$app->request->referrer);
        else
            return $this->redirect(['/']);
    }

    //get-users
    public function actionGetUsers()
    {
        $id_workspace = Yii::$app->request->post('id_workspace');
        $role = Yii::$app->request->post('role');
        $authManager = Yii::$app->authManager;
        $role = $authManager->getRole($role);
        if (!$id_workspace || !$role) {
            return json_encode([]);
        }

        $workspaceUsers = WorkspaceUser::find()->where(['id_workspace' => $id_workspace])->groupBy('id_user')->all();
        $workspaceUsersArray = [];
        foreach ($workspaceUsers as $workspaceUser) {
            $workspaceUsersArray[] = $workspaceUser->id_user;
        }
        // $users = array_diff($users, $workspaceUsersArray);
        $users = User::find()->all();
        $users = ArrayHelper::map($users, 'id_user', 'username');
        return json_encode($users);
    }

    public function actionGetRoles()
    {
        $id_user = Yii::$app->request->post('id_user');
        $authManager = Yii::$app->authManager;
        $roles = $authManager->getRolesByUser($id_user);
        $roles = ArrayHelper::map($roles, 'name', 'name');
        return json_encode($roles);
    }

    //get-role-by-module
    public function actionGetRoleByModule()
    {
        $out = [];
        if ($this->request->isPost) {
            $request = $this->request->post('depdrop_parents');
            $moduleName = $request[0];
            if ($moduleName == null || $moduleName == '') {
                return $this->asJson(['output' => [], 'selected' => '']);
            }
            $availableRoles = Yii::$app->setting->getValue('workspace::available_roles');

            $availableRoles = $availableRoles[$moduleName];
            foreach ($availableRoles as $key => $value) {
                $out[] = ['id' => $value, 'name' => $value];
            }
            return json_encode(['output' => $out, 'selected' => '']);
        }
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
