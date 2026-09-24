<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle\migrations;

use arifje\slugtotitle\db\Table;
use craft\db\Migration;
use craft\db\Table as CraftTable;

/**
 * Creates the plugin's tables on install and removes them on uninstall.
 *
 * @author arifje
 * @since 1.0.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists(Table::OVERRIDES)) {
            return true;
        }

        $this->createTable(Table::OVERRIDES, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'sync' => $this->boolean()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, Table::OVERRIDES, ['elementId'], true);
        $this->addForeignKey(null, Table::OVERRIDES, ['elementId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(Table::OVERRIDES);

        return true;
    }
}
