<?php
$installer = $this;

$installer->startSetup();


$table = $installer->getTable('sales_flat_order_payment');
$installer->getConnection()->addColumn($table, "sezzle_reference_id", "varchar(255) DEFAULT NULL COMMENT 'Sezzle Reference ID'");

$installer->endSetup();
