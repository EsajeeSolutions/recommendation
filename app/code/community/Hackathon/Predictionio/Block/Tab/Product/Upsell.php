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

    	if ($_helper->isEnabled() && Mage::getSingleton('customer/session')->isLoggedIn()) {
	    	if ($similarproducts = $_model->getSimilarProducts($product)) {
	    	
	            $collection = Mage::getResourceModel('catalog/product_collection');
	            Mage::getModel('catalog/layer')->prepareProductCollection($collection);
	            $collection->addAttributeToFilter('entity_id', array('in' => $similarproducts));

	            $this->_itemCollection = $collection;

	            return $this->_itemCollection;

	    	} else {
	    		return parent::_prepareData();
	    	}	
    	} else {
    		return parent::_prepareData();
    	}       
    }
}
