<?php
/*
Debitway Payment Controller

*/

class Debitway_Interac_PaymentController extends Mage_Core_Controller_Front_Action {


	protected $_sendNewOrderEmail = true;
    
    protected $_order = NULL;

    protected $_paymentInst = NULL;
	
	// The redirect action is triggered when someone places an order
	public function redirectAction() {
		
		$session = $this->getCheckout();
		Mage::log($session); 
		$session->setInteracQuoteId($session->getQuoteId());
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($session->getLastRealOrderId());
		$order->addStatusToHistory($order->getStatus(), Mage::helper('debitway')->__('processing the payment')); 
		$order->save();

		$this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','debitway',array('template' => 'debitway/redirect.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
        $session->unsQuoteId();
	}

	
	// The response action is triggered when your gateway sends back a response after processing the customer's payment
	public function responseAction() {

		//for backend notification.
		if($this->getRequest()->isPost()) {

			$identifier = trim(Mage::getStoreConfig('payment/debitway/identifier'));
			$vericode =  trim(Mage::getStoreConfig('payment/debitway/vericode'));
			$website_unique_id =  trim(Mage::getStoreConfig('payment/debitway/website_unique_id'));
			$return_url =  trim(Mage::getStoreConfig('payment/debitway/return_url'));
			
			$transaction_id= $_POST["transaction_id"];
			// function for backend notification
			if($_GET['action']=="backend_notification"){

				if($identifier==$_POST["identifier"]){

					$ch = curl_init();
					$url="https://www.debitway.com/integration/index.php";
					curl_setopt($ch, CURLOPT_URL,$url);
					echo "RECEIVED";
					curl_setopt($ch, CURLOPT_POST, true);

					$post_data = 'identifier='.$identifier.'&vericode='.$vericode.'&transaction_id='.$transaction_id.'&action=verify';
					curl_setopt ($ch, CURLOPT_POSTFIELDS, $post_data);
					curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					$result = curl_exec($ch);
					$output = curl_exec($ch);
					
					curl_close($ch);
					exit(0);
				}
			
			//function of approval
			}else{

				if($identifier==$_POST["identifier"] && $_POST['transaction_result']=="success"){
					
					$ch = curl_init();
					$url="https://www.debitway.com/integration/index.php";
					curl_setopt($ch, CURLOPT_URL,$url);
				
					curl_setopt($ch, CURLOPT_POST, true);

					$post_data = 'identifier='.$identifier.'&vericode='.$vericode.'&transaction_id='.$transaction_id.'&action=verify';
					curl_setopt ($ch, CURLOPT_POSTFIELDS, $post_data);
					curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					$result = curl_exec($ch);
					
					curl_close($ch);

					$output = $this->get_value_from_response($result,'result');
					if($output == "success"){
			              $validated = true;
			        }else{
			            
			            $validated = false;
			           
			        }
				}
				else{

					$validated = false;

				}
			}

			$orderId =$_POST['transaction_id'];
			// Generally sent by gateway
			$error = $_POST['customer_errors_meaning'];
		
				
			if($validated) {
				
				
				//save the transaction id
				$order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
				
				$msg= "Payment";			
				$payment = $order->getPayment();
   				$payment->setTransactionId($transaction_id);
				$payment->addTransaction( Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, $msg );

				
           
				if ($order->canInvoice()) {
	            	$invoice = $order->prepareInvoice();
	            	
	                $invoice->register()->capture(); 
	                Mage::getModel('core/resource_transaction')
	                    ->addObject($invoice)
	                    ->addObject($invoice->getOrder())
	                    ->save();
          		}

				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Gateway has authorized the payment.');
				
				$order->sendNewOrderEmail();
				$order->setEmailSent(true);

					
					
				$order->save();	
				$session = $this->getCheckout();	
				$session->setQuoteId($session->getInteracQuoteId(true));
				$session->getQuote()->setIsActive(false)->save();
				
				$cartHelper = Mage::helper('checkout/cart');
		 

				Mage::getSingleton('core/session')->addSuccess(
		   		Mage::helper('checkout')->__("Transaction id = ".$_POST['transaction_id'])
					);
		        		
				Mage::getSingleton('checkout/session')->unsQuoteId();
				

				
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
				
			}
			else {

				if($result!=null){
					$error = $this->get_value_from_response($result,'errors_meaning');
				}
				
				$session = $this->getCheckout();	
				$session->setQuoteId($session->getInteracQuoteId(true));
				$session->getQuote()->setIsActive(true)->save();


				$order = Mage::getModel('sales/order');
				$order->load($this->getCheckout()->getLastOrderId());		
				$order->cancel();
				$order->addStatusToHistory($order->getStatus(), Mage::helper('debitway')->__('Cancelation of payment:'.$error)); 
				$order->save();

				//save the transaction id
				$order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
				$msg= "Decline";

				
                Mage::getSingleton('core/session')->addError(
           		 Mage::helper('checkout')->__($error)
        			);
		      


			
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage', array('_secure'=>true));
			}
		}
		else
			Mage_Core_Controller_Varien_Action::_redirect('');
	}
	
	// The cancel action is triggered when an order is to be cancelled
	public function cancelAction() {

        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if($order->getId()) {
				// Flag the order as 'cancelled' and save it
				$order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
			}
        }
	}
	/**
	* Get singleton of Checkout Session Model
	*
	* @return Mage_Checkout_Model_Session
	*/
	public function getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}

	private function get_value_from_response($l_str, $l_key) {
        if(strpos($l_str, $l_key."=\"")) {
            $l_substr = substr($l_str, strpos($l_str, $l_key."=\"") + strlen($l_key) + 2);
            return substr($l_substr, 0, strpos($l_substr, "\""));
        }
        else return FALSE;
    }


}