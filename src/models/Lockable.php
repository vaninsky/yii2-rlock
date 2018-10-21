<?php

namespace vaninsky\modelockable\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;

trait Lockable
{
    /**
     * @param $workerId
     * @param int $expire
     * @return bool
     */
    public function lock(int $workerId = 1, int $expire = 3600): bool
    {
        return static::lockByKey($this->lockKey(), $workerId, $expire);
    }

    /**
     * @return mixed
     */
    public function unlock()
    {
        return static::unlockByKey($this->lockKey());
    }

    /**
     * Get current row locker
     * @return mixed
     */
    public function getLocker()
    {
        return static::getLockerByKey($this->lockKey());
    }

    /**
     * Return lock key as "table_name:id"
     *
     * @return string
     */
    public function lockKey()
    {
        return static::tableName().':'.$this->id;
    }

    /**
     * Lock row
     *
     * @param $key
     * @param $workerId
     * @param int $expire
     * @return bool
     */
    public static function lockByKey($key, $workerId, $expire = 3600): bool
    {
        /* @var yii\redis\Connection $redis */
        $redis = Yii::$app->redisLocker;
        if ($redis->setnx($key, $workerId)) {
            $redis->expire($key, $expire);
            $result = true;
        }
        else {
            $result = false;
        }
        return $result;
    }

    public static function unlockByKey($key)
    {
        /* @var yii\redis\Connection $redis */
        $redis = Yii::$app->redisLocker;
        return $redis->del($key);
    }

    /**
     * Return worker id (locker owner)
     * @param $key
     * @return mixed
     */
    public static function getLockerByKey($key)
    {
        /* @var yii\redis\Connection $redis */
        $redis = Yii::$app->redisLocker;
        return $redis->get($key);
    }

    /**
     * @return array
     */
    public static function getLockedIds()
    {
        /* @var yii\redis\Connection $redis */
        $redis = Yii::$app->redisLocker;
        $keys = $redis->keys(static::tableName().':*');
        $result = [];
        foreach ($keys as $key) {
            $parts = explode(':', $key);
            if (!empty($parts[1])) {
                $result[] = intval($parts[1]);
            }
        }
        return $result;
    }

    /**
     * @param ActiveQuery $query
     * @return ActiveQuery
     */
    public static function andNotLocked(ActiveQuery $query)
    {
        $keys = static::getLockedIds();
        if (!empty($keys)) {
            $query->andWhere(['NOT', ['id' => $keys]]);
        }
        return $query;
    }



}