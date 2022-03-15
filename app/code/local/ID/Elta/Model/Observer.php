<?php

class ID_Elta_Model_Observer
{

	public function addButtonVoucher($observer)
	{
	    $container = $observer->getBlock();
	    $order = Mage::app()->getRequest()->getParams();

	    if( $container instanceof Mage_Adminhtml_Block_Sales_Order_View ) {
	    	$order_obj = Mage::getModel('sales/order')->load($order['order_id']);
	    	if( !$order_obj->isCanceled() && $order_obj->canShip() && (substr($order_obj->getShippingMethod(), 0, 8) === 'id_elta_') ) {
		        $data = array(
		            'label'     => Mage::helper('elta')->__('Create Voucher'),
		            'class'     => 'go',
		            'onclick'   => 'setLocation(\''  . Mage::helper('adminhtml')->getUrl('*/elta/create', array('order' => $order['order_id'])) . '\')',
		        );
		        $container->addButton('create_voucher', $data);

		        /*
		         * Hide Ship and Invoice buttons
		         */
		        $container->removeButton('order_ship');
		        $container->removeButton('order_invoice');
		    }

		    if( !$order_obj->isCanceled() && $order_obj->getStatus() == 'complete' && (substr($order_obj->getShippingMethod(), 0, 8) === 'id_elta_') ) {
		        $data = array(
		            'label'     => Mage::helper('elta')->__('Print Voucher'),
		            'class'     => 'go',
		            'onclick'   => 'setLocation(\''.Mage::helper('adminhtml')->getUrl('*/elta/reprintVoucher', array('order' => $order['order_id'])) . '\')',
		        );
		        $container->addButton('print_voucher', $data);

		        $data = array(
		            'label'     => Mage::helper('elta')->__('Delete Voucher'),
		            'class'     => 'go',
		            'onclick'	=> "confirmSetLocation('".Mage::helper('elta')->__('Are you sure you want to delete this voucher?')."', '".Mage::helper('adminhtml')->getUrl('*/elta/deleteVoucher', array('order' => $order['order_id']))."')"
		        );
		        $container->addButton('delete_voucher', $data);
		    }
	    }

	    return $this;
	}

	public function addActions($observer)
	{
		$block = $observer->getEvent()->getBlock();
	    if(get_class($block) =='Mage_Adminhtml_Block_Widget_Grid_Massaction' && $block->getRequest()->getControllerName() == 'sales_order')
	    {
	      $block->addItem('createvouchers', array(
	        'label' => Mage::helper('elta')->__('Create Vouchers'),
	        'url' => Mage::app()->getStore()->getUrl('*/elta/massVouchers'),
	      ));
	    }

	  return $this;
	}

	public function saveCustomData($event)
	{
		$quote = $event->getSession()->getQuote();
		$quote->setData('field_custom_price', $event->getRequestModel()->getPost('field_custom_price'));

		return $this;
	}

}