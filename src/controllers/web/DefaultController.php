<?php

namespace portalium\workspace\controllers\web;

use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use portalium\user\models\User;
use portalium\workspace\Module;
use yii\web\NotFoundHttpException;
use portalium\workspace\models\Workspace;
use portalium\workspace\models\WorkspaceUser;
use portalium\workspace\models\WorkspaceSearch;
use portalium\web\Controller as WebController;

class DefaultController extends WebController
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
     * Lists all Workspace models.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultIndex', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $searchModel = new WorkspaceSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Workspace model.
     * @param int $id_workspace Id Workspace
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultView', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Workspace model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultCreate', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $model = new Workspace();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                return $this->redirect(['view', 'id' => $model->id_workspace]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Workspace model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id_workspace Id Workspace
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultUpdate', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_workspace]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Workspace model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id_workspace Id Workspace
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultDelete', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
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
        if (!\Yii::$app->user->can('workspaceWebDefaultAssignment', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        $workspace = $this->findModel($id);
        
        $users = User::find()->select(['id_user', 'username'])->asArray()->all();
        $assignedUsers = User::find()
            ->select(['user_user.id_user', 'username'])
            ->leftJoin(Module::$tablePrefix . 'workspace_user', 'user_user.id_user = ' . Module::$tablePrefix . 'workspace_user.id_user')
            ->andWhere([Module::$tablePrefix . 'workspace_user.id_workspace' => $id])
            ->asArray()
            ->all();
            
        $assignedUserIds = array_column($assignedUsers, 'id_user');
        $availableUsers = array_filter($users, function($user) use ($assignedUserIds) {
            return !in_array($user['id_user'], $assignedUserIds);
        });
        
        $assignedUsers = array_map(function($user) use ($id) {
            $role = Yii::$app->db->createCommand('SELECT role FROM ' . Module::$tablePrefix . 'workspace_user WHERE id_workspace=:id AND id_user=:user_user')
                ->bindValue(':id', $id)
                ->bindValue(':user_user', $user['id_user'])
                ->queryScalar();
            $user['username'] = $user['username'] . ' (' . $role . ')';
            return $user;
        }, $assignedUsers);
        
        $modules = Yii::$app->workspace->getSupportModules();
        $moduleArray = [];
        foreach ($modules as $key => $value) {
            $moduleArray[$key] = $key;
        }
        Yii::warning($moduleArray);
        return $this->render('assignment', [
            'model' => $workspace,
            'users' => ArrayHelper::map($availableUsers, 'id_user', 'username'),
            'assignedUsers' => ArrayHelper::map($assignedUsers, 'id_user', 'username'),
            'moduleArray' => $moduleArray,
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
        if (!\Yii::$app->user->can('workspaceWebDefaultAssign', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        // Retrieve role, users, and workspace ID from the request
        $role = Yii::$app->request->get('role');
        $users = Yii::$app->request->get('users');
        $id_workspace = Yii::$app->request->get('id');
        $id_module = Yii::$app->request->get('id_module');

        // Assign role to users for the workspace
        foreach ($users as $user) {
            $workspaceUser = WorkspaceUser::findOne(['id_workspace' => $id_workspace, 'id_user' => $user]);
            if (!$workspaceUser) {
                $workspaceUser = new WorkspaceUser();
                $workspaceUser->id_workspace = $id_workspace;
                $workspaceUser->id_user = $user;
                $workspaceUser->id_module = $id_module;
            }
            $workspaceUser->role = $role;

            // Check if user is active in the workspace
            $status = WorkspaceUser::find()->where(['id_user' => $user, 'status' => WorkspaceUser::STATUS_ACTIVE])->one();
            $workspaceUser->status = $status ? WorkspaceUser::STATUS_ACTIVE : WorkspaceUser::STATUS_INACTIVE;
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
        if (!\Yii::$app->user->can('workspaceWebDefaultAssignUpdate', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        // Retrieve role, users, and workspace ID from the request
        $role = Yii::$app->request->get('role');
        $users = Yii::$app->request->get('users');
        $id_workspace = Yii::$app->request->get('id');
        $id_module = Yii::$app->request->get('id_module');

        // Update role assignments for users in the workspace
        foreach ($users as $user) {
            $workspaceUser = WorkspaceUser::findOne(['id_workspace' => $id_workspace, 'id_user' => $user]);
            if ($workspaceUser) {
                $workspaceUser->role = $role;
                $workspaceUser->id_module = $id_module;
                $workspaceUser->save();
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
        if (!\Yii::$app->user->can('workspaceWebDefaultRemove', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        // Retrieve users and workspace ID from the request
        $users = Yii::$app->request->get('users');
        $id_workspace = Yii::$app->request->get('id');

        // Remove users from the workspace
        foreach ($users as $user) {
            $workspaceUser = WorkspaceUser::findOne(['id_workspace' => $id_workspace, 'id_user' => $user]);
            $workspaceUser->delete();
        }

        // Display success message to user
        Yii::$app->session->addFlash('success', 'Users removed from role successfully.');
        // Return true if the action was successful
        return true;
    }

    public function actionSetWorkspace($id)
    {
        if (!\Yii::$app->user->can('workspaceWebDefaultSetWorkspace', ['id_module' => 'workspace'])) {
            throw new \yii\web\ForbiddenHttpException(Module::t('You are not allowed to access this page.'));
        }
        if ($id == 0) {
            return $this->goBack(Yii::$app->request->referrer);
        }
        $workspaceUser = WorkspaceUser::findOne(['id_user' => Yii::$app->user->id, 'status' => WorkspaceUser::STATUS_ACTIVE]);
        if ($workspaceUser) {
            $workspaceUser->status = WorkspaceUser::STATUS_INACTIVE;
            $workspaceUser->save();
        }

        $workspaceUser = WorkspaceUser::findOne(['id_workspace_user' => $id]);
        if ($workspaceUser) {
            $workspaceUser->status = WorkspaceUser::STATUS_ACTIVE;
            $workspaceUser->save();
        }
        return $this->goBack(Yii::$app->request->referrer);
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
        $users = $authManager->getUserIdsByRole($role->name);
        $workspaceUsers = WorkspaceUser::find()->where(['id_workspace' => $id_workspace])->groupBy('id_user')->all();
        $workspaceUsersArray = [];
        foreach ($workspaceUsers as $workspaceUser) {
            $workspaceUsersArray[] = $workspaceUser->id_user;
        }
        $users = array_diff($users, $workspaceUsersArray);
        $users = User::find()->where(['id_user' => $users])->all();
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
            Yii::warning($availableRoles);
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