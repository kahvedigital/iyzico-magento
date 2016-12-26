<?php

$installer = $this;
$installer->startSetup();

$installer->run("
                CREATE TABLE IF NOT EXISTS  `{$this->getTable('iyzico_checkout_form_transaction_api_log')}` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `method_type` tinyint(2) NOT NULL,
          `order_increment_id` varchar(255) NOT NULL,
          `request_data` longtext NOT NULL,
          `response_data` longtext NOT NULL,
          `status` varchar(255) NOT NULL,
          `created` datetime NOT NULL,
          `modified` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
    ");
$installer->run("
                CREATE TABLE IF NOT EXISTS  `{$this->getTable('iyzico_card_save_log')}` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `customer_id` varchar(255) NOT NULL,
         `carduserkey` varchar(255) NOT NULL,
		 `apikey` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
    ");

$installer->endSetup();