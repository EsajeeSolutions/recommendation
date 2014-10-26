<?php
/**
 *	Recommendation Block class for Predictionio module
 *	Showing recommended products with data from PredictionIO
 *
 * @category    Hackathon
 * @package     Hackathon_Predictionio
 * @author 		Gion-Antoni Koch (gion-antoni.koch@outlook.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Hackathon_Predictionio_Block_Catalog_Product_Recommendations
	extends Mage_Catalog_Block_Product_Abstract {
	/**
	 * get products collection
	 * @access protected
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */
	public function getProductCollection(){
		$_model = Mage::getModel('predictionio/prediction');
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
			$_productIds = $_model->getRecommendedProducts(Mage::getSingleton('customer/session')->getCustomer()->getId(), 5);
			$_productCollection = Mage::getModel('catalog/product')->getCollection()
				->addAttributeToFilter('entity_id', array('in' => $productIds))
				->load();
		} else {
			return null;
		}
	}
}