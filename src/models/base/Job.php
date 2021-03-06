<?php
// This class was automatically generated by a giiant build task
// You should not change it manually as it will be overwritten on next build

namespace vaninsky\rlock\models\base;

use Yii;

/**
 * This is the base-model class for table "job".
 *
 * @property integer $id
 * @property integer $type_id
 * @property integer $status_id
 * @property integer $item_id
 * @property string $title
 * @property string $params
 * @property string $created_at
 * @property string $updated_at
 *
 * @property string $aliasModel
 */
abstract class Job extends \yii\db\ActiveRecord
{

        /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'job';
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type_id', 'status_id', 'item_id'], 'integer'],
            [['created_at', 'updated_at', 'params'], 'safe'],
            [['title'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type_id' => 'Type ID',
            'status_id' => 'Status ID',
            'item_id' => 'item ID',
            'title' => 'Title',
            'params' => 'Params',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }


}
