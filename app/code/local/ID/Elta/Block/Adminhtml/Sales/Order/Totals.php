<?php

class ID_Elta_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        $order = $this->getOrder();
        if ($order->getFieldCustomPrice()) {
            $this->addTotalBefore(new Varien_Object(array(
                'code'      => 'elta',
                'value'     => $order->getFieldCustomPrice(),
                'base_value'=> $order->getFieldCustomPrice(),
                'label'     => Mage::helper('elta')->__('Αντικαταβολή (Μόνο για αλλαγές)'),
            )));
        }

        return $this;
    }

}