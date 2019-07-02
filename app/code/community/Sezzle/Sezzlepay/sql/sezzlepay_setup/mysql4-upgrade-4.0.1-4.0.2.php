<?php
$installer = $this;

$installer->startSetup();


$table = $installer->getTable('sales_flat_order');
$installer->getConnection()->addColumn($table, 'sezzle_capture_expiry', "datetime DEFAULT NULL COMMENT 'Sezzle Capture Expiry'");    

$installer->endSetup();
