<?php

use yii\helpers\Html;
use portalium\workspace\Module;
use portalium\workspace\widgets\WorkspaceSwitcher;

/* @var $this yii\web\View */
/* @var $id string */
/* @var $entries array[] */
/* @var $active array|null null when the active workspace is not in the list */
/* @var $links array[] */
/* @var $footer string */

$meta = function (array $entry) {
    $members = Module::t('{n, plural, =1{# member} other{# members}}', ['n' => $entry['members']]);

    return $entry['badge'] ? $entry['badge'] . ' · ' . $members : $members;
};
$tile = function (array $entry, string $class = '') {
    return Html::tag('span', Html::encode($entry['initials']), [
        'class' => trim('ws-tile ws-tile-' . $entry['color'] . ' ' . $class),
        'aria-hidden' => 'true',
    ]);
};
$check = '<svg class="ws-switcher-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"></path></svg>';
?>
<div class="ws-switcher dropdown" id="<?= Html::encode($id) ?>">
    <button type="button" class="ws-switcher-card" data-bs-toggle="dropdown" aria-expanded="false"
            data-bs-popper-config='{"strategy":"fixed"}' title="<?= Html::encode($active['title'] ?? Module::t('Select a workspace')) ?>">
        <?php if ($active !== null) : ?>
            <?= $tile($active) ?>
            <span class="ws-switcher-text">
                <span class="ws-switcher-title"><?= Html::encode($active['title']) ?></span>
                <span class="ws-switcher-meta"><?= Html::encode($meta($active)) ?></span>
            </span>
        <?php else : ?>
            <span class="ws-tile ws-tile-new" aria-hidden="true"><i class="fa fa-building-o"></i></span>
            <span class="ws-switcher-text">
                <span class="ws-switcher-title"><?= Module::t('Select a workspace') ?></span>
            </span>
        <?php endif; ?>
        <svg class="ws-switcher-chevrons" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"></path></svg>
    </button>
    <div class="dropdown-menu ws-switcher-menu">
        <div class="ws-switcher-heading"><?= Module::t('Workspaces') ?></div>
        <div class="ws-switcher-list">
            <?php foreach ($entries as $entry) : ?>
                <?php
                $inner = $tile($entry)
                    . Html::tag('span', Html::encode($entry['title']), ['class' => 'ws-switcher-name'])
                    . ($entry['badge'] ? Html::tag('span', Html::encode($entry['badge']), ['class' => 'ws-switcher-badge']) : '');
                if ($entry['active']) {
                    echo Html::tag('span', $inner . $check, ['class' => 'dropdown-item ws-switcher-item active', 'aria-current' => 'true']);
                } else {
                    echo Html::a($inner, ['/workspace/assignment/set-workspace', 'id' => $entry['id_workspace_user']], [
                        'class' => 'dropdown-item ws-switcher-item',
                        'data-method' => 'post',
                    ]);
                }
                ?>
            <?php endforeach; ?>
        </div>
        <?php if (WorkspaceSwitcher::canCreate()) : ?>
            <?= Html::a(
                Html::tag('span', '<i class="fa fa-plus" aria-hidden="true"></i>', ['class' => 'ws-tile ws-tile-new']) . Module::t('New workspace'),
                ['/workspace/default/create'],
                ['class' => 'dropdown-item ws-switcher-item ws-switcher-new']
            ) ?>
        <?php endif; ?>
        <?php if (!empty($links)) : ?>
            <div class="dropdown-divider"></div>
            <?php foreach ($links as $link) : ?>
                <?= Html::a(
                    Html::tag('i', '', ['class' => 'fa ' . $link['icon'], 'aria-hidden' => 'true']) . Html::encode($link['label']),
                    $link['url'],
                    ['class' => 'dropdown-item ws-switcher-link']
                ) ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($footer !== '') : ?>
            <div class="dropdown-divider"></div>
            <div class="ws-switcher-footer"><?= $footer ?></div>
        <?php endif; ?>
    </div>
</div>
