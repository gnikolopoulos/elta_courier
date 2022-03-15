<?php

class ID_Elta_Helper_Tracking extends Mage_Core_Helper_Abstract
{

    private $username;
    private $password;
    private $appkey;
    private $api_url;

    private function init()
    {
        $this->username = Mage::getStoreConfig('elta/login/username');
        $this->password = Mage::getStoreConfig('elta/login/password');
        $this->appkey = Mage::getStoreConfig('elta/login/appkey');
        $this->api_url = Mage::getStoreConfig('elta/login/api_url');
    }

    public function _trace($voucher)
    {
        $this->init();

        $xml = array(
            'wpel_user'         => $this->username,
            'wpel_pass'         => $this->password,
            'wpel_code'         => $this->appkey,
            'wpel_vg'           => $voucher,
            'wpel_ref'          => '',
            'wpel_flag'         => '1',
        );
        $soap = new SoapClient(Mage::getModuleDir('', 'ID_Elta') . '/wsdl/PELTT01.wsdl');
        $soap->__setLocation($this->api_url);
        $response = $soap->READ($xml);

        if ($response->st_flag !== 0) {
            return array(
                'checkpoints' => array(),
                'status'      => '',
                'date'        => '',
                'signed'      => '',
            );
        }

        $checkpoints = array();
        $last_status = '';
        $last_update = '';
        foreach($response->web_status as $status) {
            if( $status->web_station == '' ) {
                break;
            }

            $checkpoints[] = (object) array(
                'Shop' => $status->web_station,
                'Status' => $status->web_status_title,
                'StatusDate' => $status->web_date . ' ' . $status->web_time,
            );
            $last_status = $status->web_status_title;
            $last_update = $status->web_date . ' ' . $status->web_time;
        }

        return array(
            'checkpoints' => $checkpoints,
            'status'      => $last_status,
            'date'        => $last_update,
            'signed'      => $response->pod_name ?: '',
        );
    }

}