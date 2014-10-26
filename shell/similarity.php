<?php
/**
 * Simple script to import customers, products and actions
 * into prediction engine for all past orders.
 *
 * This will only add a coversion action type as we cannot determine
 * the previous actions of the customers
 *
 * @category    Richdynamix
 * @package     Richdynamix_SimilarProducts
 * @author      Steven Richardson (steven@richdynamix.com) @troongizmo
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
require_once 'abstract.php';

class Richdynamix_Shell_Similarity extends Mage_Shell_Abstract
{
    const DATE_TIME_FORMAT = DateTime::ISO8601;

    /**
     * Define the a list of stores to run
     *
     * @var array
     */
    protected $_stores = array();

    /**
     * Store count for reporting
     *
     * @var int
     */
    protected $_sCount = 0;

    /**
     * Store count for reporting
     *
     * @var int
     */
    protected $_iCount = 0;

    /**
     * Define the helper object
     *
     * @var NULL
     */
    protected $_helper;

    /**
     * Define the model object
     *
     * @var NULL
     */
    protected $_model;


    /**
     * API Endpoint for users
     *
     * @var string
     */
    protected $_userUrl = 'events.json';

    /**
     * API Endpoint for items
     *
     * @var string
     */
    protected $_itemsUrl = 'events.json';

    /**
     * API Endpoint for users-to-item actions
     *
     * @var string
     */
    protected $_actionsUrl = 'events.json';


    /**
     * Setup the run command with the right data to process
     */
    public function __construct()
    {
        parent::__construct();

        set_time_limit(0);

        $this->_helper = Mage::helper('similarproducts');
        $this->_model  = Mage::getModel('similarproducts/prediction');


        if ($this->getArg('stores')) {
            $this->_stores = array_merge(
                $this->_stores,
                array_map(
                    'trim',
                    explode(',', $this->getArg('stores'))
                )
            );
        }

    }

    // Shell script point of entry
    public function run()
    {

        try {

            if (!empty($this->_stores)) {
                $selectedStores = '"' . implode('", "', $this->_stores) . '"';
            } else {
                $selectedStores = 'All';
            }

            printf(
                'Selected stores: %s' . "\n",
                $selectedStores
            );

            echo "\n";

            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                $storeName = $store->getName();
                if (!empty($this->_stores) && !in_array($storeName, $this->_stores)) {
                    continue;
                }
                $this->_processStore($store);
            }

            printf(
                'Done processing.' . "\n"
                . 'Total processed stores: %d' . "\n",
                $this->_sCount, $this->_iCount
            );

        } catch (Exception $e) {
            echo $e->getMessage() . '@' . time();
        }

    }

    // Usage instructions
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f prelaunch.php -- [options]

  --stores <names>       Process only these stores (comma-separated)

  help                   This help

USAGE;
    }

    /**
     * Lets process each store sales
     *
     * @param string $store Pass in the store to process
     *
     * @return void
     */
    protected function _processStore($store)
    {
        $storeName = $store->getName();
        printf('Processing "%s" store' . "\n", $storeName);
        $this->_sCount++;
        Mage::app()->setCurrentStore($store->getId());
        $salesModel      = Mage::getModel("sales/order");
        $salesCollection = $salesModel->getCollection();
        foreach ($salesCollection as $order) {
            if ($order->getCustomerId()) {
                $_order[$order->getIncrementId()]['customer'][$order->getCustomerId()] = array();
                foreach ($order->getAllItems() as $item) {
                    $_order[$order->getIncrementId()]['customer'][$order->getCustomerId()]['items'][] = $item->getProductId();
                }
            }
        }
        $this->preparePost($_order);
    }

    /**
     * Setup customers, products and actions
     *
     * @param string $orders the order for given store
     *
     * @return void
     */
    private function preparePost($orders)
    {
        foreach ($orders as $order) {
            foreach ($order['customer'] as $key => $items) {
                $customerId = $key;
                $products   = $items['items'];
            }
            $this->_addCustomer($customerId);
            $this->_addItems($products, $customerId);
        }
    }

    /**
     * Sets up cURL request paramaters for adding a customer
     *
     * @param int $customerId Customer ID of loggedin customer
     *
     * @return void
     */
    private function _addCustomer($customerId)
    {
        if (empty($customerId)) {
            return;
        }
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
                'appId'      => (int) $this->_helper->getEngineKey(),
                'properties' => $properties,
                'eventTime'  => $eventTime,
            ]
        );
        $this->_model->postRequest(
            $this->_helper->getApiHost() . ':' . $this->_helper->getApiPort() . '/' .
            Richdynamix_SimilarProducts_Helper_Data::PREDICTION_INDEX_API_ENDPOINT,
            $json
        );
    }

    /**
     * Sets up cURL request paramaters for adding a parent
     * item of ordered product (Since Upsells can only be shown on parents)
     *
     * @param int $products   Product ID of purchased item
     * @param int $customerId Customer ID of loggedin customer
     *
     * @return void
     */
    private function _addItems($products, $customerId)
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
            return;
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
            'appId'      => (int) $this->_helper->getEngineKey(),
            'properties' => $properties,
            'eventTime'  => $eventTime,
            ]
        );

        $this->_model->postRequest(
            $this->_helper->getApiHost() . ':' . $this->_helper->getApiPort() . '/' .
            Richdynamix_SimilarProducts_Helper_Data::PREDICTION_INDEX_API_ENDPOINT,
            $json
        );
        $this->_addAction($_productId, $customerId);

    }

    /**
     * Sets up cURL request paramaters for adding a user-to-item action
     *
     * @param int $_productId Product ID of item to action
     * @param int $customerId Customer ID of loggedin customer
     *
     * @return void
     */
    private function _addAction($_productId, $customerId)
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

}

$shell = new Richdynamix_Shell_Similarity();
$shell->run();
