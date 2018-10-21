<?php

namespace vaninsky\rlock\models;

use Yii;
use yii\base\Exception;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "job".
 */
class Job extends \vaninsky\rlock\models\base\Job
{
    use \vaninsky\rlock\models\Lockable;

    const MAX_WORKER_ID = 99999999999;

    const STATUS_NEW    = 0;
    const STATUS_WORK   = 1;
    const STATUS_OK     = 2;
    const STATUS_ERROR  = 3;

    // Sample
//    const TYPE_ACTION_1 = 1;
//    const TYPE_ACTION_2 = 2;
//     ...

    public static $actions = [
//        static::TYPE_ACTION_1 => 'doActionOne',
//        static::TYPE_ACTION_1 => 'doActionTwo',
//         ...
    ];


    public function behaviors()
    {
        return ArrayHelper::merge(
            parent::behaviors(),
            [
                # custom behaviors
            ]
        );
    }

    public function rules()
    {
        return ArrayHelper::merge(
            parent::rules(),
            [
                # custom validation rules
            ]
        );
    }


    /**
     * Get timestamp from last similar job
     * @param int $typeId
     * @param null $itemId
     * @return mixed
     */
    public static function getLastTimestamp(int $typeId, $itemId = null)
    {
        $query = static::find()
            ->select([new Expression('UNIX_TIMESTAMP(created_at)')])
            ->andWhere(['type_id' => $typeId])
            ->orderBy(['created_at' => SORT_DESC]);

        if (!empty($itemId)) {
            $query->andWhere(['item_id' => $itemId]);
        }
        return $query->scalar();
    }


    /**
     * @param array $data
     * @param array $options
     * @return Job|false
     */
    public static function createOne(array $data, array $options = [])
    {

        if (!empty($options['item_interval'])) {
            $last = static::getLastTimestamp($data['type_id'], $data['item_id']);
            $interval = intval($options['item_interval']);
        }
        if (!empty($options['interval'])) {
            $last = static::getLastTimestamp($data['type_id']);
            $interval = intval($options['interval']);
        }

        if (!empty($last) && ($last + $interval > time())) {
            if (!empty($options['skip'])) {
                return false;
            }
            else {
                $options['seconds'] = $last + $interval - time();
            }
        }
        /* @var self $model */
        $model = new static();
        $model->status_id = static::STATUS_NEW;
        $model->setAttributes($data);

        if (!empty($options['seconds'])) {
            $sec = intval($options['seconds']);
            $model->created_at = new Expression("DATE_ADD(NOW(), INTERVAL {$sec} SECOND)");
        }
        elseif (!empty($data['created_at'])) {
            $model->created_at = $data['created_at'];
        }
        $model->save();
        return $model;
    }


    /**
     * Run some tasks
     *
     * @param int $limit
     * @param array $params
     * @return int
     */
    public static function lockAndRun($limit = 1, $params = [])
    {
        $workerId = !empty($params['worker_id']) ? intval($params['worker_id']) : 1;
        $statusId = !empty($params['status_id']) ? intval($params['status_id']) : static::STATUS_NEW;

        $query = static::find()
            ->andWhere(['status_id' => $statusId])
            ->limit($limit);

        if (!empty($params['type_id'])) {
            $query->andWhere(['type_id' => $params['type_id']]);
        }
        static::andNotLocked($query);

        $cnt = 0;
        /* @var Job $job */
        foreach ($query->all() as $job) {
            if ($job->lock($workerId)) {
                $job->do();
                $cnt++;
            }
        }
        return $cnt;
    }


    /**
     * Do current job
     */
    public function do()
    {
        $this->updatedAtNow(['status_id' => $this->status_id]);

        $actionResult = false;
        $params = $this->getParams();

        if (!empty(static::$actions[$this->type_id])) {
            $action = static::$actions[$this->type_id];
            /* Do some action with params */
            $actionResult = static::$action($params);
        }

        if ($actionResult) {
            $this->status_id = static::STATUS_OK;
        }
        else {
            $this->status_id = static::STATUS_ERROR;
        }
        $this->updatedAtNow(['status_id' => $this->status_id]);
        $this->unlock();
    }


    /**
     * @return mixed|string
     */
    public function getParams()
    {
        if (!empty($this->params)) {
            if (is_string($this->params)) {
                $this->params = \json_decode($this->params, true);
            }
        }
        return $this->params;
    }

    /**
     * @param $insert
     * @return mixed
     */
    public function beforeSave($insert)
    {
        if (!$insert) {
            $this->updated_at = new Expression('NOW()');
        }
        if (empty($this->created_at)) {
            $this->created_at = new Expression('NOW()');
        }

        if (is_array($this->params)) {
            $this->params = \json_encode($this->params);
        }
        return parent::beforeSave($insert);
    }

    public function updatedAtNow($fields = [])
    {
        if (empty($fields['updated_at'])) {
            $fields['updated_at'] = new Expression('NOW()');
        }

        static::updateAll($fields, ['id' => $this->id]);
    }


}
