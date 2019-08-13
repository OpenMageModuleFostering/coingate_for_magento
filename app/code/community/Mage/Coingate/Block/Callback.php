<?php

class Mage_Coingate_Block_Callback extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $coingate = Mage::getModel('coingate/CoingateFactory');

        return $coingate->validateCallback();
    }
}
