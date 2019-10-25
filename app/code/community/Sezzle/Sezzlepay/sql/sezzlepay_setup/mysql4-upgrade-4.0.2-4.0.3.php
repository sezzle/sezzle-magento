<?php
$installer = $this;

$installer->startSetup();


$table = $installer->getTable('sales_flat_order');
$installer->getConnection()->addColumn($table, 'is_captured', "int(10) DEFAULT 0 COMMENT 'Sezzle Capture Status'");
$installer->getConnection()->addColumn($table, 'is_refunded', "int(10) DEFAULT 0 COMMENT 'Sezzle Refund Status'");

$installer->endSetup();
