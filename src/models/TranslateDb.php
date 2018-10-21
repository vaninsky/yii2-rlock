<?php

namespace vaninsky\modelockable\models;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "translate".
 */
class TranslateDb extends \vaninsky\modelockable\models\base\TranslateDb
{

    public static $translatable = false;

    public static $tables = [
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

    public static function getItemTranslations($tableName, $itemId, $lang = null)
    {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }

        $cache = Yii::$app->cache;
        $key = self::getCacheKey($tableName, $itemId, $lang);

        // get Cache
        $result = $cache->get($key);
        if (false === $result) {
            $query = self::find()
                ->select(['field_name', 'translate'])
                ->andWhere(['table_name' => $tableName])
                ->andWhere(['item_id' => $itemId])
                ->andWhere(['lang' => $lang]);

            $rows = $query->asArray()->all();
            $result = ArrayHelper::map($rows, 'field_name', 'translate');
            // set cache
            $cache->set($key, $result); // 30
        }

        return $result;
    }

    public static function getCacheKey($phrase, $module, $lang)
    {
        return [$phrase, $module, $lang];
    }


    /**
     * @param $tableName
     * @param $itemId
     * @param $fieldName
     * @param $translate
     * @param $lang
     * @return TranslateDb|array|null|\yii\db\ActiveRecord
     */
    public static function createOrUpdate($tableName, $itemId, $fieldName, $translate, $lang)
    {
        $item = self::find()
            ->andWhere(['table_name' => $tableName])
            ->andWhere(['item_id' => $itemId])
            ->andWhere(['field_name' => $fieldName])
            ->andWhere(['lang' => $lang])
            ->one();

        if (!empty($item) && empty($translate)) {
            // clear translate if empty
            $item->delete();
            return false;
        }

        if (empty($item)) {
            $item = new self();
            $item->table_name   = $tableName;
            $item->item_id      = $itemId;
            $item->field_name   = $fieldName;
            $item->lang         = $lang;
        }
        $item->translate    = $translate;
        $item->save();
        return $item;
    }

    public static function deleteItemTranslates($tableName, $itemId)
    {
//        $items = self::find()
//            ->andWhere(['table_name' => $tableName])
//            ->andWhere(['item_id' => $itemId])
//            ->all();
//        foreach ($items as $item) {
//            $item->delete();
//        }
        if (!empty($itemId)) {
            _log([$tableName, $itemId], 'deleteItemTranslates');
            self::deleteAll(['table_name' => $tableName, 'item_id' => $itemId]);
        }

    }


    public static function dropDownTables()
    {
        return self::$tables;
    }

    /**
     * @param $search
     * @return array
     */
    public static function searchGeoIds($search, $fullSearch = false)
    {
//        $query = self::find()
//            ->select(['DISTINCT(item_id)'])
//            ->andWhere(['table_name' => 'geo'])
//            ->andFilterWhere(['LIKE', 'translate', $search.'%', false]);
//        return $query->column();
        return self::searchIds('geo', $search, $fullSearch);
    }

    /**
     * @param $tableName
     * @param string $search
     * @return array
     */
    public static function searchIds(string $tableName, string $search, $fullSearch = false, $limit = null)
    {
        $query = self::find()
            ->select(['DISTINCT(item_id)'])
            ->andWhere(['table_name' => $tableName]);
        if ($fullSearch) {
            $query->andFilterWhere(['LIKE', 'translate', $search]);
        }
        else {
            $query->andFilterWhere(['LIKE', 'translate', $search.'%', false]);
        }
        if (!empty($limit)) {
            $query->limit($limit);
        }

        return $query->column();
    }

    /**
     * @param $tableName
     * @param string $search
     * @return array
     */
    public static function searchIdsExact(string $tableName, string $search)
    {
        $query = self::find()
            ->select(['DISTINCT(item_id)'])
            ->andWhere(['table_name' => $tableName])
            ->andWhere(['translate' => $search]);

        return $query->column();
    }

}
