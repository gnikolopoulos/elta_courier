<?php

$this->startSetup();

$table_voucher = $this->getConnection()
    ->newTable($this->getTable('id_elta/voucher'))
    ->addColumn(
        'entity_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
        ),
        'Entity ID'
    )
    ->addColumn(
        'pod',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'POD No'
    )
    ->addColumn(
        'orderno',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'Order No'
    )
    ->addColumn(
        'created_at',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        array(),
        'POD Creation Time'
    )
    ->addColumn(
        'is_printed',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'nullable'  => false,
        ),
        'Print Status'
    )
    ->setComment('Voucher Table');

$this->getConnection()->createTable($table_voucher);

$this->endSetup();