<?php

use yii\db\Migration;

class m260730_111424_workspace_api_rbac extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $auth = \Yii::$app->authManager; 
        // Fetch roles
        $adminRole = \Yii::$app->setting->getValue('site::admin_role');
        $admin = (isset($adminRole) && $adminRole != '') ? $auth->getRole($adminRole) : $auth->getRole('admin');
        $user = $auth->getRole('user');

        $permissions = [
            // Global Permissions (Admin access to any workspace)
            'workspaceApiInvitationView' => 'View workspace information via API',
            'workspaceApiInvitationCreate' => 'Create workspace invitation via API',
            'workspaceApiInvitationUpdate' => 'Update workspace invitation via API',
            'workspaceApiInvitationDelete' => 'Delete workspace invitation via API',
            'workspaceApiInvitationResend' => 'Resend workspace invitation via API',

            'workspaceApiDefaultIndex' => 'Index workspaces via API',
            'workspaceApiDefaultView' => 'View workspace information via API',
            //'workspaceApiDefaultCreate' => 'Create workspace via API', //no one can create workspaces for others
            'workspaceApiDefaultUpdate' => 'Update workspace via API',
            'workspaceApiDefaultDelete' => 'Delete workspace via API',
            'workspaceApiDefaultSetWorkspace' => 'Set active workspace via API',

            'workspaceApiAssignmentView'         => 'View workspace assignment information via API',
            'workspaceApiAssignmentAssign'       => 'Assign users to workspace roles via API',
            'workspaceApiAssignmentAssignUpdate' => 'Update user workspace assignment roles via API',
            'workspaceApiAssignmentRemove'       => 'Remove users from workspace assignments via API',



            // "Own" Permissions (Users accessing their own workspaces)
            'workspaceApiInvitationViewOwn' => 'View own workspace information via API',
            'workspaceApiInvitationCreateOwn' => 'Create invitation for own workspace via API',
            'workspaceApiInvitationUpdateOwn' => 'Update invitation for own workspace via API',
            'workspaceApiInvitationDeleteOwn' => 'Delete invitation for own workspace via API',
            'workspaceApiInvitationResendOwn' => 'Resend invitation for own workspace via API',
            
            'workspaceApiDefaultIndexOwn' => 'Index own workspaces via API',
            'workspaceApiDefaultViewOwn' => 'View own workspace information via API',
            'workspaceApiDefaultCreateOwn' => 'Create own workspace via API',
            'workspaceApiDefaultUpdateOwn' => 'Update own workspace via API',
            'workspaceApiDefaultDeleteOwn' => 'Delete own workspace via API',
            'workspaceApiDefaultSetWorkspaceOwn' => 'Set active own workspace via API',

            'workspaceApiAssignmentViewOwn'         => 'View own workspace assignment information via API',
            'workspaceApiAssignmentAssignOwn'       => 'Assign users to own workspace roles via API',
            'workspaceApiAssignmentAssignUpdateOwn' => 'Update user own workspace assignment roles via API',
            'workspaceApiAssignmentRemoveOwn'       => 'Remove users from own workspace assignments via API',
        ];

        foreach ($permissions as $permissionKey => $permissionDescription) {
            $permissionObject = $auth->createPermission($permissionKey);
            $permissionObject->description = $permissionDescription;
            $auth->add($permissionObject);

            // Assign "Own" permissions to regular user role, and global permissions to admin
            if (strpos($permissionKey, 'Own') !== false) {
                if ($user) {
                    $auth->addChild($user, $permissionObject);
                }
            } else {
                if ($admin) {
                    $auth->addChild($admin, $permissionObject);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $auth = \Yii::$app->authManager;

        $permissions = [
            'workspaceApiInvitationView',
            'workspaceApiInvitationCreate',
            'workspaceApiInvitationUpdate',
            'workspaceApiInvitationDelete',
            'workspaceApiInvitationResend',
            'workspaceApiInvitationViewOwn',
            'workspaceApiInvitationCreateOwn',
            'workspaceApiInvitationUpdateOwn',
            'workspaceApiInvitationDeleteOwn',
            'workspaceApiInvitationResendOwn',
            'workspaceApiAssignmentView',
            'workspaceApiAssignmentAssign',
            'workspaceApiAssignmentAssignUpdate',
            'workspaceApiAssignmentRemove',



            'workspaceApiDefaultIndex',
            'workspaceApiDefaultView',
            //'workspaceApiDefaultCreate',
            'workspaceApiDefaultUpdate',
            'workspaceApiDefaultDelete',
            'workspaceApiDefaultSetWorkspace',
            'workspaceApiDefaultIndexOwn',
            'workspaceApiDefaultViewOwn',
            'workspaceApiDefaultCreateOwn',
            'workspaceApiDefaultUpdateOwn',
            'workspaceApiDefaultDeleteOwn',
            'workspaceApiDefaultSetWorkspaceOwn',
            'workspaceApiAssignmentViewOwn',
            'workspaceApiAssignmentAssignOwn',
            'workspaceApiAssignmentAssignUpdateOwn',
            'workspaceApiAssignmentRemoveOwn',
        ];

        foreach ($permissions as $permissionKey) {
            $permissionObject = $auth->getPermission($permissionKey);
            if ($permissionObject) {
                $auth->remove($permissionObject);
            }
        }
    }
}
