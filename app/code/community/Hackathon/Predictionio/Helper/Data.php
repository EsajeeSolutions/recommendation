<?php

/**
 *    Helper class for Similarity module
 *    handling most data transactions via cURL
 *
 * @category      Richdynamix
 * @package       Richdynamix_SimilarProducts
 * @original_author        Steven Richardson (steven@richdynamix.com) @troongizmo
 * @maintainer		Dima Kovalyov (dimdroll@gmail.com)
 * @license       http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Hackathon_Predictionio_Helper_Data extends Mage_Core_Helper_Abstract
{
    const PREDICTION_INDEX_API_ENDPOINT = 'events.json';
    const PREDICTION_QUERY_API_ENDPOINT = 'queries.json';
    const DATE_TIME_FORMAT = DateTime::ISO8601;

    private $refactor_model = null;

    public function __construct()
    {
        $this->refactor_model = Mage::getModel('predictionio/prediction');
    }


    public function connect_to_write($table) {

        if(!isset(self::$writeConnection)) {
            $resource = Mage::getSingleton('core/resource');
            $writeConnection = $resource->getConnection('core_write');
            $tableName = $resource->getTableName($table);

        }

        return $writeConnection;

    }


    /**
     * Perform the cURL GET Request
     *
     * @param  string $url   URL of PredictionIO API
     * @param  string $query Query params to get data
     *
     * @return string string version of JSON object to be parsed.
     */
    public function getData($url, $query)
    {

        $ch = curl_init();

        // Set query data here with the URL
        curl_setopt($ch, CURLOPT_URL, $url . $query);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, '3');

        $content = trim(curl_exec($ch));

        curl_close($ch);

        return $content;
    }

    /**
     * Similarity Engine Key, Defined in PredictionIO
     *
     * @return string
     */
    public function getEngineKey()
    {
        return Mage::getStoreConfig('predictionio/settings/predict_key');
    }

    /**
     * Similarity Engine Name, Defined in PredictionIO
     *
     * @return string
     */
    public function getEngineName()
    {
        return Mage::getStoreConfig('predictionio/settings/engine_name');
    }

    /**
     * PredictionIO URL
     *
     * @return string
     */
    public function getApiHost()
    {
        return Mage::getStoreConfig('predictionio/settings/predict_host');
    }

    /**
     * PredictionIO API Port, Default is 8000 but needs to be defined
     *
     * @return string
     */
    public function getApiPort()
    {
        return Mage::getStoreConfig('predictionio/settings/predict_port');
    }

    /**
     * PredictionIO API Ranking Port, Default is 9993 but needs to be defined
     *
     * @return string
     */
    public function getApiRankingPort()
    {
        return Mage::getStoreConfig('predictionio/settings/predict_ranking_port');
    }

    /**
     * PredictionIO API Recommendation Port, Default is 9997 but needs to be defined
     *
     * @return string
     */
    public function getApiRecommendationPort()
    {
        return Mage::getStoreConfig('predictionio/settings/predict_recommendation_port');
    }

    /**
     * Module ON/OFF Switch
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfig('predictionio/settings/enabled');
    }

    /**
     * Determine if the results should be based on similar categories
     *
     * @return bool
     */
    public function isCategoryResults()
    {
        return Mage::getStoreConfig('predictionio/settings/category_results');
    }

    /**
     * Get maximum returned products
     *
     * @return int
     */
    public function getProductCount()
    {
        return Mage::getStoreConfig('predictionio/settings/product_count');
    }

    /**
     * Get minimum score for upsell recommendations
     *  
     * @return int
     */
    public function getScoreThreshold()
    {   
        return Mage::getStoreConfig('predictionio/settings/score_threshold');
    }

    /**
     * Get debug option
     *
     * @return int
     */
    public function getDebug()
    {   
        return Mage::getStoreConfig('predictionio/settings/debug');
    }

    /**
     * Get debug file path
     *
     * @return int
     */
    public function getDebugFilePath()
    {   
        return Mage::getStoreConfig('predictionio/settings/debug_file_path');
    }

    /**
     * If Debug is enabled in configuration
     * Output to specified file or to system.log
     */
    public function logThis($header, $message) {

        if ($this->getDebug()) {
                $logFile = $this->getDebugFilePath();

                if (isset($logFile)) {
                        Mage::log($header . ": " . $message . "\n", null, $logFile);
                } else {
                        Mage::log($header . ": " . $message . "\n");
                }

        }
     }


}
