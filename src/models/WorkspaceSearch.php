<?php

namespace portalium\workspace\models;

use yii\base\Model;
use portalium\data\ActiveDataProvider;
use portalium\workspace\models\Workspace;

/**
 * WorkspaceSearch represents the model behind the search form of `portalium\workspace\models\Workspace`.
 */
class WorkspaceSearch extends Workspace
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_workspace'], 'integer'],
            [['name', 'date_create', 'date_update'], 'safe'],
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
        $query = Workspace::find();

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
            'date_create' => $this->date_create,
            'date_update' => $this->date_update,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name]);

        return $dataProvider;
    }
}
