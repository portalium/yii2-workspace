<?php

use portalium\theme\widgets\ActionColumn;
use portalium\theme\widgets\ActiveForm;
use portalium\theme\widgets\GridView;
use portalium\theme\widgets\Modal;
use yii\helpers\Html;
use portalium\workspace\Module;
use yii\widgets\DetailView;
use portalium\theme\widgets\Panel;
use portalium\workspace\models\Invitation;
use portalium\workspace\models\Workspace;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var portalium\workspace\models\Workspace $model */

$this->title = $model->name;
$referer = Yii::$app->request->referrer;
if ($referer && strpos($referer, 'manage') !== false) {
    $this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/manage']];
} else {
    $this->params['breadcrumbs'][] = ['label' => Module::t('Workspaces'), 'url' => ['/workspace/default/index']];
}
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
$this->registerCss(
    <<<CSS
    .ui-dialog .ui-dialog-titlebar-close {
        display: none;
    }
    .ui-dialog.ui-corner-all.ui-widget.ui-widget-content.ui-front.ui-dialog-buttons {
        font-size: inherit;
    }
    .ui-dialog-titlebar.ui-corner-all.ui-widget-header.ui-helper-clearfix {
        font-size: inherit;
        font-weight: inherit;
        background: transparent;
        border: none;
        border-bottom: 1px solid #ccc;
        border-radius: 0px;
    }
    .ui-dialog-buttonpane.ui-widget-content.ui-helper-clearfix {
        border-color: #ccc;
    }
    @keyframes dot-typing {
        0% {
            box-shadow: 9984px 0 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        }
        16.667% {
            box-shadow: 9984px -10px 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        }
        33.333% {
            box-shadow: 9984px 0 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        }
        50% {
            box-shadow: 9984px 0 0 0 #0d6efd, 9999px -10px 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        }
        66.667% {
            box-shadow: 9984px 0 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        }
        83.333% {
            box-shadow: 9984px 0 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px -10px 0 0 #0d6efd;
        }
        100% {
            box-shadow: 9984px 0 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        }
    }
    .snippet{
        padding-top: 11px;
        padding-left: 15px;
        margin-right: 15px;
    }

    .dot-typing {
        position: relative;
        left: -9999px;
        width: 7px;
        height: 7px;
        border-radius: 5px;
        background-color: #0d6efd;
        color: #0d6efd;
        box-shadow: 9984px 0 0 0 #0d6efd, 9999px 0 0 0 #0d6efd, 10014px 0 0 0 #0d6efd;
        animation: dot-typing 1.5s infinite linear;
    }
    CSS
);
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
                'id' => 'invitation-modal'
            ],
            'title' => Module::t('Send Invitation'),
            'footer' => Html::submitButton(Module::t('Add'), ['class' => 'btn btn-primary', 'id' => 'send-invitation-form'])
        ]);
        echo $this->render('_invitation', ['model' => $invitationModel, 'form' => $formInvitation, 'moduleArray' => $moduleArray, 'dynamicModuleModel' => $dynamicModuleModel, 'availableRoles' => $availableRoles, 'usersEmail' => $usersEmail]);
        Modal::end();
        ActiveForm::end();

        ?>
        <?php Panel::begin([
            'title' => Html::encode($this->title),
            'actions' => [
                'header' => [
                    Html::a(Module::t(''), ['#'], ['class' => 'fa fa-plus btn btn-success', 'data-bs-toggle' => 'modal', 'data-bs-target' => '#invitation-modal']),
                ]
            ]
        ]) ?>

        
        <?= GridView::widget([
            'dataProvider' => $invitationDataProvider,
            'filterModel' => $invitationSearchModel,
            'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                [
                    'attribute' => 'invitation_token',
                    'label' => Module::t('Invitation Url'),
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::a(
                            Url::toRoute(['accept', 'token' => $model->invitation_token], true),
                            Url::toRoute(['accept', 'token' => $model->invitation_token], true),
                            ['target' => '_blank']
                        );
                    }
                ],
                'date_expire',
                [
                    'class' => ActionColumn::className(),
                    'urlCreator' => function ($action, Invitation $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id_invitation]);
                    },
                    'template' => '{detail} {update}',
                    'buttons' => [
                        'detail' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-eye']),
                                ['detail', 'id' => $model->id_invitation],
                                ['class' => 'btn btn-primary btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;']
                            );
                        },
                        'update' => function ($url, $model, $key) {
                            return Html::a(
                                Html::tag('i', '', ['class' => 'fa fa-pencil']),
                                ['update', 'id' => $model->id_invitation],
                                ['class' => 'btn btn-primary btn-xs', 'style' => 'padding: 2px 9px 2px 9px; display: inline-block;']
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
<div id="dialog-confirm" title="Delete Invitation" style="display: none;">
    <div><?= Module::t('Are you sure delete this invitation?') ?></div>
</div>
<?php
$this->registerJs(
    <<<JS
    $(document).ready(function(){
        $('#send-invitation-form').on('click', function(e){
            // '<div class="snippet"><div class="dot-typing"></div></div>'
            e.target.innerHTML = '<div class="snippet"><div class="dot-typing"></div></div>';
        });

        // if delete button clicked then show confirm modal and choose all and single and cancel
        window.deleteInvitation = function(id){
            $('#dialog-confirm').dialog({
                resizable: false,
                closeOnEscape: false,
                draggable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: [
                    {
                        text: "Delete",
                        class: "btn btn-danger",
                        click: function() {
                            $.ajax({
                                url: '/workspace/invitation/delete?id='+id,
                                type: 'post',
                                data: {
                                    '_csrf-web': yii.getCsrfToken()
                                },
                                success: function(data) {
                                    // page refresh
                                    window.location.reload();
                                }
                            });
                            $( this ).dialog( "close" );
                        }
                    },
                    {
                        text: "Delete All",
                        class: "btn btn-danger",
                        click: function() {
                            $.ajax({
                                url: '/workspace/invitation/delete?id='+id+'&all=true',
                                type: 'post',
                                data: {
                                    '_csrf-web': yii.getCsrfToken()
                                },
                                success: function(data) {
                                    // page refresh
                                    window.location.reload();
                                }
                            });
                            $( this ).dialog( "close" );
                        }
                    },
                    {
                        text: "Cancel",
                        class: "btn btn-warning",
                        click: function() {
                            $( this ).dialog( "close" );
                        }
                    }
                ]
                
            });
        }

        
    });
JS
);
?>