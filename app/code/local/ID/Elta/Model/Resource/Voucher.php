<?php

class ID_Elta_Model_Resource_Voucher extends Mage_Core_Model_Resource_Db_Abstract
{
    public function _construct()
    {
        $this->_init('id_elta/voucher', 'entity_id');
    }
}