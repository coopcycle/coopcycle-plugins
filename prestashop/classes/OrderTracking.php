<?php

class CoopCycleOrderTracking extends ObjectModel
{
    public $id_order;
    public $delivery;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'coopcycle_order_tracking',
        'primary' => 'id_order',
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT,    'required' => true, 'validate' => 'isUnsignedId'),
            'delivery' => array('type' => self::TYPE_STRING, 'required' => true),
        ),
    );

    public function __construct($id_order)
    {
        parent::__construct($id_order);

        $this->id_order = $id_order;
    }

    /**
     * @param Order $order
     * @return boolean
     */
    public static function existsFor($order)
    {
        $sql = 'SELECT COUNT(`delivery`)
                FROM `' . _DB_PREFIX_ . bqSQL(self::$definition['table']) . '`
                WHERE id_order = ' . pSQL($order->id);

        PrestaShopLogger::addLog(
            $sql,
            1, null, 'Order', (int) $order->id, true);

        $count = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);

        PrestaShopLogger::addLog(
            sprintf('count = %d', $count),
            1, null, 'Order', (int) $order->id, true);

        return $count !== 0;
    }
}
