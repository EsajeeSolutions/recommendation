<?php
/**
 *	Upsell Block class for Similarity module
 *	Replacing upsell data with PredictionIO data
 *	
 * @category    Richdynamix
 * @package     Richdynamix_SimilarProducts
 * @author 		Steven Richardson (steven@richdynamix.com) @troongizmo
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Hackathon_Predictionio_Block_Catalog_Product_List_Upsell extends Mage_Catalog_Block_Product_List_Upsell
{
	/**
	 * Rewrite of parent::_prepareData() if 
	 * module enabled and has data relating to current product and
	 * customer is logged in.
	 * @return mixed _itemCollection or parent::_prepareData()
	 */
	protected function _prepareData()
    {
    	$_helper = Mage::helper('predictionio');
        $_model  = Mage::getModel('predictionio/prediction');
    	$product = Mage::registry('product');
	$productId = $product->getId();
	
	// check if we need to disable PredictionIO based on URL params
	$disablePrediction = Mage::app()->getRequest()->getParam('disableIO');

	$raw_guestData = Mage::getModel('core/cookie')->get('userData');
	if (isset($raw_guestData->userID)) {
	        $guestuserID = json_decode($raw_guestData)->userID;
	}

	// Visitor ID is Magento's internal ID
	// We need it to link prediction to Visitor
	// So we can later see what was seen by it
	$sessionId = Mage::getSingleton("core/session")->getEncryptedSessionId();
	if ( !isset($sessionId) ) {
		$sessionId= 'NULL';
	}
	// if module is enabled
	// and if it is not disabled via query string like:
	// http://107.167.185.249/ginger-sesame-salad-dressing.html?disableIO=false
	if ($_helper->isEnabled() and !isset($disablePrediction)) {
		// if user is logged in get his ID
		if ( Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customerId = Mage::getSingleton('customer/session')->getCustomerId();
		// if not, assign ID from guest userData if it exists
		} elseif (isset($guestuserID)) {
			$customerId = $guestuserID;
		}
		$_helper->logThis('Upsell.php:pre-getRecommendedProducts');
		// if we have valid customerId recommenend based on it
		if ( isset($customerId) && $customerId != 'GUEST' ) {
			$_helper->logThis('User based recommendation');
			try {
				$recommendedproducts = $_model->getRecommendedProducts($customerId, 'user', $sessionId, $productId);
			} catch (Exception $e) {
				$_helper->logThis('EXCEPTION: ', $e);
			}
		}
		// recommend based on item is user recommendation is empty
		// or if user recommendation is not applicable
		if ( !isset($recommendedproducts) ) {
			$_helper->logThis('Item based recommendation');
			try {
		    		$recommendedproducts = $_model->getRecommendedProducts($productId, 'item', $sessionId, $productId);
			} catch (Exception $e) {
				$_helper->logThis('EXCEPTION: ', $e);
			}
		}

		$_helper->logThis('Upsell.php:pre-recommendedproducts');
		// if any recommendation returned from engine
		// show them
		if (isset($recommendedproducts)) {

			$collection = Mage::getResourceModel('catalog/product_collection');
			Mage::getModel('catalog/layer')->prepareProductCollection($collection);
			$collection->addAttributeToFilter('entity_id', array('in' => $recommendedproducts));

			$this->_itemCollection = $collection;

			return $this->_itemCollection;

		// if not, show hand-pick
	    	} else {
	    		return parent::_prepareData();
	    	}	
	} else {
		return parent::_prepareData();
	}
    }
}
