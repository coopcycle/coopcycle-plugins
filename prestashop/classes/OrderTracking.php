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
            'id_cart'   => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'delivery' => array('type' => self::TYPE_STRING, 'required' => true),
        ),
    );

    public function __construct($id_order)
    {
        parent::__construct($id_order);

        $this->id_order = $id_order;
    }
}
