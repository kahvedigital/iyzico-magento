<?php

$installer = $this;
$installer->startSetup();

$installer->run("
	ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `iyzicoinstallment_amount` DECIMAL( 10, 2 )   NULL;
	ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `base_iyzicoinstallment_amount` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/quote_address') . "` ADD  `iyzicoinstallment_amount` DECIMAL( 10, 2 )   NULL;
        ALTER TABLE  `" . $this->getTable('sales/quote_address') . "` ADD  `base_iyzicoinstallment_amount` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `iyzicoinstallment_amount_invoiced` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `base_iyzicoinstallment_amount_invoiced` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/invoice') . "` ADD  `iyzicoinstallment_amount` DECIMAL( 10, 2 )   NULL;
	ALTER TABLE  `" . $this->getTable('sales/invoice') . "` ADD  `base_iyzicoinstallment_amount` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `iyzicoinstallment_amount_refunded` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `base_iyzicoinstallment_amount_refunded` DECIMAL( 10, 2 )  NULL;	
        ALTER TABLE  `" . $this->getTable('sales/creditmemo') . "` ADD  `iyzicoinstallment_amount` DECIMAL( 10, 2 )  NULL;
        ALTER TABLE  `" . $this->getTable('sales/creditmemo') . "` ADD  `base_iyzicoinstallment_amount` DECIMAL( 10, 2 )  NULL;
    ");

$installer->endSetup();