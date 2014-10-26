<?php

/**
 * Class Richdynamix_SimilarProducts_Model_Prediction
 */
class Richdynamix_SimilarProducts_Model_Prediction extends Mage_Core_Model_Abstract
{
    protected $_helper;
    /**
     * Get a specific amount of recommended products for the user
     *
     * @param int $customerId
     * @param int $numProducts
     */
    public function getRecommendedProducts($customerId, $numProducts)
    {
        $json = json_encode(
            [
                'uid' => $customerId,
                'n'   => $numProducts
            ]
        );
        $this->postRequest(
            $this->getApiHost() . ':' . $this->getApiRecommendationPort() . '/' .
            Richdynamix_SimilarProducts_Helper_Data::PREDICTION_QUERY_API_ENDPOINT,
            $json
        );
    }

    /**
     * Perform the POST Request
     *
     * @param string $url  URL of PredictionIO API
     * @param string $json Query params for POST data
     *
     * @return void
     */
    public function postRequest($url, $json)
    {
        $client = new Zend_Http_Client(
            'http://' . $url,
            array(
                'maxredirects' => 0,
                'timeout'      => 1)
        );
        $client->setRawData($json, 'application/json')->request('POST');
        $status = $client->getLastResponse();
        if ($status->getStatus() == 400) { // log bad request
            Mage::log("Prediction postRequest BadRequest " . $json);
        };
    }

    /**
     * Sets up request paramaters for adding a customer
     *
     * @param int $customerId Customer ID of loggedin customer
     */
    public function _addCustomer($customerId)
    {
        $eventTime  = (new DateTime('NOW'))->format(self::DATE_TIME_FORMAT);
        $properties = array();
        if (empty($properties)) {
            $properties = (object) $properties;
        }
        $json = json_encode(
            [
                'event'      => '$set',
                'entityType' => 'pio_user',
                'entityId'   => $customerId,
                'appId'      => (int) $this->getHelper()->getEngineKey(),
                'properties' => $properties,
                'eventTime'  => $eventTime,
            ]
        );

        $this->postRequest(
            $this->getApiHost() . ':' . $this->getApiPort() . '/' .
            self::PREDICTION_INDEX_API_ENDPOINT,
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
    public  function _addItems($products, $customerId)
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
        }

        if (empty($_productId)) {
            return false;
        }

        $eventTime  = (new DateTime('NOW'))->format(self::DATE_TIME_FORMAT);
        $properties = array('pio_itypes' => array('1'));
        if (empty($properties)) {
            $properties = (object) $properties;
        }

        $json = json_encode(
            [
                'event'      => '$set',
                'entityType' => 'pio_item',
                'entityId'   => $_productId,
                'appId'      => (int) $this->getHelper()->getEngineKey(),
                'properties' => $properties,
                'eventTime'  => $eventTime,
            ]
        );

        $this->postRequest(
            $this->_helper->getApiHost() . ':' . $this->_helper->getApiPort() . '/' .
            Richdynamix_SimilarProducts_Helper_Data::PREDICTION_INDEX_API_ENDPOINT,
            $json
        );

        $this->_addAction($_productId, $customerId);

    }

    /**
     * Sets up request paramaters for adding a user-to-item action
     *
     * @param int $_productId Product ID of item to action
     * @param int $customerId Customer ID of loggedin customer
     *
     * @return void
     */
    public function _addAction($_productId, $customerId)
    {
        if (empty($_productId) || empty($customerId)) {
            return;
        }
        $eventTime  = (new \DateTime())->format(DateTime::ISO8601);
        $properties = array();
        if (empty($properties)) {
            $properties = (object) $properties;
        }
        $json = json_encode(
            [
                'event'            => 'conversion',
                'entityType'       => 'pio_user',
                'entityId'         => $customerId,
                'targetEntityType' => 'pio_item',
                'targetEntityId'   => $_productId,
                'appId'            => (int) $this->_helper->getEngineKey(),
                'properties'       => $properties,
                'eventTime'        => $eventTime,
            ]
        );
        $this->_model->postRequest(
            $this->_helper->getApiHost() . ':' . $this->_helper->getApiPort() . '/' .
            Richdynamix_SimilarProducts_Helper_Data::PREDICTION_INDEX_API_ENDPOINT,
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
            Mage::helper('similarproducts/data');
        } else {
            return $this->_helper;
        }
    }
}
