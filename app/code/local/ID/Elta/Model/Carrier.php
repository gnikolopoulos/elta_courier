<?php

class ID_Elta_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = 'id_elta';

    public function collectRates( Mage_Shipping_Model_Rate_Request $request )
    {
        if (!$this->getConfigData('active')) {
            return false;
        }

        $result = Mage::getModel('shipping/rate_result');

        $result->append($this->_getStandardShippingRate($request));
        if( Mage::app()->getStore()->isAdmin() || Mage::getDesign()->getArea() == 'adminhtml' ) {
            $result->append($this->_getReturnShippingRate($request));
            $result->append($this->_getFreeShippingRate($request));
        }

        return $result;
    }

    protected function _getStandardShippingRate($data)
    {
        $rate = Mage::getModel('shipping/rate_result_method');

        $rate->setCarrier($this->_code);
        /**
         * getConfigData(config_key) returns the configuration value for the
         * carriers/[carrier_code]/[config_key]
         */
        $rate->setCarrierTitle($this->getConfigData('title'));

        $rate->setMethod('standard');
        $rate->setMethodTitle($this->getConfigData('label_standard'));

        if ($data->getAllItems()) {
            foreach ($data->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    if( $data->getPackageValueWithDiscount() >= floatval($this->getConfigData('free')) ) {
                        $rate->setPrice(0);
                    } else {
                        $rate->setPrice($this->getConfigData('price'));
                    }
                } else {
                    if( $data->getPackageValueWithDiscount() >= floatval($this->getConfigData('free')) ) {
                        $rate->setPrice(0);
                    } else {
                        $rate->setPrice($this->getConfigData('price'));
                    }
                }
            }
        }

        $rate->setCost($this->getConfigData('cost'));

        return $rate;
    }

    protected function _getReturnShippingRate($data)
    {
        $rate = Mage::getModel('shipping/rate_result_method');

        $rate->setCarrier($this->_code);
        /**
         * getConfigData(config_key) returns the configuration value for the
         * carriers/[carrier_code]/[config_key]
         */
        $rate->setCarrierTitle($this->getConfigData('title'));

        $rate->setMethod('return');
        $rate->setMethodTitle($this->getConfigData('label_return'));

        $rate->setPrice($this->getConfigData('return_price'));
        $rate->setCost($this->getConfigData('cost'));

        return $rate;
    }

    protected function _getFreeShippingRate($data)
    {
        $rate = Mage::getModel('shipping/rate_result_method');

        $rate->setCarrier($this->_code);
        /**
         * getConfigData(config_key) returns the configuration value for the
         * carriers/[carrier_code]/[config_key]
         */
        $rate->setCarrierTitle($this->getConfigData('title'));

        $rate->setMethod('free');
        $rate->setMethodTitle($this->getConfigData('label_free'));

        $rate->setPrice(0);
        $rate->setCost(0);

        return $rate;
    }

    public function getAllowedMethods()
    {
        return array(
            'standard' => $this->getConfigData('label_standard'),
            'return' => $this->getConfigData('label_return'),
            'free' => $this->getConfigData('label_free'),
        );
    }

    public function isTrackingAvailable()
    {
        return true;
    }

    public function getTrackingInfo($tracking)
    {
        $track = Mage::getModel('shipping/tracking_result_status');
        $track->setUrl('https://www.acscourier.net/el/my-shipments-status?p_p_id=ACSCustomersAreaTrackTrace_WAR_ACSCustomersAreaportlet&p_p_lifecycle=1&p_p_state=normal&p_p_mode=view&p_p_col_id=column-4&p_p_col_pos=1&p_p_col_count=2&_ACSCustomersAreaTrackTrace_WAR_ACSCustomersAreaportlet_javax.portlet.action=trackTrace&generalCode=' . $tracking)
            ->setTracking($tracking)
            ->setCarrierTitle($this->getConfigData('admin_title'));
        return $track;
    }

}