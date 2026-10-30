<?php

namespace portalium\workspace\widgets;

use Yii;
use yii\base\Widget;
use yii\web\YiiAsset;
use portalium\workspace\Module;
use portalium\workspace\bundles\WorkspaceSwitcherAsset;
use portalium\workspace\models\Workspace as WorkspaceModel;

/**
 * Workspace card at the top of the side menu: the active workspace, and a menu to switch to another one,
 * create one, open its settings or invite members.
 *
 * The top menu keeps the small {@see Workspace} widget; this one is meant for the side menu.
 */
class WorkspaceSwitcher extends Widget
{
    /**
     * @var callable|null function (WorkspaceModel[] $workspaces): array, short labels such as the
     * subscription plan, keyed by workspace id; shown as a badge next to each workspace and in the card.
     * Called once with all of the user's workspaces.
     */
    public $badges;

    /**
     * @var callable|null function (WorkspaceModel $workspace): string, HTML added at the bottom of the
     * menu for the active workspace (usage, credits). The caller encodes what it returns.
     */
    public $footer;

    /**
     * Colours of the initials tiles; picked by workspace id so a workspace keeps its colour.
     */
    public const COLORS = 6;

    public function run()
    {
        if (Yii::$app->user->isGuest) {
            return '';
        }

        $entries = $this->entries();
        if (empty($entries)) {
            return '';
        }

        $active = null;
        foreach ($entries as $entry) {
            if ($entry['active']) {
                $active = $entry;
                break;
            }
        }

        YiiAsset::register($this->getView());
        WorkspaceSwitcherAsset::register($this->getView());

        // no active workspace in the list (e.g. a virtual one is active): the card asks to pick one
        return $this->render('workspace-switcher', [
            'id' => $this->getId(),
            'entries' => $entries,
            'active' => $active,
            'links' => $active !== null ? $this->links($active['model']) : [],
            'footer' => $active !== null && is_callable($this->footer) ? (string) call_user_func($this->footer, $active['model']) : '',
        ]);
    }

    /**
     * The user's (non-virtual) workspaces, each with what the card shows.
     *
     * @return array[] keys: id_workspace_user, id_workspace, title, initials, color, members, badge, active, model
     */
    public function entries(): array
    {
        $memberships = Yii::$app->workspace->getJoinedWorkspaces(WorkspaceModel::IS_VIRTUAL_FALSE);
        $ids = [];
        foreach ($memberships as $membership) {
            $ids[] = $membership->id_workspace;
        }
        $counts = Yii::$app->workspace->getMemberCounts($ids);
        $activeId = (int) Yii::$app->workspace->getId();
        $badges = [];
        if (is_callable($this->badges)) {
            $models = [];
            foreach ($memberships as $membership) {
                if ($membership->workspace !== null) {
                    $models[] = $membership->workspace;
                }
            }
            $badges = (array) call_user_func($this->badges, $models);
        }

        $entries = [];
        foreach ($memberships as $membership) {
            $workspace = $membership->workspace;
            if ($workspace === null) {
                continue;
            }
            $title = $workspace->displayTitle();
            $entries[] = [
                'id_workspace_user' => $membership->id_workspace_user,
                'id_workspace' => (int) $workspace->id_workspace,
                'title' => $title,
                'initials' => self::initials($title),
                'color' => self::color((int) $workspace->id_workspace),
                'members' => $counts[$workspace->id_workspace] ?? 0,
                'badge' => $badges[$workspace->id_workspace] ?? null,
                'active' => (int) $workspace->id_workspace === $activeId,
                'model' => $workspace,
            ];
        }

        return $entries;
    }

    /**
     * Links under the list, only those the user may open.
     *
     * @return array[] keys: label, url, icon
     */
    protected function links(WorkspaceModel $active): array
    {
        $user = Yii::$app->user;
        $links = [];
        if ($user->can('workspaceWebDefaultUpdate', ['id_module' => 'workspace', 'model' => $active])) {
            $links[] = [
                'label' => Module::t('Workspace settings'),
                'url' => ['/workspace/default/update', 'id' => $active->id_workspace],
                'icon' => 'fa-sliders',
            ];
        }
        $params = ['id_module' => 'workspace', 'model' => $active];
        // the invitations page needs View; sending one needs CreateInvitation
        if ($user->can('workspaceWebDefaultView', $params) && $user->can('workspaceWebDefaultCreateInvitation', $params)) {
            $links[] = [
                'label' => Module::t('Invite members'),
                'url' => ['/workspace/invitation/index', 'id' => $active->id_workspace],
                'icon' => 'fa-user-plus',
            ];
        }

        return $links;
    }

    /**
     * Whether the user may create a workspace (the "New workspace" row).
     */
    public static function canCreate(): bool
    {
        $model = new WorkspaceModel(['id_user' => Yii::$app->user->id]);

        return Yii::$app->user->can('workspaceWebDefaultCreate', ['id_module' => 'workspace', 'model' => $model]);
    }

    /**
     * Up to two letters for the tile: the first letters of the first two words, or the first two
     * letters of a single word.
     */
    public static function initials(string $title): string
    {
        $words = preg_split('/[\s\-_.]+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words)) {
            return '?';
        }
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }

    /**
     * Index of the tile colour (0 .. COLORS-1) for a workspace.
     */
    public static function color(int $idWorkspace): int
    {
        return abs($idWorkspace) % self::COLORS;
    }
}
