<?php

class ID_Elta_Helper_Orders extends Mage_Core_Helper_Abstract
{

	public function _getItemQtys(Mage_Sales_Model_Order $order)
	{
    $qty = array();
    foreach ($order->getAllItems() as $_eachItem) {
      if ($_eachItem->getParentItemId()) {
        $qty[$_eachItem->getParentItemId()] = $_eachItem->getQtyOrdered();
      } else {
        $qty[$_eachItem->getId()] = $_eachItem->getQtyOrdered();
      }
    }
    return $qty;
	}

	public function _saveShipment(Mage_Sales_Model_Order_Shipment $shipment, Mage_Sales_Model_Order $order, $customerEmailComments = '')
	{
    $shipment->getOrder()->setIsInProcess(true);
    $transactionSave = Mage::getModel('core/resource_transaction')
			->addObject($shipment)
			->addObject($order)
			->save();
    $emailSentStatus = $shipment->getData('email_sent');
    if (!$emailSentStatus) {
      $shipment->sendEmail(true, $customerEmailComments);
      $shipment->setEmailSent(true);
    }
    return $this;
	}

	public function _saveOrder(Mage_Sales_Model_Order $order)
	{
	  $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
	  $order->setData('status', Mage_Sales_Model_Order::STATE_COMPLETE);
	  $order->save();
	  return $this;
	}
}