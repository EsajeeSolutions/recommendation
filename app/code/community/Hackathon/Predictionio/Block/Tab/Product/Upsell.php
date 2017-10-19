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
class Hackathon_Predictionio_Block_Tab_Product_Upsell extends TM_EasyTabs_Block_Tab_Product_Upsell
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

	$raw_guestData = Mage::getModel('core/cookie')->get('userData');
        $guestuserID = json_decode($raw_guestData)->userID;

	if ($_helper->isEnabled()) {
		// if user is logged in get his ID
		if ( Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customerId = Mage::getSingleton('customer/session')->getCustomerId();
		// if not, assign ID from guest userData
		} else {
			$customerId = $guestuserID;
		}
		// if we have valid customerId recommenend based on it
		if ( !isset($customerId && $cutomerId != 'GUEST' ) {
			$recommendedproducts = $_model->getRecommendedProducts($customerId, 'user');
		}
		// recommend based on item is user recommendation is empty
		// or if user recommendation is not applicable
		if ( !isset($recommendedproducts) ) {
	    		$recommendedproducts = $_model->getRecommendedProducts($productId, 'item'))
		}
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
    }
}
