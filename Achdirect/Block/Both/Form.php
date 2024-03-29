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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Mage
 * @package    Mage_Achdirect
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author	   Gayatri S Ajith <gayatri@schogini.com>
 */


class Mage_Achdirect_Block_Both_Form extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
		$achtype = Mage::getModel('Mage_Achdirect_Model_Achdirect')->getConfigData('achtype');
		$this->setData('achtype', $achtype);
		switch ($achtype) {
			case 'eft':
				$this->setTemplate('achdirect/both/eft.phtml');
				break;
			case 'cc':
				$this->setTemplate('achdirect/both/cc.phtml');
				break;
			default:
				$this->setTemplate('achdirect/both/form.phtml');
		}
    }
}