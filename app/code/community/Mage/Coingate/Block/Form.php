<?php

class Mage_Coingate_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('coingate/form.phtml');

        parent::_construct();
    }
}