<?php

class ID_Elta_Adminhtml_EltaController extends Mage_Adminhtml_Controller_Action
{

	private $order;

	private $api_url;
	private $username;
	private $password;
	private $appkey;
	private $print_type;

	private function init()
	{
		$this->username = Mage::getStoreConfig('elta/login/username');
		$this->password = Mage::getStoreConfig('elta/login/password');
		$this->appkey = Mage::getStoreConfig('elta/login/appkey');
		$this->api_url = Mage::getStoreConfig('elta/login/api_url');
		$this->print_type = Mage::getStoreConfig('elta/login/print_type');
	}

	public function indexAction()
	{
	    $this->_redirectReferer();
	    Mage::getSingleton('adminhtml/session')->addNotice( $this->__('You cannot access this area directly') );
	    return $this;
	}

	public function createAction($order = null)
	{
		$this->init();

		if( $this->getRequest()->getParam('order') ) {
			$this->order = Mage::getModel("sales/order")->load( $this->getRequest()->getParam('order') );
		} elseif( $this->getRequest()->getParam('order_ids') ) {
			$this->order = Mage::getModel("sales/order")->load( $order );
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Invalid action. You must select at least 1 order to create vouchers for') );
			return false;
		}

		// Get Order parameters
		if( $this->order->canShip() && (substr($this->order->getShippingMethod(), 0, 8) === 'id_elta_') ) {
			$order_data = $this->order->getShippingAddress()->getData();
			if( $this->order->getPayment()->getMethodInstance()->getCode() == 'cashondelivery' ) {
				// Αντικαταβολή
				if( $this->order->getFieldCustomPrice() !== NULL ) {
					$amount = $this->order->getFieldCustomPrice();
				} else {
					$amount = $this->order->getGrandTotal();
				}
			} else {
				$amount = 0;
			}

			try {
				$xml = array(
					'pel_user_code'     => $this->username,
				  'pel_user_pass'     => $this->password,
				  'pel_apost_code'    => $this->appkey,
				  'pel_apost_sub_code'=> '',
				  'pel_user_lang'     => '',
				  'pel_paral_name'    => $this->order->getShippingAddress()->getName(),
				  'pel_paral_address' => trim($order_data['street']),
				  'pel_paral_area'    => $order_data['city'],
				  'pel_paral_tk'      => $order_data['postcode'],
				  'pel_paral_thl_1'   => $order_data['telephone'],
				  'pel_paral_thl_2'   => '',
				  'pel_service'       => '1',
				  'pel_baros'         => '0.5',
				  'pel_temaxia'       => '1',
				  'pel_paral_sxolia'  => ($this->order->getCustomerNote() ? $this->order->getCustomerNote() : ''),
				  'pel_sur_1'         => '',
				  'pel_sur_2'         => '',
				  'pel_sur_3'         => '',
				  'pel_ant_poso1'     => '',
				  'pel_ant_poso2'     => '',
				  'pel_ant_poso3'     => '',
				  'pel_ant_poso4'     => '',
				  'pel_ant_date1'     => '',
				  'pel_ant_date2'     => '',
				  'pel_ant_date3'     => '',
				  'pel_ant_date4'     => '',
				  'pel_asf_poso'      => '',
				  'pel_ant_poso'      => $amount,
				  'pel_ref_no'        => $this->order->getIncrementId(),
				);

				$soap = new SoapClient(Mage::getModuleDir('', 'ID_Elta') . '/wsdl/CREATEAWB.wsdl');
				$soap->__setLocation($this->api_url);
				$response = $soap->READ($xml);

				// Proceed if no errors
				if( $response->st_flag == 0 ) {
					$this->createInvoice();
					$this->createShipment($response->vg_code);

					// Add voucher to Vouchers table
					$voucher = array(
						'created_at'		=> date('d-m-Y H:i:s'),
						'pod'						=> $response->vg_code,
						'orderno'				=> $this->order->getIncrementId(),
						'is_printed'		=> 0,
					);
					Mage::getModel('id_elta/voucher')->setData($voucher)->save();

					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Created voucher for order #%s.Voucher: %s', $this->order->getIncrementId(), $response->vg_code) );
				} else {
					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addError( $this->__('Could not create voucher for order #%s. Error: %s', $this->order->getIncrementId(), $response->st_title) );
				}

			} catch(SoapFault $fault) {
				trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);

				$this->_redirectReferer();
				Mage::getSingleton('adminhtml/session')->addError( $this->__('Could not create voucher for order #%s. Error: %s', $this->order->getIncrementId(), $fault->faultstring) );
			}
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Order #%s cannot be shipped or has already been shipped', $this->order->getIncrementId()) );
		}
		return $this;
	}

	public function massVouchersAction()
	{
		$this->order_arr = $this->getRequest()->getParam('order_ids');
		foreach($this->order_arr as $_order) {
			$this->createAction($_order);
		}
		return $this;
	}

	private function createShipment($voucher)
	{
		if($this->order->canShip()) {
			$customerEmailComments = '';
			// Create shipment and add tracking number
			$shipment = Mage::getModel('sales/service_order', $this->order)->prepareShipment(Mage::helper('elta/orders')->_getItemQtys($this->order));

	    if( $shipment ) {
		    $arrTracking = array(
          'carrier_code' 	=> 'custom',
          'title' 				=> 'ΕΛΤΑ Courier',
          'number' 				=> $voucher,
        );
		    $track = Mage::getModel('sales/order_shipment_track')->addData($arrTracking);
        $shipment->addTrack($track);
        $shipment->register();
       	Mage::helper('elta/orders')->_saveShipment($shipment, $this->order, $customerEmailComments);
        Mage::helper('elta/orders')->_saveOrder($this->order);

	      if( !$shipment->getEmailSent() ) {
	        // Send Tracking data
	        $shipment->sendEmail(true);
	        $shipment->setEmailSent(true);
	      	$shipment->save();
	      }
      	return true;
    	}
		} else {
			return false;
		}
	}

	private function createInvoice()
	{
		if( !$this->order->hasInvoices() && $this->order->canInvoice() ) {
	    	// Prepare
	    	$invoice = Mage::getModel('sales/service_order', $this->order)->prepareInvoice();
	    	// Check that are products to be invoiced
	    	if( $invoice->getTotalQty() ) {
	        	// CAPTURE_OFFLINE since CC and PayPal already have invoices
	        	$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
	        	$invoice->register();
	        	$transactionSave = Mage::getModel('core/resource_transaction')
	          		->addObject($invoice)
	          		->addObject($invoice->getOrder());
	        	$transactionSave->save();
	      	}
  		}
	}

	public function reprintVoucherAction($order = null)
	{
		$this->init();

		if( $this->getRequest()->getParam('order') ) {
			$this->order = Mage::getModel("sales/order")->load( $this->getRequest()->getParam('order') );

			$voucher = $this->order->getTracksCollection()->getFirstItem()->getNumber();
			$xml = array(
			  'pel_user_code'     => $this->username,
			  'pel_user_pass'     => $this->password,
			  'pel_apost_code'    => $this->appkey,
			  'vg_code'           => $voucher,
			  'paper_size'        => $this->print_type,
			);
			$soap = new SoapClient(Mage::getModuleDir('', 'ID_Elta') . '/wsdl/PELB64VG.wsdl');
			$soap->__setLocation($this->api_url);
			$response = $soap->READ($xml);

			if ($response->st_flag == 0) {
				$this->_prepareDownloadResponse($voucher.'.pdf', base64_decode($response->b64_string), 'application/pdf');
			} else {
				$this->_redirectReferer();
				Mage::getSingleton('adminhtml/session')->addError( $this->__('%s', $response->st_title) );
			}
		} elseif( $this->getRequest()->getParam('order_ids') ) {
			$this->order = Mage::getModel("sales/order")->load( $order );
			return $this->order->getTracksCollection()->getFirstItem()->getNumber();
		} elseif( $this->getRequest()->getParam('pod') ) {
			$voucher = $this->getRequest()->getParam('pod');
			$xml = array(
			  'pel_user_code'     => $this->username,
			  'pel_user_pass'     => $this->password,
			  'pel_apost_code'    => $this->appkey,
			  'vg_code'           => $voucher,
			  'paper_size'        => $this->print_type,
			);
			$soap = new SoapClient(Mage::getModuleDir('', 'ID_Elta') . '/wsdl/PELB64VG.wsdl');
			$soap->__setLocation($this->api_url);
			$response = $soap->READ($xml);

			if ($response->st_flag == 0) {
				$this->_prepareDownloadResponse($voucher.'.pdf', base64_decode($response->b64_string), 'application/pdf');
			} else {
				$this->_redirectReferer();
				Mage::getSingleton('adminhtml/session')->addError( $this->__('%s', $response->st_title) );
			}
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Invalid action. You must select at least 1 order to print vouchers from') );
			return false;
		}
	}

	public function deleteVoucherAction($order = null)
	{
		if( $this->getRequest()->getParam('order') ) {
			$this->order = Mage::getModel("sales/order")->load( $this->getRequest()->getParam('order') );

			$pod = $this->order->getTracksCollection()->getFirstItem()->getNumber();
			$voucher = Mage::getModel('id_elta/voucher')->load($pod, 'pod');

			// Delete Shipment
			$shipments = $this->order->getShipmentsCollection();
			foreach ($shipments as $shipment) {
			  $shipment->delete();
			}

			$invoices = $this->order->getInvoiceCollection();
 			foreach ($invoices as $invoice) {
   			$items = $invoice->getAllItems();
        foreach ($items as $i) {
          $i->delete();
        }
   			$invoice->delete();
 			}

			$items = $this->order->getAllVisibleItems();
			foreach($items as $i) {
				$i->setQtyShipped(0);
				$i->setQtyInvoiced(0);
				$i->save();
			}

			//Reset order state
			$this->order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Undo Shipment');
			$this->order->save();

			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Voucher %s deleted', $this->order->getTracksCollection()->getFirstItem()->getNumber()) );

				$voucher->delete();

				return true;
		} elseif( $this->getRequest()->getParam('pod') ) {
			$voucher = Mage::getModel('id_elta/voucher')->load($this->getRequest()->getParam('pod'), 'pod');
			$voucher->delete();

			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Voucher %s deleted', $this->getRequest()->getParam('pod')) );
				return true;
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Invalid action') );
			return false;
		}
	}

	public function vouchersAction()
	{
		$this->_title($this->__('Vouchers'));
    $this->loadLayout();
    $this->_setActiveMenu('elta/voucher');
    $this->_addContent($this->getLayout()->createBlock('id_elta/adminhtml_voucher'));
    $this->renderLayout();
	}

	public function grid_voucherAction()
  {
    $this->loadLayout();
    $this->getResponse()->setBody(
  	  $this->getLayout()->createBlock('id_elta/adminhtml_voucher_grid')->toHtml()
    );
  }

  protected function _isAllowed() {
    return Mage::getSingleton('admin/session')->isAllowed('admin/elta');
	}

}
