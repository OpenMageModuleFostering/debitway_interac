<?php

 class Debitway_Interac_Model_Service_Quote extends Mage_Sales_Model_Service_Quote
{



	public function submitOrder()
	{
		
		
		$order = parent::submitOrder();
		if($order->getPayment()->getMethodInstance()->getCode()== 'debitway')
		
			$this->_quote->setIsActive(true);
		
		return $order;
	}
} 

?>