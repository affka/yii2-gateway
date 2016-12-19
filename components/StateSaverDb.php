<?php

namespace gateway\components;

use yii\base\Component;
use yii\db\Query;
use yii\db\Schema;

class StateSaverDb extends Component implements IStateSaver
{

    public $tableName;

    public function init()
    {
        parent::init();
    }

    /**
     * @param string|int $id
     * @param array $data
     */
    public function set($id, $data)
    {
        $this->lazyCreateTable();

        \Yii::$app->db->createCommand()->insert($this->tableName, [
            'key' => $id,
            'value' => $data,
        ])->execute();
    }

    /**
     * @param string|int $id
     * @return mixed|null
     */
    public function get($id)
    {
        $this->lazyCreateTable();

        return (new Query())
            ->select('value')
            ->from($this->tableName)
            ->where([
                'key' => $id,
            ])
            ->scalar() ?: null;
    }

    protected function lazyCreateTable() {
        if (\Yii::$app->db->schema->getTableSchema($this->tableName, true) === null) {
            \Yii::$app->db->createCommand()->createTable($this->tableName, [
                'id' => Schema::TYPE_PK,
                'key' => Schema::TYPE_STRING,
                'value' => Schema::TYPE_TEXT,
            ])->execute();
        }
    }

}