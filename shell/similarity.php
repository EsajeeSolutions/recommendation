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

class Hackathon_Shell_Prediction extends Mage_Shell_Abstract
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
     * Setup the run command with the right data to process
     */
    public function __construct()
    {
        parent::__construct();

        set_time_limit(0);

        $this->_helper = Mage::helper('predictionio');
        $this->_model  = Mage::getModel('predictionio/prediction');


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
	// we better limit collection size to avoid memory issues
	// and read it page by page
	$salesCollection = $salesModel->getCollection();
	$salesCollection->setPageSize(100);
	$pages = $salesCollection->getLastPageNumber();
        $currentPage = 1;

        do {
		$salesCollection->setCurPage($currentPage);
		printf('Processing page %s out of %s pages' . "\r", $currentPage, $pages);
		$salesCollection->load();

	        foreach ($salesCollection as $order) {
        	    if ($order->getCustomerId()) {
                	$_order[$order->getIncrementId()]['customer'][$order->getCustomerId()] = array();
	                foreach ($order->getAllItems() as $item) {
        	            $_order[$order->getIncrementId()]['customer'][$order->getCustomerId()]['items'][] = $item->getProductId();
                	}
	            }
		}

	        $currentPage++;
		$salesCollection->clear();
        } while ($currentPage <= $pages);
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
        $this->_model->_addCustomer($customerId);
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
        if (empty($products) || empty($customerId)) {
            return;
        }
        $this->_model->_addItems($products, $customerId);
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
        $this->_model->_addAction($_productId, $customerId);
    }

}

$shell = new Hackathon_Shell_Prediction();
$shell->run();
