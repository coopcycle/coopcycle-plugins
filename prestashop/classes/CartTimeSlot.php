<?php

class CoopCycleCartTimeSlot extends ObjectModel
{
    public $id_cart;
    public $time_slot;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'coopcycle_cart_time_slot',
        'primary' => 'id_cart',
        'fields' => array(
            'id_cart'   => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'time_slot' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 64),
        ),
    );

    public function __construct($id_cart)
    {
        parent::__construct($id_cart);

        $this->id_cart = $id_cart;
    }
}
