<?php

$sql = array();

$sql[_DB_PREFIX_.'coopcycle_cart_time_slot'] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'coopcycle_cart_time_slot` (
  `id_cart` int(11) NOT NULL,
  `time_slot` varchar(64) NOT NULL,
  PRIMARY KEY (`id_cart`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

$sql[_DB_PREFIX_.'coopcycle_order_tracking'] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'coopcycle_order_tracking` (
  `id_order` int(11) NOT NULL,
  `delivery` TEXT NOT NULL,
  PRIMARY KEY (`id_order`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

return $sql;
