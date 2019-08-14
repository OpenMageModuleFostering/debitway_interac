<?php
class Debitway_Interac_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'debitway';
	
	protected $_isGateway = false;
	protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
   
	protected $_canRefund               = true;
	protected $_canCapture              = true;
	protected $_canAuthorize            = true;
	protected $_canRefundInvoicePartial = true;

	
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('debitway/payment/redirect', array('_secure' => true));
	}

	 public function refund(Varien_Object $payment, $amount)
    {
    	
    	try{
    
	    	$identifier = trim(Mage::getStoreConfig('payment/debitway/identifier'));
			$vericode =  trim(Mage::getStoreConfig('payment/debitway/vericode'));
			
			
	    	$order = $payment->getOrder()->getOrderId();
	    	$transaction_id  = $payment->getParentTransactionId();
	    	if($transaction_id==null){
	    		Mage::throwException(Mage::helper('payment')->__('null value in transaction id'));
	    	
	    	}
	    	
	 
	    	$comments = "A refund was issued to the customer";
	    	$ch = curl_init();
			$url="https://www.debitway.com/integration/index.php";
			curl_setopt($ch, CURLOPT_URL,$url);
		
			curl_setopt($ch, CURLOPT_POST, true);

			$post_data = 'identifier='.$identifier.'&vericode='.$vericode.'&comments='.$comments.'&transaction_id='.$transaction_id.'&amount='.$amount.'&action=refund';
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			$result = curl_exec($ch);

			curl_close($ch);
			$res = explode('" ',$result);
			for($i=0;$i<count($res);$i++){
				if($res[$i]=='result="success'){
					$success = true;
					break;
				}else
					$success = false;

			}
			if($result == null){
				$success = false;
			}
			if($success==false){
				Mage::throwException(Mage::helper('payment')->__('Problem encountered in Refunding.'));
			}


		}catch (Exception $e) {
    		Mage::throwException(Mage::helper('payment')->__($e));
		}
		

        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }


        return $this;
    }
    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setStatus(self::STATUS_APPROVED)
            ->setLastTransId($payment->getParentTransactionId());
        
        return $this;
    }

}
?>