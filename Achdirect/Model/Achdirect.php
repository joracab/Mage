<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Mage
 * @package    Mage_Paygate
 * @copyright  Copyright (c) 2004-2007 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @author     Sreeprakash.N. <sree@schogini.com>
 * @copyright  Copyright (c) 2008 Schogini Systems (http://schogini.in)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Achdirect_Model_Achdirect extends Mage_Payment_Model_Method_Cc
{

    const CGI_URL = 'https://staging.linkpt.net:1129/LSGSXML';

    const REQUEST_METHOD_CC     = 'cc';
    const REQUEST_METHOD_ECHECK = 'eft';

    const CC_REQUEST_TYPE_AUTH_CAPTURE 			= '10';
    const CC_REQUEST_TYPE_AUTH_ONLY    			= '11';
    const CC_REQUEST_TYPE_CAPTURE_ONLY 			= '12';
    const CC_REQUEST_TYPE_CREDIT       			= '13';
    const CC_REQUEST_TYPE_VOID         			= '14';
    const CC_REQUEST_TYPE_PRIOR_AUTH_CAPTURE 	= '12';
	
    const ECHECK_REQUEST_TYPE_AUTH_CAPTURE 			= '20';
    const ECHECK_REQUEST_TYPE_AUTH_ONLY    			= '21';
    const ECHECK_REQUEST_TYPE_CAPTURE_ONLY 			= '22';
    const ECHECK_REQUEST_TYPE_CREDIT       			= '23';
    const ECHECK_REQUEST_TYPE_VOID         			= '24';
    const ECHECK_REQUEST_TYPE_PRIOR_AUTH_CAPTURE 	= '22';
	
    const RESPONSE_CODE_APPROVED = 'A';
    const RESPONSE_CODE_DECLINED = 'U';
    const RESPONSE_CODE_ERROR    = 'E';
    const RESPONSE_CODE_HELD     = 'D';

    protected $_code  			= 'achdirect';
	protected $_formBlockType 	= 'achdirect/both_form';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc 				= true;

	public function assignData($data)
    {        
		if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
		parent::assignData($data);

        $info = $this->getInfoInstance();
		
		// type of transactionc cc or eft
        $info->setAchdirectType($data->getAchdirectType());
		
		// eft fields
		$info->setAchdirectTransitRoutingNumber($data->getAba());
		$info->setAchdirectAccountNumber($data->getAccountNumber());
		$info->setAchdirectAccountType($data->getAccountType());
		$info->setAchdirectCheckNo($data->getCheckNumber());

        return $this;
    }

	public function prepareSave()
    {
		$info = $this->getInfoInstance();
		if ($this->_canSaveCc) {
			$info->setCcCidEnc($info->encrypt($info->getCcCid()));
		}
		parent::prepareSave();
		
        return $this;
    }
	
	public function validate() 
	{
		$errorMsg = '';
		$info = $this->getInfoInstance();
		$achdirectType = $info->getAchdirectType();
		
		if ($achdirectType == 'eft') {
			// This is actually done in the Mage_Payment_Model_Method_Abstract class
			// which is the parent parent class of this class. Since, we cannot call it
			// directly we copied the code here
			 if ($info instanceof Mage_Sales_Model_Order_Payment) {
				 $billingCountry = $info->getOrder()->getBillingAddress()->getCountryId();
			 } else {
				 $billingCountry = $info->getQuote()->getBillingAddress()->getCountryId();
			 }
			 if (!$this->canUseForCountry($billingCountry)) {
				 Mage::throwException($this->_getHelper()->__('Selected payment type is not allowed for billing country.'));
			 }
		 
			// payment method selected is EFT - do EFT validation
			$aba     = $info->getAchdirectTransitRoutingNumber();
			$accno   = $info->getAchdirectAccountNumber();
			$acctype = $info->getAchdirectAccountType();
			$chkno 	 = $info->getAchdirectCheckNo();
			
			/*if (!preg_match('/^\d{9,}$/', $aba)) {
				$errorMsg = $this->_getHelper()->__('Invalid routing number');
			} else if (!preg_match('/^\d{17,}$/', $accno)) {
				$errorMsg = $this->_getHelper()->__('Invalid account number');
			} else if (!in_array($acctype, array('S', 'C'))) {
				$errorMsg = $this->_getHelper()->__('Invalid account type');
			} else if (!preg_match('/^\d{10,}$/', $chkno)) {
				$errorMsg = $this->_getHelper()->__('Invalid check number');
			}*/
		} else if ($achdirectType == 'cc') {
			// payment method selected is CC - do CC validation
			parent::validate();
		} else {
			// no payment method selected
			$errorMsg = $this->_getHelper()->__('Please select a payment method');
		}
		
		if($errorMsg){
            Mage::throwException($errorMsg);
        }
		return $this;
	}
	
    /**
     * Send authorize request to gateway
     *
     * @param   Varien_Object $payment
     * @param   decimal $amount
     * @return  Mage_Achdirect_Model_Achdirect
     */
    public function authorize(Varien_Object $payment, $amount)
    {
	    $error = '';
		
		// set the transaction type
		if ($payment->getAchdirectType() == self::REQUEST_METHOD_ECHECK) {
			$payment->setAnetTransType(self::ECHECK_REQUEST_TYPE_AUTH_ONLY);
		} else {
			$payment->setAnetTransType(self::CC_REQUEST_TYPE_AUTH_ONLY);
		}

        if ($amount>0) {
            $payment->setAmount($amount);

			$this->logit('Calling _buildRequest', array());
            $request = $this->_buildRequest($payment);
			$this->logit('buildrequest call returned', $request);

            $result  = $this->_postRequest($request);
			$this->logit('postRequest call returned', $result);
			
            $payment->setCcApproval($result->getApprovalCode())
                ->setLastTransId($result->getTransactionId())
                ->setCcTransId($result->getTransactionId())
                ->setCcAvsStatus($result->getAvsResultCode())
                ->setCcCidStatus($result->getCardCodeResponseCode());

            $code = $result->getResponseReasonCode();
            $text = $result->getResponseReasonText();
			
            switch ($result->getResponseCode()) {
                case self::RESPONSE_CODE_APPROVED:
                    $payment->setStatus(self::STATUS_APPROVED);
					
					// set the response with the order - just for the records
					if (!$order = $payment->getOrder()) {
						$order = $payment->getQuote();
					}
					$order->addStatusToHistory(
						$order->getStatus(),
						urldecode($code . ' - ' . $text) . ' at ACH Direct',
						Mage::helper('paygate')->__($code . ' - ' . $text . ' from ACH Direct')
					);
                    break;

                case self::RESPONSE_CODE_DECLINED:
                    $error = Mage::helper('paygate')->__('Payment transaction has been declined. ' . "$code - $text");
					
					// set the response with the order - just for the records
					if (!$order = $payment->getOrder()) {
						$order = $payment->getQuote();
					}
					$order->addStatusToHistory(
						$order->getStatus(),
						urldecode($code . ' - ' . $text) . ' at ACH Direct',
						Mage::helper('paygate')->__($code . ' - ' . $text . ' from ACH Direct')
					);
                    break;

                default:
                    $error = Mage::helper('paygate')->__('Payment authorization error. ' . "$code - $text");
					
					// set the response with the order - just for the records
					if (!$order = $payment->getOrder()) {
						$order = $payment->getQuote();
					}
					$order->addStatusToHistory(
						$order->getStatus(),
						urldecode($code . ' - ' . $text) . ' at ACH Direct',
						Mage::helper('paygate')->__($code . ' - ' . $text . ' from ACH Direct')
					);
                    break;
            }
        } else {
            $error = Mage::helper('paygate')->__('Invalid amount for authorization.');
        }

        if ($error != '') {
            Mage::throwException($error);
        }
        
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
		$error = '';
		
		// set the transaction type
		if ($payment->getAchdirectType() == self::REQUEST_METHOD_ECHECK) {
			if ($payment->getCcTransId() && $payment->getStatus() != self::STATUS_VOID) {
				$payment->setAnetTransType(self::ECHECK_REQUEST_TYPE_PRIOR_AUTH_CAPTURE);
			} else {
				$payment->setAnetTransType(self::ECHECK_REQUEST_TYPE_AUTH_CAPTURE);
			}		
		} else {
			if ($payment->getCcTransId() && $payment->getStatus() != self::STATUS_VOID) {
				$payment->setAnetTransType(self::CC_REQUEST_TYPE_PRIOR_AUTH_CAPTURE); // Sree do only capture
			} else {
				$payment->setAnetTransType(self::CC_REQUEST_TYPE_AUTH_CAPTURE);    // Sree do full SALE
			}
		}
		
		// do the transaction
		$payment->setAmount($amount);
		$request = $this->_buildRequest($payment);
		$result  = $this->_postRequest($request);

		// process the response
		if ($result->getResponseCode() == self::RESPONSE_CODE_APPROVED) {
			$payment->setStatus(self::STATUS_APPROVED);
			$payment->setLastTransId($result->getTransactionId());
		}
		else {
			if ($result->getResponseReasonText()) {
				$error = $result->getResponseReasonText();
			}
			else {
				$error = Mage::helper('paygate')->__('Error in capturing the payment');
			}
		}

		if ($error != '') {
			Mage::throwException($error);
		}

		return $this;
    }

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canVoid(Varien_Object $payment)
    {
		return $this->_canVoid;
    }
	public function void(Varien_Object $payment)
	{
		$error = false;
		
		// set the transaction type
		if ($payment->getAchdirectType() == self::REQUEST_METHOD_ECHECK) {
			$payment->setAnetTransType(self::ECHECK_REQUEST_TYPE_VOID);
		} else {
			$payment->setAnetTransType(self::CC_REQUEST_TYPE_VOID);
		}
		
		if ($payment->getVoidTransactionId() && $payment->getAmount() > 0) {
			// do the transaction
			$request = $this->_buildRequest($payment);
			$request->setXTransId($payment->getVoidTransactionId());
			$result = $this->_postRequest($request);
			
			// process the response
			if ($result->getResponseCode()==self::RESPONSE_CODE_APPROVED) {
				$payment->setStatus(self::STATUS_VOID);
			} else {
				$errorMsg = $result->getResponseReasonText();
				$error = true;
			}

		} else {
			$errorMsg = Mage::helper('paygate')->__('Error in voiding the payment');
			$error = true;
		}
		
		if ($error !== false) {
			Mage::throwException($errorMsg);
		}
		return $this;
	}

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
		return $this->_canRefund;
    }
	public function refund(Varien_Object $payment, $amount)
	{
		$error = false;
		
		// set the transaction type
		if ($payment->getAchdirectType() == self::REQUEST_METHOD_ECHECK) {
			$payment->setAnetTransType(self::ECHECK_REQUEST_TYPE_CREDIT);
		} else {
			$payment->setAnetTransType(self::CC_REQUEST_TYPE_CREDIT);
		}
		
		if ($payment->getRefundTransactionId() && $amount>0) {
			// do the transaction
			$request = $this->_buildRequest($payment);
			$request->setXTransId($payment->getRefundTransactionId());
			$request->setXAmount($amount);
			$result = $this->_postRequest($request);
			
			// process the response
			if ($result->getResponseCode()==self::RESPONSE_CODE_APPROVED) {
				$payment->setStatus(self::STATUS_SUCCESS);
			} else {
				$errorMsg = $result->getResponseReasonText();
				$error = true;
			}
		} else {
			$errorMsg = Mage::helper('paygate')->__('Error in voiding the payment');
			$error = true;
		}

		if ($error !== false) {
			Mage::throwException($errorMsg);
		}
		return $this;
	}

    /**
     * Prepare request to gateway
     *
     * @link   http://www.authorize.net/support/AIM_guide.pdf
     * @param  Mage_Sales_Model_Document $order
     * @return unknown
     */
    protected function _buildRequest(Varien_Object $payment)
    {
	    $order = $payment->getOrder();

		// if payment method is not set we assume credit card method by default
        if (!$payment->getAchdirectType()) {
            $payment->setAchdirectType(self::REQUEST_METHOD_CC);
        }
		
		// test mode
        $request = Mage::getModel('achdirect/achdirect_request');
		$request->setXTestRequest($this->getConfigData('test') ? 'TRUE' : 'FALSE');

		// set the transaction details
        $request->setXLogin($this->getConfigData('login'))
            ->setXTranKey($this->getConfigData('trans_key'))
            ->setXType($payment->getAnetTransType())
            ->setXMethod($payment->getAchdirectType());

		// amount
        if ($payment->getAmount()) {
            $request->setXAmount($payment->getAmount(),2);
            $request->setXCurrencyCode($order->getBaseCurrencyCode());
        }
        
		// depending on the method of payment (cc or eft) populate the data
        switch ($payment->getAchdirectType()) {
            case self::REQUEST_METHOD_CC:
				if($payment->getCcNumber()){
					$ccnum = $payment->getCcNumber();
				} else {
					$ccnum = Mage::helper('core')->decrypt($payment->getCcNumberEnc());
				}
				$request->setXCardNum($ccnum)
					->setXExpDate(sprintf('%02d-%04d', $payment->getCcExpMonth(), $payment->getCcExpYear()))
					->setXCardCode($payment->getCcCid())
					->setXCardType($payment->getCcType());				
                break;

            case self::REQUEST_METHOD_ECHECK:
				$request->setXAba($payment->getAchdirectTransitRoutingNumber())
					->setXAccountNumber($payment->getAchdirectAccountNumber())
					->setXAccountType($payment->getAchdirectAccountType())
					->setXCheckNumber($payment->getAchdirectCheckNo());			
                break;
        }		
		
		// depending of the type of transaction populate data
        switch ($payment->getAnetTransType()) {
            case self::CC_REQUEST_TYPE_CREDIT:
            case self::CC_REQUEST_TYPE_VOID:
            case self::CC_REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
            case self::ECHECK_REQUEST_TYPE_CREDIT:
            case self::ECHECK_REQUEST_TYPE_VOID:
            case self::ECHECK_REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
                $request->setXTransId($payment->getCcTransId());
				$request->setXAuthCode($payment->getCcApproval());
                break;

            case self::CC_REQUEST_TYPE_CAPTURE_ONLY:
            case self::ECHECK_REQUEST_TYPE_CAPTURE_ONLY:
                $request->setXAuthCode($payment->getCcApproval());
                break;
        }

		if (!empty($order)) {
			$request->setXInvoiceNum($order->getIncrementId());
			
            $billing = $order->getBillingAddress();
			$this->logit('Inside _buildRequest CCC order->getBillingAddress', get_class($billing));
			$this->logit('Inside _buildRequest CCC order->getBillingAddress', get_class_methods(get_class($billing)));
            if (!empty($billing)) {
				$email = $billing->getEmail();
				if(!$email)$email = $order->getBillingAddress()->getEmail();
				if(!$email)$email = $order->getCustomerEmail();

                $request->setXFirstName($billing->getFirstname())
                    ->setXLastName($billing->getLastname())
                    ->setXCompany($billing->getCompany())
                    ->setXAddress($billing->getStreet(1))
                    ->setXCity($billing->getCity())
                    ->setXState($billing->getRegion())
                    ->setXZip($billing->getPostcode())
                    ->setXCountry($billing->getCountry())
                    ->setXPhone($billing->getTelephone())
                    ->setXFax($billing->getFax())
                    ->setXCustId($billing->getCustomerId())
                    ->setXCustomerIp($order->getRemoteIp())
                    ->setXCustomerTaxId($billing->getTaxId())
                    ->setXEmail($email)  //Sree 17Nov2008
                    ->setXEmailCustomer($this->getConfigData('email_customer'))
                    ->setXMerchantEmail($this->getConfigData('merchant_email'));
            }
			
            $shipping = $order->getShippingAddress();
			$this->logit('Inside _buildRequest DDD shipping = order->getShippingAddress()', get_class($shipping));
			if(!$shipping)$shipping = $billing;
            if (!empty($shipping)) {
                $request->setXShipToFirstName($shipping->getFirstname())
                    ->setXShipToLastName($shipping->getLastname())
                    ->setXShipToCompany($shipping->getCompany())
                    ->setXShipToAddress($shipping->getStreet(1))
                    ->setXShipToCity($shipping->getCity())
                    ->setXShipToState($shipping->getRegion())
                    ->setXShipToZip($shipping->getPostcode())
                    ->setXShipToCountry($shipping->getCountry());
            }

            $request->setXPoNum($payment->getPoNumber())
                ->setXTax($shipping->getTaxAmount())
                ->setXFreight($shipping->getShippingAmount());
        }
		
        return $request;
    }

    protected function _postRequest(Varien_Object $request)
    {
		$result = Mage::getModel('achdirect/achdirect_result');
		$m 		= $request->getData();
		$this->logit("_postRequest m array", array('m' => $m));

		// Pre-Build Returned results
		$r = array (
		    0 => '1',
		    1 => '1',
		    2 => '1',
		    3 => '(TESTMODE) This transaction has been approved.',
		    4 => '000000',
		    5 => 'P',
		    6 => '0',
		    7 => '100000018',
		    8 => '',
		    9 => '2704.99',
		    10 => 'CC',
		    11 => 'auth_only',
		    12 => '',
		    13 => 'Sreeprakash',
		    14 => 'N.',
		    15 => 'Schogini',
		    16 => 'XYZ',
		    17 => 'City',
		    18 => 'Idaho',
		    19 => '695038',
		    20 => 'US',
		    21 => '1234567890',
		    22 => '',
		    23 => '',
		    24 => 'Sreeprakash',
		    25 => 'N.',
		    26 => 'Schogini',
		    27 => 'XYZ',
		    28 => 'City',
		    29 => 'Idaho',
		    30 => '695038',
		    31 => 'US',
		    32 => '',
		    33 => '',
		    34 => '',
		    35 => '',
		    36 => '',
		    37 => '382065EC3B4C2F5CDC424A730393D2DF',
		    38 => '',
		    39 => '',
		    40 => '',
		    41 => '',
		    42 => '',
		    43 => '',
		    44 => '',
		    45 => '',
		    46 => '',
		    47 => '',
		    48 => '',
		    49 => '',
		    50 => '',
		    51 => '',
		    52 => '',
		    53 => '',
		    54 => '',
		    55 => '',
		    56 => '',
		    57 => '',
		    58 => '',
		    59 => '',
		    60 => '',
		    61 => '',
		    62 => '',
		    63 => '',
		    64 => '',
		    65 => '',
		    66 => '',
		    67 => '',
		  );

    	// Replace the values from Magento 
		$r[7]  = $m['x_invoice_num']; //InvoiceNumber
	    $r[8]  = ''; //Description
	    $r[9]  = $m['x_amount']; //Amount
	    $r[10] = $m['x_method']; //Method = CC
	    $r[11] = $m['x_type']; //TransactionType
	    $r[12] = $m['x_cust_id']; //CustomerId
	    $r[13] = $m['x_first_name']; 
	    $r[14] = $m['x_last_name'];
	    $r[15] = $m['x_company'];
	    $r[16] = $m['x_address'];
	    $r[17] = $m['x_city'];
	    $r[18] = $m['x_state'];
	    $r[19] = $m['x_zip'];
	    $r[20] = $m['x_country'];
	    $r[21] = $m['x_phone'];
	    $r[22] = $m['x_fax'];
	    $r[23] = '';
        //no shipping

        $m['x_ship_to_first_name'] 	= !isset($m['x_ship_to_first_name'])?$m['x_first_name']:$m['x_ship_to_first_name'];
		$m['x_ship_to_first_name'] 	= !isset($m['x_ship_to_first_name'])?$m['x_first_name']:$m['x_ship_to_first_name'];
		$m['x_ship_to_last_name'] 	= !isset($m['x_ship_to_last_name'])?$m['x_last_name']:$m['x_ship_to_last_name'];
		$m['x_ship_to_company'] 	= !isset($m['x_ship_to_company'])?$m['x_company']:$m['x_ship_to_company'];
		$m['x_ship_to_address'] 	= !isset($m['x_ship_to_address'])?$m['x_address']:$m['x_ship_to_address'];
		$m['x_ship_to_city'] 		= !isset($m['x_ship_to_city'])?$m['x_city']:$m['x_ship_to_city'];
		$m['x_ship_to_state'] 		= !isset($m['x_ship_to_state'])?$m['x_state']:$m['x_ship_to_state'];
		$m['x_ship_to_zip'] 		= !isset($m['x_ship_to_zip'])?$m['x_zip']:$m['x_ship_to_zip'];
		$m['x_ship_to_country'] 	= !isset($m['x_ship_to_country'])?$m['x_country']:$m['x_ship_to_country'];

	    $r[24] = $m['x_ship_to_first_name'];
	    $r[25] = $m['x_ship_to_last_name'];
	    $r[26] = $m['x_ship_to_company'];
	    $r[27] = $m['x_ship_to_address'];
	    $r[28] = $m['x_ship_to_city'];
	    $r[29] = $m['x_ship_to_state'];
	    $r[30] = $m['x_ship_to_zip'];
	    $r[31] = $m['x_ship_to_country'];

	    //Dummy Replace the values from ACHDirect 
	    $r[0]  = '1';  // response_code
	    $r[1]  = '1';  // ResponseSubcode
	    $r[2]  = '1';  // ResponseReasonCode
	    $r[3]  = '(TESTMODE2) This transaction has been approved.'; //ResponseReasonText
	    $r[4]  = '000000'; //ApprovalCode
	    $r[5]  = 'P'; //AvsResultCode
	    $r[6]  = '0'; //TransactionId
	    $r[37] = '382065EC3B4C2F5CDC424A730393D2DF'; //Md5Hash
	    $r[39] = ''; //CardCodeResponse

		// Add ACHDirect Here
		$rr = $this->_achdirectapi($m);
		$this->logit("_achdirectapi call returned back", array('rr' => $rr));

		// Replace the values from ACHDirect 
		$r[0]  = $rr['response_code'];
		$r[1]  = $rr['response_subcode'];
		$r[2]  = $rr['response_reason_code'];
		$r[3]  = $rr['response_reason_text']; //'(TESTMODE2) This transaction has been approved.'; //ResponseReasonText
		$r[4]  = $rr['approval_code']; //'000000'; //ApprovalCode
		$r[5]  = $rr['avs_result_code']; //'P'; //AvsResultCode
		$r[6]  = $rr['transaction_id']; //'0'; //TransactionId
		$r[37] = $rr['md5_hash'];
		$r[39] = $rr['card_code_response'];
		$this->logit("after r array loaded with rr", array('r' => $r));

       if ($r) {
			$this->logit("setting", '');
			$result->setResponseCode($r[0]);

			$this->logit("setting 2", '');
			$result->setResponseSubcode($r[1]);
			
			$this->logit("setting 3", '');
			$result->setResponseReasonCode($r[2]);
			
			$this->logit("setting 4", '');
			$result->setResponseReasonText($r[3]);
			
			$this->logit("setting 5", '');
			$result->setApprovalCode($r[4]);
			
			$this->logit("setting 6", '');
			$result->setAvsResultCode($r[5]);
			
			$this->logit("setting 7", '');
			$result->setTransactionId($r[6]);
			
			$this->logit("setting 8", '');
			$result->setInvoiceNumber($r[7]);
			
			$this->logit("setting 9", '');
			$result->setDescription($r[8]);
			
			$this->logit("setting 10", '');
			$result->setAmount($r[9]);
			
			$this->logit("setting 11", '');
			$result->setMethod($r[10]);
			
			$this->logit("setting 12", '');
			$result->setTransactionType($r[11]);
			
			$this->logit("setting 13", '');
			$result->setCustomerId($r[12]);
			
			$this->logit("setting 14", '');
			$result->setMd5Hash($r[37]);
			
			$this->logit("setting 15", '');
			$result->setCardCodeResponseCode($r[39]);
			
			$this->logit("setting 16", '');
        } else {
             Mage::throwException(Mage::helper('paygate')->__('Error in payment gateway'));
        }
        return $result;
    }
	
	function _achdirectapi($m)
	{
		// Login Details
	    $merchant_id  		= $this->getConfigData('login');
		$merchant_passwd 	= Mage::helper('core')->decrypt($this->getConfigData('password'));

		// depending on the transaction type prepare the data to be sent
		switch ($m['x_method']) {
			case self::REQUEST_METHOD_CC:
				switch ($m['x_type']) {
					case self::CC_REQUEST_TYPE_VOID:
					case self::CC_REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
						$input = array (
						  'pg_transaction_type' 				=> $m['x_type'],
						  'pg_merchant_id' 						=> $merchant_id,
						  'pg_password' 						=> $merchant_passwd,
						  'pg_original_trace_number' 			=> $m['x_auth_code'],
						  'pg_original_authorization_code' 		=> $m['x_trans_id']
						);
						break;
						
					case self::CC_REQUEST_TYPE_CREDIT:
						if (!isset($m['x_card_type'])) {
							Mage::throwException(Mage::helper('paygate')->__('Need CC information to refund'));
						} else {
							// Map the card type code to the card type
							$card_types_arr = array('AE' => 'American Express', 'VI' => 'Visa', 'MC' => 'MasterCard');		
							$card_type 		= $card_types_arr[$m['x_card_type']];				
							
							$input = array (
							  'pg_transaction_type' 				=> $m['x_type'],
							  'pg_merchant_id' 						=> $merchant_id,
							  'pg_password' 						=> $merchant_passwd,
							  'pg_original_trace_number' 			=> $m['x_auth_code'],
							  'pg_original_authorization_code' 		=> $m['x_trans_id'],
							  'pg_total_amount' 					=> $m['x_amount'],
							  'ecom_billto_postal_name_first' 		=> $m['x_first_name'],
							  'ecom_billto_postal_name_last' 		=> $m['x_last_name'],
							  'ecom_payment_card_type' 				=> $card_type,
							  'ecom_payment_card_number' 			=> $m['x_card_num'],
							  'ecom_payment_card_expdate_month' 	=> substr($m['x_exp_date'],0,2),
							  'ecom_payment_card_expdate_year' 		=> substr($m['x_exp_date'],-4),
							  'ecom_billto_postal_city' 			=> $m['x_city'],
							  'ecom_billto_postal_street_line1' 	=> $m['x_address'],
							  'ecom_billto_postal_stateprov' 		=> $m['x_state'],
							  'ecom_billto_postal_postalcode' 		=> $m['x_zip'],
							  'ecom_billto_postal_countrycode' 		=> $m['x_country'],
							  'ecom_billto_telecom_phone_number' 	=> $m['x_phone'],
							  'ecom_billto_online_email' 			=> $m['x_email']
							);
						}
						break;
						
					case self::CC_REQUEST_TYPE_AUTH_ONLY:
					case self::CC_REQUEST_TYPE_AUTH_CAPTURE:
						// create the AVS method string from the configuration
						// Response will also be in a similar format
						// If X1X2X3X4X5 is either the request or response for AVS then this is how to intepret it
						// 0: Do not check, 1: Check but, don't decline, 2: Check and decline, 3: Check passed, 4: Check failed
						// X1 = Credit Card Account/Zipcode Check
						// X2 = Credit Card Account/Street Number Check
						// X3 = State/Zipcode Check
						// X4 = State/Area Code Check
						// X5 = Anonymous Email Check
						// Examples:
						// #1
						// Request (avs method): 22000
						// Response: 34000
						// Transaction will be declined because the CC street number check failed
						// #2
						// Request (avs method): 11000
						// Response: 34000
						// Transaction will not be declined because CC street check failed, but it was “check only” avs method
						$x1 = (int)$this->getConfigData('avs_zipcode') + 0;
						$x2 = (int)$this->getConfigData('avs_streetnum') + 0;
						$x3 = (int)$this->getConfigData('avs_state_zipcode') + 0;
						$x4 = (int)$this->getConfigData('avs_state_areacode') + 0;
						$x5 = (int)$this->getConfigData('avs_email') + 0;
						$avs_method = $x1 . $x2 . $x3 . $x4 . $x5;

						// Map the card type code to the card type
						$card_types_arr = array('AE' => 'American Express', 'VI' => 'Visa', 'MC' => 'MasterCard');		
						$card_type = $card_types_arr[$m['x_card_type']];
						
						// Prepare the data to be sent
						$input = array (
						  'pg_total_amount' 					=> $m['x_amount'],
						  'ecom_billto_postal_name_first' 		=> $m['x_first_name'],
						  'ecom_billto_postal_name_last' 		=> $m['x_last_name'],
						  'ecom_payment_card_name' 				=> $m['x_first_name'],
						  'ecom_payment_card_type' 				=> $card_type,
						  'ecom_payment_card_number' 			=> $m['x_card_num'],
						  'ecom_payment_card_expdate_month' 	=> substr($m['x_exp_date'],0,2),
						  'ecom_payment_card_expdate_year' 		=> substr($m['x_exp_date'],-4),
						  'pg_transaction_type' 				=> $m['x_type'],
						  'pg_merchant_id' 						=> $merchant_id,
						  'pg_password' 						=> $merchant_passwd,
						  'pg_avs_method' 						=> $avs_method,
						  'ecom_billto_postal_city' 			=> $m['x_city'],
						  'ecom_billto_postal_street_line1' 	=> $m['x_address'],
						  'ecom_billto_postal_stateprov' 		=> $m['x_state'],
						  'ecom_billto_postal_postalcode' 		=> $m['x_zip'],
						  'ecom_billto_postal_countrycode' 		=> $m['x_country'],
						  'ecom_billto_telecom_phone_number' 	=> $m['x_phone'],
						  'ecom_billto_online_email' 			=> $m['x_email']
						);
						break;
						
					default:
						Mage::throwException(Mage::helper('paygate')->__('Unknown transaction type'));
				}
				break;
			case self::REQUEST_METHOD_ECHECK:
				// ABA|Account Number|Account Type|Check Number
				$aba 			= $m['x_aba'];
				$account_number = $m['x_account_number'];
				$account_type 	= $m['x_account_type'];
				$check_number 	= $m['x_check_number'];
				
				// depending on the transaction type prepare the data to be sent
				switch ($m['x_type'])
				{
					case self::ECHECK_REQUEST_TYPE_VOID:
					case self::ECHECK_REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
						$input = array (
						  'pg_transaction_type' 				=> $m['x_type'],
						  'pg_merchant_id' 						=> $merchant_id,
						  'pg_password' 						=> $merchant_passwd,
						  'pg_original_trace_number' 			=> $m['x_auth_code'],
						  'pg_original_authorization_code' 		=> $m['x_trans_id']
						);
						break;
						
					case self::ECHECK_REQUEST_TYPE_CREDIT:
						$input = array (
							  'pg_transaction_type' 				=> $m['x_type'],
							  'pg_merchant_id' 						=> $merchant_id,
							  'pg_password' 						=> $merchant_passwd,
							  'pg_original_trace_number' 			=> $m['x_auth_code'],
							  'pg_original_authorization_code' 		=> $m['x_trans_id'],
							  'pg_total_amount' 					=> $m['x_amount'],
							  'ecom_billto_postal_name_first' 		=> $m['x_first_name'],
							  'ecom_billto_postal_name_last' 		=> $m['x_last_name'],
							  'ecom_billto_postal_city' 			=> $m['x_city'],
							  'ecom_billto_postal_street_line1' 	=> $m['x_address'],
							  'ecom_billto_postal_stateprov' 		=> $m['x_state'],
							  'ecom_billto_postal_postalcode' 		=> $m['x_zip'],
							  'ecom_billto_postal_countrycode' 		=> $m['x_country'],
							  'ecom_billto_telecom_phone_number' 	=> $m['x_phone'],
							  'ecom_billto_online_email' 			=> $m['x_email'],
							  
							  'ecom_payment_check_trn'			=> $aba,
							  'ecom_payment_check_account'		=> $account_number,
							  'ecom_payment_check_account_type'	=> $account_type,
							  'ecom_payment_check_checkno'		=> $check_number
							);
						break;
						
					case self::ECHECK_REQUEST_TYPE_AUTH_ONLY:
					case self::ECHECK_REQUEST_TYPE_AUTH_CAPTURE:
						// Prepare the data to be sent
						$input = array (
						  'pg_transaction_type' 				=> $m['x_type'],				
						  'pg_merchant_id' 						=> $merchant_id,
						  'pg_password' 						=> $merchant_passwd,				
						  'pg_total_amount' 					=> $m['x_amount'],
						  'ecom_billto_postal_name_first' 		=> $m['x_first_name'],
						  'ecom_billto_postal_name_last' 		=> $m['x_last_name'],
						  'ecom_billto_postal_city' 			=> $m['x_city'],
						  'ecom_billto_postal_street_line1' 	=> $m['x_address'],
						  'ecom_billto_postal_stateprov' 		=> $m['x_state'],
						  'ecom_billto_postal_postalcode' 		=> $m['x_zip'],
						  'ecom_billto_postal_countrycode' 		=> $m['x_country'],
						  'ecom_billto_telecom_phone_number' 	=> $m['x_phone'],
						  'ecom_billto_online_email' 			=> $m['x_email'],
						  
						  'ecom_payment_check_trn'			=> $aba,
						  'ecom_payment_check_account'		=> $account_number,
						  'ecom_payment_check_account_type'	=> $account_type,
						  'ecom_payment_check_checkno'		=> $check_number				  
						);
						break;
						
					default:
						Mage::throwException(Mage::helper('paygate')->__('Unknown transaction type'));
				}			
				break;
		}

		// format the data to be sent via cURL as POST
		$output_transaction = '';
		foreach($input as $k=>$v){
			$output_transaction .= "$k=$v&";
		}
		$output_transaction .= "endofdata&";
		$this->logit("POSTFIELDS", array('output_transaction' => $output_transaction));
		
		// Payment Gateway URL
		if ($this->getConfigData('test')) {
			$output_url = "https://www.paymentsgateway.net/cgi-bin/posttest.pl";
		} else {
			$output_url = "https://www.paymentsgateway.net/cgi-bin/postauth.pl";
		}
		
		// Send the request to the payment gateway and get the response
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $output_url );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,  1 );
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);		
		curl_setopt($ch, CURLOPT_POST,            1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     str_replace('&', "\n", $output_transaction) );
		curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: text/plain')); 
		$process_result = curl_exec ($ch);		
		if (curl_errno($ch)) {
			echo 'Curl error: ' . curl_error($ch);
			Mage::throwException(Mage::helper('paygate')->__('Error while connecting to payment gateway: ' . curl_errno($ch) . ' - ' . curl_error($ch)));
		} 
		curl_close($ch);

		// If there is an internet connection issue then the response will be blank
		// and there will be no curl error too.
		if (empty($process_result)) {
			Mage::throwException(Mage::helper('paygate')->__('Error while connecting to payment gateway.'));
		}
		
		// clean response data of whitespace, convert newline to ampersand for parse_str function and trim off endofdata
		$process_result = str_replace("\n", '&', trim(str_replace("endofdata", "", trim($process_result))));
		parse_str($process_result, $retarr);
		$this->logit('RETARR', array('retarr' => $retarr));

		// Load Default Dummy Values
	    $rr 						= array();
	    $rr['response_code']		= '1';	
	    $rr['response_subcode']		= '1';
	    $rr['response_reason_code']	= '1';
	    $rr['response_reason_text'] = '(TESTMODE2) This transaction has been approved.';
	    $rr['approval_code'] 		= '000000'; //ApprovalCode
	    $rr['avs_result_code']		= 'P';
	    $rr['transaction_id']		= '0';
	    $rr['md5_hash']				= '382065EC3B4C2F5CDC424A730393D2DF';
	    $rr['card_code_response']	= '';
		
		//Now check for approval
	    if (( isset($retarr['pg_response_code']) && $retarr['pg_response_code']=='A01' )) {
		    $rr['response_code']		= $retarr['pg_response_type'];
		    $rr['response_subcode']		= $retarr['pg_response_code'];
		    $rr['response_reason_code']	= $retarr['pg_response_code'];
			
		    if (isset($retarr['pg_response_description']) && !empty($retarr['pg_response_description'])) {
				$rr['response_reason_text']	= $retarr['pg_response_description'];
			}
		    if (isset($retarr['pg_trace_number']) && !empty($retarr['pg_trace_number'])) {
				$rr['approval_code'] 	= $retarr['pg_trace_number'];
			}
		    if (isset($retarr['pg_avs_result']) && !empty($retarr['pg_avs_result'])) {
				$rr['avs_result_code']	= $retarr['pg_avs_result'];
			
				// The user may want to check AVS but, may not want to decline transactions based on it
				// In such cases append the AVS message to the response for the record.
				if (substr($rr['avs_result_code'], 0, 1) == 4) {
					$rr['response_reason_text'] .= "\n" . 'AVS zipcode check failed';
				}
				if (substr($rr['avs_result_code'], 1, 1) == 4) {
					$rr['response_reason_text'] .= "\n" . 'AVS street number check failed';
				}
				if (substr($rr['avs_result_code'], 2, 1) == 4) {
					$rr['response_reason_text'] .= "\n" . 'AVS state/zipcode check failed';
				}
				if (substr($rr['avs_result_code'], 3, 1) == 4) {
					$rr['response_reason_text'] .= "\n" . 'AVS state/area code check failed';
				}
				if (substr($rr['avs_result_code'], 4, 1) == 4) {
					$rr['response_reason_text'] .= "\n" . 'AVS ananymous email check failed';
				}
			}
			
		    if (isset($retarr['pg_authorization_code']) && !empty($retarr['pg_authorization_code'])) {
				$rr['transaction_id']	= $retarr['pg_authorization_code'];
			}
			
	    } else {
		    $rr['response_code']		= $retarr['pg_response_type'];
		    $rr['response_subcode']		= $retarr['pg_response_code'];
		    $rr['response_reason_code']	= $retarr['pg_response_code'];
		    if (isset($retarr['pg_response_description']) && !empty($retarr['pg_response_description'])) {
				$rr['response_reason_text'] = $retarr['pg_response_description'];
			}
		    if (isset($retarr['pg_avs_result']) && !empty($retarr['pg_avs_result'])) {
				$rr['avs_result_code']	= $retarr['pg_avs_result'];
			}			
		    $rr['approval_code'] 	= '000000'; //ApprovalCode
		    $rr['transaction_id']	= '0';
	    }
       	return $rr;
	}
	
	function logit($func, $arr=array())
	{
		if(!$this->getConfigData('debug')) return; // Set via Admin

		if(!isset($this->pth)||empty($this->pth)){
				$cfg = Mage::getConfig();
				$this->pth = $cfg->getBaseDir();
		}

		$f = $this->pth . '/magento_log.txt';

		if(!is_writable($f))return;

		$a='';
		if(count($arr)>0)$a=var_export($arr,true);
		@file_put_contents( $f , '----- Inside ' . $func . ' =1= ' . date('d/M/Y H:i:s') . ' -----' . "\n" . $a, FILE_APPEND);

	}
}