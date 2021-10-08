<?php

namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main\Config\Option,
	\Bitrix\Main\Web\HttpClient,
	\Eshoplogistic\Delivery\Config;

/** ????? ??? ?????? ??????? ? eShopLogistic
 * Class Client
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */
class Client {
	private $httpClient;
	private $url;
	private $apiKey;
	private $isLog;

	function __construct( $apiObject ) {

		$this->httpClient = new HttpClient();
		$this->url        = 'https://api.eshoplogistic.ru/api/' . $apiObject;
		$this->apiKey     = Option::get( Config::MODULE_ID, 'api_key' );

		$this->isLog = Option::get( Config::MODULE_ID, 'is_log' );
	}

	/** Http - ?????? ??? ?????? ??????? ? eSputnik
	 *
	 * @param string $httpMethod
	 * @param array $apiParams
	 *
	 * @return array
	 */

	public function request( $httpMethod, $apiParams = array() ) {
		$apiParams['key'] = $this->apiKey;
		$this->httpClient->query( $httpMethod, $this->url, $apiParams, false );
		$httpResult = $this->httpClient->getResult();

		if ( ! $httpResult ) {
			$httpResult = $this->alternativeCurlPost( $this->url, $apiParams );
		}

		return json_decode( $httpResult, true );
	}

	public function alternativeCurlPost( $url, $body = null ) {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt( $curl, CURLOPT_POST, 1 );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
		$result = curl_exec( $curl );
		curl_close( $curl );

		return $result;
	}

}