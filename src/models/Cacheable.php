<?php

namespace vaninsky\modelockable\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;

trait Cacheable
{
    public static function findOneCached(array $where) : ?self
    {
        /* @var Cache $cache */
        $cache = Yii::$app->cache;
        $key = ['findOne_'.self::tableName(), $where, \Yii::$app->language];

        // get Cache
        $result = $cache->get($key);
        if (false === $result) {
            $result = self::findOne($where);
            // set Cache
            $cache->set($key, $result);
        }
        return $result;
    }

    public static function findAllCached(ActiveQuery $query) : ?array
    {
        /* @var Cache $cache */
        $cache = Yii::$app->cache;
        $key = 'findOne_'.serialize($query).\Yii::$app->language;

        // get Cache
        $result = $cache->get($key);
        if (false === $result) {
            $result = $query->all();
            // set Cache
            $cache->set($key, $result);
        }
        return $result;
    }



}