<?php

/**
 * Class Richdynamix_SimilarProducts_Model_Prediction
 */

class Richdynamix_SimilarProducts_Model_Prediction extends Mage_Core_Model_Abstract
{

    /**
     * Perform the POST Request
     *
     * @param string $url  URL of PredictionIO API
     * @param json   $json Query params for POST data
     *
     * @return void
     */
    public  function postRequest($url, $json)
    {
        $client = new Zend_Http_Client(
            'http://'.$url,
            array(
                'maxredirects' => 0,
                'timeout' => 1)
        );
        $client->setRawData($json, 'application/json')->request('POST');
        $status = $client->getLastResponse();
        if ($status->getStatus() == 400) { // log bad request
            Mage::log("Prediction postRequest BadRequest ".$json);
        };
    }
}
