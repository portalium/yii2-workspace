<?php

namespace portalium\workspace\models;

use portalium\data\ActiveDataProvider;
use portalium\workspace\models\Invitation;
/**
 * This is the model class for table "workspace_invitation".
 *
 * @property int $id_invitation
 * @property int $id_workspace
 * @property string $email
 * @property string $module
 * @property string $role
 * @property string $invitation_token
 * @property int $id_user
 * @property string $date_create
 * @property string $date_expire
 */
/**
 * InvitationSearch represents the model behind the search form of `portalium\invitation\models\Invitation`.
 */
class InvitationSearch extends Invitation
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_invitation', 'id_workspace', 'id_user'], 'integer'],
            [['invitation_token', 'date_create', 'date_expire'], 'safe'],
        ];
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Invitation::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id_workspace' => $this->id_workspace,
            'id_user' => $this->id_user,
            'date_create' => $this->date_create,
            'date_expire' => $this->date_expire
        ]);

        $query
            ->andFilterWhere(['like', 'invitation_token', $this->invitation_token]);

        return $dataProvider;
    }
}
