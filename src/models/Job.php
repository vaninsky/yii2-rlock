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

    const TYPE_IMPORT           = 1;
    const TYPE_EMAIL_ACTIVATE   = 2;
    const TYPE_SOME_ACTION      = 3;
    // ...


    public static $actions = [
        self::TYPE_IMPORT           => 'doImport',
        self::TYPE_EMAIL_ACTIVATE   => 'doEmailActivate',
        self::TYPE_SOME_ACTION      => 'doSomeAction',
        // ...
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
        $query = self::find()
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
            $last = self::getLastTimestamp($data['type_id'], $data['item_id']);
            $interval = intval($options['item_interval']);
        }
        if (!empty($options['interval'])) {
            $last = self::getLastTimestamp($data['type_id']);
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
        $model = new self();
        $model->status_id = self::STATUS_NEW;
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
        $statusId = !empty($params['status_id']) ? intval($params['status_id']) : self::STATUS_NEW;

        $query = self::find()
            ->andWhere(['status_id' => $statusId])
            ->limit($limit);

        if (!empty($params['type_id'])) {
            $query->andWhere(['type_id' => $params['type_id']]);
        }
        self::andNotLocked($query);

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
//        _log(['start', $this->id], 'job_log'); // Log
        $this->updatedAtNow(['status_id' => $this->status_id]);

        $result = false;
        $params = $this->getParams();

        if (!empty(self::$actions[$this->type_id])) {
            $action = self::$actions[$this->type_id];
            $this->$action($params);
        }

        if ($result) {
            $this->status_id = self::STATUS_OK;
        }
        else {
            $this->status_id = self::STATUS_ERROR;
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



}
