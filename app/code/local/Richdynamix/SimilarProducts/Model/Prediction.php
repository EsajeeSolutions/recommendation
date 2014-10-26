<?php

/**
 * Class Richdynamix_SimilarProducts_Model_Prediction
 */

class Richdynamix_SimilarProducts_Model_Prediction extends Mage_Core_Model_Abstract
{
	/**
	 * Get a specific amount of recommended products for the user
	 *
	 * @param int $customerId
	 * @param int $numProducts
	 */
	public function getRecommendedProducts($customerId, $numProducts) {
		$json = json_encode(
			[
				'uid'	=> $customerId,
				'n'		=> $numProducts
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
