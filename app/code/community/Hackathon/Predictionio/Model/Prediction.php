<?php

/**
 * Class Richdynamix_SimilarProducts_Model_Prediction
 */
class Hackathon_Predictionio_Model_Prediction extends Mage_Core_Model_Abstract
{
    private $_helper = null;

    /**
     * Filter Recoomendations based on score set in Configuration
     *
     * @param string $json JSON responce from PredictionIO API
     *
     * @return array of items OR null
     */
    public function filterRecommendations($json) {

        // from an array like ( "itemScores" => array( "item" => "id1", "score" => "score1"), ...)
        // get item ids that are greater then $score_threshold
        $score_threshold = $this->getHelper()->getScoreThreshold();
        if (isset($json)) {
                $array = array();
                foreach ($result['itemScores'] as $prediction) {

                        if ($prediction['score'] > $score_threshold ) {
                                $array[] = $prediction['item'];
                        }
                }

                return $array;

        } else {
                return null;
        }	

    };

    /**
     * Perform the POST Request to get recommendation from engine
     *
     * @param int $id id of customer or item to base recommendation on
     * @param string $type either 'user' or 'item'
     *
     * @return array of items
     */
    public function getRecommendedProducts($id, $type = null) {

        $numProducts = $this->getHelper()->getProductCount();

	// default recommendation is item based
	if (!isset($type)) {
                $type = 'item';
        }

        $json = json_encode(
                [
                        $type   => $id,
                        'num'   => $numProducts
                ]
        );

        $result = json_decode($this->postRequest(
                                $this->getHelper()->getApiHost() . ':' . $this->getHelper()->getApiRecommendationPort() . '/' .
                                Hackathon_Predictionio_Helper_Data::PREDICTION_QUERY_API_ENDPOINT,
                                $json, 1
                                ), true
        );

        return filterRecommendations($result);

     }

    /**
     * Perform the POST Request
     *
     * @param string $url  URL of PredictionIO API
     * @param string $json Query params for POST data
     *
     * @return void
     */
    public function postRequest($url, $json, $returnResult = null)
    {
        $client = new Zend_Http_Client(
            'http://' . $url,
            array(
                'maxredirects' => 0,
                'timeout'      => 1)
        );
        $client->setRawData($json, 'application/json');
        $responce_body = Zend_Http_Response::fromString($client->request('POST'))->getBody();
	$status = $client->getLastResponse()->getStatus();
       
//	Mage::log('URL: ' . $url . "\n", null, 'predictionio.log');
//	Mage::log('JSON: ' . $json . "\n", null, 'predictionio.log');
//	Mage::log('Status: ' . $status . "\n", null, 'predictionio.log');
//      Mage::log('Body: ' . $responce_body . "\n", null, 'predictionio.log');

	if ($status == 400) { // log bad request
		Mage::log("Prediction/postRequest/BadRequest " . $json, null, 'predictionio.log');
        };

        if (isset($returnResult)) {
                return $responce_body;
        }
    }

    /**
     * Sets up request paramaters for adding a customer
     *
     * @param int $customerId Customer ID of loggedin customer
     */
    public function _addCustomer($customerId)
    {
        $eventTime  = (new DateTime('NOW'))->format(Hackathon_Predictionio_Helper_Data::DATE_TIME_FORMAT);
        $properties = array();
        if (empty($properties)) {
            $properties = (object) $properties;
        }
        $json = json_encode(
            [
                'event'      => '$set',
                'entityType' => 'user',
                'entityId'   => $customerId,
                'properties' => $properties,
                'eventTime'  => $eventTime,
            ]
        );

        $this->postRequest(
            $this->getHelper()->getApiHost() . ':' . $this->getHelper()->getApiPort() . '/' .
            Hackathon_Predictionio_Helper_Data::PREDICTION_INDEX_API_ENDPOINT . '?accessKey=' . $this->getHelper()->getEngineKey(),
            $json
        );
    }

