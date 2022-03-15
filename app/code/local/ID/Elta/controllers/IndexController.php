<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class ID_Elta_IndexController extends Mage_Core_Controller_Front_Action
{

	private $api_url;
	private $appkey;
	private $username;
	private $password;
	private $soap;

	private function init()
	{
		$this->username = Mage::getStoreConfig('elta/login/username');
        $this->password = Mage::getStoreConfig('elta/login/password');
        $this->appkey = Mage::getStoreConfig('elta/login/appkey');
        $this->api_url = Mage::getStoreConfig('elta/login/api_url');

        $this->soap = new SoapClient( $this->api_url );
	}

    public function indexAction()
    {
        $this->init();

        $order_collection = Mage::getModel('sales/order')
					->getCollection()
					->addAttributeToSelect('*')
					->addAttributeToFilter('status', array('in' => array('complete')));

		foreach ($order_collection as $order) {
            if( substr($order->getShippingMethod(), 0, 8) === 'id_elta_' && $order->getTracksCollection()->getFirstItem()->getNumber() != '' ) {
    			$xml = array (
                    'authKey' => $this->auth_key,
                    'voucherNo' => $order->getTracksCollection()->getFirstItem()->getNumber(),
                    'language' => 'el'
                );
                
                $response = $this->soap->TrackAndTrace($xml);
                
                $checkpoints = $response->TrackAndTraceResult->Checkpoints->Checkpoint;

                if( $response->Result == 0 ) {
        			if( $response->TrackAndTraceResult->Status == 'ΠΑΡΑΔΟΜΕΝΟ' ) {
        				echo $order->getIncrementId() . ' Delivered<br />';
        				$order->setStatus("delivered");
        				$order->save();
        			} else {
                        foreach( $checkpoints as $points ) {
                            if( $point->Status == 'Αδυναμία παράδοσης - Άρνηση Παραλαβής' || $point->Status == 'Επιστροφή στον αρχικό αποστολέα' ) {
                                echo $order->getIncrementId() . ' Denied<br />';
                                $order->setStatus("denied");
                                $order->save();

                                break;
                            }
                        }
        			}
                }
            }
		}
    }

}