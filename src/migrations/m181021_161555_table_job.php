<?php

use yii\db\Migration;

/**
 * Class m181021_161555_table_job
 */
class m181021_161555_table_job extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $sql = <<<'SQL'
            CREATE TABLE `job` (
              `id` INT(11) NOT NULL,
              `type_id` INT(11) DEFAULT NULL,
              `status_id` INT(11) NOT NULL DEFAULT '0',
              `item_id` INT(11) DEFAULT NULL,
              `title` VARCHAR(255) DEFAULT NULL,
              `params` MEDIUMTEXT,
              `created_at` DATETIME DEFAULT NULL,
              `updated_at` DATETIME DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            
            ALTER TABLE `job`
              ADD PRIMARY KEY (`id`),
              ADD KEY `type_id` (`type_id`),
              ADD KEY `status_id` (`status_id`),
              ADD KEY `created_at` (`created_at`),
              ADD KEY `updated_at` (`updated_at`);
            
            ALTER TABLE `job`
              MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
            COMMIT;     
SQL;
        Yii::$app->db->createCommand($sql)->query();
    }


    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $sql = "DROP TABLE `job`";
        Yii::$app->db->createCommand($sql)->query();
    }
}
