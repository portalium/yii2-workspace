<?php

use portalium\theme\widgets\ActionColumn;
use portalium\theme\widgets\ActiveForm;
use portalium\theme\widgets\Alert;
use portalium\theme\widgets\GridView;
use portalium\theme\widgets\Modal;
use yii\helpers\Html;
use portalium\workspace\Module;
use portalium\theme\widgets\Panel;
use portalium\workspace\models\Invitation;
use yii\helpers\Url;
use portalium\workspace\bundles\InvitationAsset;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */
/** @var string[] $notices kapatılana kadar duran uyarılar (atlanan / e-postası gönderilemeyen adresler) */

$this->title = Module::t('Invitations');
$referer = Yii::$app->request->referrer;
if ($referer && strpos($referer, 'manage') !== false) {
    $this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/manage']];
} else {
    $this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/index']];
}
$this->params['breadcrumbs'][] = ['label' => $model->displayTitle(), 'url' => ['/workspace/default/view', 'id' => $model->id_workspace]];
$this->params['breadcrumbs'][] = $this->title;
$canManageMembers = Yii::$app->user->can('workspaceWebDefaultAssignment', ['id_module' => 'workspace', 'model' => $model]);
$workspaceId = $model->id_workspace;
\yii\web\YiiAsset::register($this);
InvitationAsset::register($this);
?>
<div class="workspace-view">



    <p>
        <?php

        $formInvitation = ActiveForm::begin([
            'id' => 'invitation-form',
            'action' => Url::toRoute(['create', 'id' => $model->id_workspace]),
            'options' => [
                'data-pjax' => true,
                'autocomplete' => 'off'
            ]
        ]);
        Modal::begin([
            'size' => Modal::SIZE_LARGE,
            'options' => [
                'id' => 'invitation-modal',
                'data-open-on-load' => !empty($openInvitationModal) ? '1' : null,
            ],
            'title' => Module::t('Send Invitation'),
            'footer' => Html::submitButton(Module::t('Send'), ['class' => 'btn btn-success', 'id' => 'send-invitation-form'])
        ]);
        echo $this->render('_invitation', ['model' => $invitationModel, 'form' => $formInvitation, 'moduleArray' => $moduleArray, 'dynamicModuleModel' => $dynamicModuleModel, 'availableRoles' => $availableRoles]);
        Modal::end();
        ActiveForm::end();

        ?>
        <?php
        $headerActions = [];

        if (\Yii::$app->user->can(
            'workspaceWebDefaultCreateInvitation',
            ['id_module' => 'workspace', 'model' => $model]
        )) {
            $headerActions[] = Html::a(
                Module::t(''),
                ['#'],
                [
                    'class' => 'fa fa-plus btn btn-success',
                    'data-bs-toggle' => 'modal',
                    'data-bs-target' => '#invitation-modal',
                ]
            );
        }

        Panel::begin([
            'title' => Html::encode($this->title),
            'actions' => [
                'header' => $headerActions,
            ],
        ]) ?>

        <?php if (!empty($notices)) : ?>
            <div id="ws-invitation-notices">
                <?php foreach ($notices as $notice) : ?>
                    <?= Alert::widget([
                        'body' => Html::encode($notice),
                        'options' => ['class' => 'alert-warning', 'role' => 'alert'],
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


        <?= GridView::widget([
            'dataProvider' => $invitationDataProvider,
            'filterModel' => $invitationSearchModel,
            'columns' => [
                ['class' => 'portalium\grid\SerialColumn'],
                [
                    'attribute' => 'inviteeEmail',
                    'label' => Module::t('Email'),
                    'value' => static fn (Invitation $invitation) => $invitation->inviteeEmail(),
                ],
                [
                    'label' => Module::t('Status'),
                    'format' => 'raw',
                    'value' => static function (Invitation $invitation) {
                        $label = Html::encode(Invitation::statusLabels()[$invitation->addressStatus()]);
                        if (!$invitation->needsNewLink()) {
                            return $label;
                        }
                        return $label . ' ' . Html::tag('span', Html::encode(Module::t('Link must be renewed')), [
                            'class' => 'badge bg-warning text-dark ws-invitation-renew',
                            'title' => Module::t(
                                'The old shared link was removed. Resend the invitation with the «{button}» button in its row to create a new link.',
                                ['button' => Module::t('Resend invitation')]
                            ),
                        ]);
                    },
                ],
                'date_expire',
                [
                    'label' => Module::t('Invitation link'),
                    'format' => 'raw',
                    'value' => static function (Invitation $invitation) use ($canManageMembers, $workspaceId) {
                        $status = $invitation->addressStatus();
                        if ($status === Invitation::STATUS_ACCEPTED) {
                            return $canManageMembers
                                ? Html::a(Html::encode(Module::t('Members and roles')), ['/workspace/assignment/assignment', 'id' => $workspaceId])
                                : '';
                        }
                        if ($status !== Invitation::STATUS_PENDING) {
                            return '';
                        }
                        if (!$invitation->hasLink()) {
                            return Html::encode(Module::t('No link yet. Resend the invitation to create one.'));
                        }
                        $url = Url::toRoute(['accept', 'token' => $invitation->invitation_token], true);
                        return Html::button(Html::encode(Module::t('Copy link')), [
                            'class' => 'btn btn-info btn-xs ws-invitation-copy',
                            'data-url' => $url,
                            'data-copied' => Module::t('Copied'),
                            'data-prompt' => Module::t('Copy the invitation link:'),
                            'title' => $url,
                            'aria-live' => 'polite',
                        ]);
                    }
                ],
                [
                    'class' => ActionColumn::className(),
                    'urlCreator' => function ($action, Invitation $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id_invitation]);
                    },
                    'template' => '{detail} {update} {resend} {delete}',
                    'visibleButtons' => [
                        'update' => static fn (Invitation $invitation) => !$invitation->isAccepted(),
                        'resend' => static fn (Invitation $invitation) => !$invitation->isAccepted(),
                        'delete' => static fn (Invitation $invitation) => !$invitation->isAccepted(),
                    ],
                    'buttons' => [
                        'detail' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-eye']),
                                ['detail', 'id' => $model->id_invitation],
                                ['class' => 'btn btn-info btn-xs invitation-action', 'title' => Module::t('Details')]
                            );
                        },
                        'update' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-pencil']),
                                ['update', 'id' => $model->id_invitation],
                                ['class' => 'btn btn-warning btn-xs invitation-action', 'title' => Module::t('Update Invitation')]
                            );
                        },
                        'resend' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-paper-plane']),
                                ['resend', 'id' => $model->id_invitation],
                                [
                                    'class' => 'btn btn-success btn-xs invitation-action',
                                    'title' => Module::t('Resend invitation'),
                                    'data-method' => 'post',
                                    'data-confirm' => Module::t('Send this invitation again?'),
                                ]
                            );
                        },
                        'delete' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-trash']),
                                ['delete', 'id' => $model->id_invitation],
                                [
                                    'class' => 'btn btn-danger btn-xs invitation-action',
                                    'title' => Module::t('Delete invitation'),
                                    'data-method' => 'post',
                                    'data-confirm' => Module::t('Are you sure you want to delete this invitation?'),
                                ]
                            );
                        },
                    ],
                ],
            ],
            'layout' => '{items}{summary}{pagesizer}{pager}',
        ]); ?>

        <?php
        Panel::end()
        ?>

</div>