    /**
     * Sets up request paramaters for adding a parent
     * item of ordered product (Since Upsells can only be shown on parents)
     *
     * @param int $products   Product ID of purchased item
     * @param int $customerId Customer ID of loggedin customer
     *
     * @return void
     */
    public function _addOrder($products, $customerId, $action)
    {
        foreach ($products as $key => $productid) {
            $product = Mage::getModel('catalog/product')->load($productid);
            if ($product->getTypeId() == "simple") {
                $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
                if (!$parentIds)
                    $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
                if (isset($parentIds[0])) {
                    $_productId = $parentIds[0];
                } else {
                    $_productId = $product->getId();
                }
	    }
        // if type is not simple or something else is going on
            if (!isset($_productId)) {
                $_productId = $productid;
            }
	    $this->_addItem($_productId);
	    $this->_addAction($_productId, $customerId, $action);

        }

    }

    public function _addItem($productId)
    {
        if (empty($productId)) {
            return false;
        }
	// doing this second time, probably not wise TODO better
	$product = Mage::getModel('catalog/product')->load($productId);
	$eventTime  = (new DateTime('NOW'))->format(Hackathon_Predictionio_Helper_Data::DATE_TIME_FORMAT);
	$cats    = $this->getCategories($product);
	// replaced 4 next lines with cats
        $properties = array('category' => explode(",", $cats));
        if (empty($properties)) {
            $properties = (object) $properties;
        }

        $json = json_encode(
            [
                'event'      => '$set',
                'entityType' => 'item',
                'entityId'   => $productId,
                'properties' => $properties,
                'eventTime'  => $eventTime,
            ]
        );

        $this->postRequest(
            $this->getHelper()->getApiHost() . ':' . $this->getHelper()->getApiPort() . '/' .
            Hackathon_Predictionio_Helper_Data::PREDICTION_INDEX_API_ENDPOINT . '?accessKey=' . $this->getHelper()->getEngineKey(),
            $json
        );

    }

    /**
     * Gets comma seperated list of categories
     * belonging to product, used for pio_itypes in PredictionIO
     *
     * @param  Mage_Catalog_Model_Product $product Instance of Product Model
     *
     * @return string  Comma seperated categories
     */
    public function getCategories(Mage_Catalog_Model_Product $product)
    {

        if ($product->getId()) {
            $categoryIds = $product->getCategoryIds();
            if (is_array($categoryIds) and count($categoryIds) >= 1) {
                $catsString = '';
                foreach ($categoryIds as $id) {
                    $cat = Mage::getModel('catalog/category')->load($id);
                    $catsString .= $cat->getName() . ',';
                }
                $cats = rtrim($catsString, ",");
                return $cats;
            }
            return '';
        }
    }

    /**
     * Sets up request paramaters for adding a user-to-item action
     *
     * @param int $_productId Product ID of item to action
     * @param int $customerId Customer ID of loggedin customer
     *
     * @return void
     */
    public function _addAction($_productId, $customerId, $action)
    {
        if (empty($_productId) || empty($customerId) || empty($action)) {
            return;
        }
        $eventTime  = (new \DateTime())->format(DateTime::ISO8601);
        $properties = array();
        if (empty($properties)) {
            $properties = (object) $properties;
        }
        $json = json_encode(
            [
                'event'            => $action,
                'entityType'       => 'user',
                'entityId'         => $customerId,
                'targetEntityType' => 'item',
                'targetEntityId'   => $_productId,
                'properties'       => $properties,
                'eventTime'        => $eventTime,
            ]
        );
        $this->postRequest(
            $this->getHelper()->getApiHost() . ':' . $this->getHelper()->getApiPort() . '/' .
            Hackathon_Predictionio_Helper_Data::PREDICTION_INDEX_API_ENDPOINT . '?accessKey=' . $this->getHelper()->getEngineKey(),
            $json
        );
    }

    /**
     * get helper
     *
     * @return mixed
     */
    public function getHelper()
    {
        if (empty($this->_helper)) {
            return $this->_helper = Mage::helper('predictionio/data');
        } else {
            return $this->_helper;
        }
    }
}
