<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateNursingSchedule extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        // 1. 人员表
        $this->table('staff')
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('is_night_group', 'boolean', ['default' => 0, 'comment' => '是否属于夜班组'])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->create();

        // 2. 排班记录表
        $this->table('schedules')
            ->addColumn('staff_id', 'integer')
            ->addColumn('work_date', 'date')
            ->addColumn('shift_name', 'string', ['limit' => 20])
            ->addIndex(['work_date', 'staff_id'], ['unique' => true])
            ->create();

        // 3. 需求配置表 (申请休息等)
        $this->table('staff_requests')
            ->addColumn('staff_id', 'integer')
            ->addColumn('request_date', 'date')
            ->addColumn('shift_name', 'string', ['limit' => 20]) // 如 '休'
            ->create();
    }
}
