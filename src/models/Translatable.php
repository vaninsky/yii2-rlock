<?php

namespace vaninsky\modelockable\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use vaninsky\modelockable\models\TranslateDb;
use yii\redis\Cache;

trait Translatable
{
    public static $translatable = true;

    public $currentLang;

    public $translated = [];

    public static $availableLangs = [
        'ru',
        'en',
        'de',
        'fr',
        'it',
        'it',
        'ua',
    ];

    public static function getTranslatedFields()
    {
        return static::$_translatedFields;
    }

    public function __get($name)
    {
        if (substr($name, 0, 2) === 't_') {
            $name = substr($name, 2);
            if (static::$translatable && in_array($name, static::$_translatedFields)) {
                return $this->getTranslatedAttribute($name);
            }
        }
        return parent::__get($name);
    }

    /**
     * Translate field value to $lang
     *
     * @param $name
     * @param null $lang
     * @param bool $emptyDefault
     * @return mixed
     */
    public function t($name, $lang = null, $emptyDefault = true)
    {
        return $this->getTranslatedAttribute($name, $lang, $emptyDefault);
    }

    /**
     *
     */
    public function getTranslated()
    {
        if (empty($this->translated)) {
            foreach (self::$availableLangs as $code) {
                $this->getTranslatedAttributes($code);
            }
        }
        return $this->translated;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getTranslatedAttribute($name, $lang = null, $emptyDefault = false)
    {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }
        if (empty($this->translated[$lang][$name])) {
            // load translation
            $this->getTranslatedAttributes($lang);
        }

        if (!empty($this->translated[$lang][$name])) {
            $result = $this->translated[$lang][$name];
        } elseif($emptyDefault) {
            $result = '';
        } else {
            $result = parent::__get($name);
        }
        return $result;
    }

    /**
     * @param $name
     * @param string|null $lang
     * @return mixed
     */
    public function getTranslatedOrTranslit($name, $lang = null)
    {
        $emptyDefault = (\Yii::$app->language == 'en') ? true : false;

        $result = $this->getTranslatedAttribute($name, $lang, $emptyDefault);
        if (empty($result)) {
            $result = Abc::translit(parent::__get($name), false, ' ');
        }

        return $result;
    }

    /**
     * @param $fieldName
     * @param $translate
     * @param $lang
     */
    public function saveTranslate($fieldName, $translate, $lang)
    {
        if (!empty($translate)) {
            TranslateDb::createOrUpdate(static::tableName(), $this->id, $fieldName, $translate, $lang);
            /* @var Cache $cache */
            $cache = Yii::$app->cache;
            $key = $this->getTranslatedKey($lang);
            $cache->delete($key);
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public function setTranslatedAttribute($name, $value, $lang = null)
    {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }
        $this->translated[$lang][$name] = $value;
    }

    /**
     * @return array
     */
    public function getTranslatedAttributes($lang)
    {
        if (empty($this->id)) {
            return false;
        }
        $cache = Yii::$app->cache;
        $key = $this->getTranslatedKey($lang);
        $this->translated[$lang] = $cache->get($key);
        if (empty($this->translated[$lang])) {
            $this->translated[$lang] = TranslateDb::getItemTranslations(static::tableName(), $this->id, $lang);
            $cache->set($key, $this->translated[$lang]); // 30
        }
        else {
        }
        return $this->translated[$lang];
    }


    /**
     * @param $lang
     * @return string
     */
    public function getTranslatedKey($lang)
    {
        return [$lang, static::tableName(), $this->id];
    }

    public function getCacheParamKey($param, $lang = null)
    {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }
        return [$param, $lang, static::tableName(), $this->id];
    }


    public function setAttributes($values, $safeOnly = true)
    {
        _log([$safeOnly, $values], 'setAttributes_'.static::tableName());

        if (!empty($values['translated'])) {
            $this->translated = $values['translated'];
            unset($values['translated']);
        }
        if (!empty($values['tags'])) {
            $this->related['tags'] = $values['tags'];
            unset($values['tags']);
        }

        parent::setAttributes($values, $safeOnly);
    }

    public function saveTranslatedAttributes()
    {
        if (!empty($this->translated)) {
            _log($this->translated, 'saveTranslatedAttributes');

            foreach ($this->translated as $lang => $fields) {
                foreach ($fields as $fieldName => $value) {
                    if (!empty($value)) {
                        $this->saveTranslate($fieldName, $value, $lang);
                    }
                }
            }
        }
    }

    public function beforeDelete()
    {
        if (static::$translatable) {
            TranslateDb::deleteItemTranslates(static::tableName(), $this->id);
        }
        static::deleteRelated();

        return parent::beforeDelete();
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        static::saveTranslatedAttributes();

        static::saveRelated();
    }


    public function updatedAtNow($fields = [])
    {
        $fields['updated_at'] = new Expression('NOW()');

        static::updateAll($fields, ['id' => $this->id]);
    }

    public function saveRelated()
    {
    }

    public function deleteRelated()
    {
    }



}