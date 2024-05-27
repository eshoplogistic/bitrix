<?
namespace Eshoplogistic\Delivery\Api;


use Eshoplogistic\Delivery\Config;
use \Eshoplogistic\Delivery\Helpers\Client;

/** Class for class for searching cities for delivery
 * Class Search
 * @package Eshoplogistic\Delivery\Api
 * @author negen
 */

class Search
{

    /**
     * @param string $service
     * @return Client
     */
    private static function getHttpClient()
    {
        $configClass = new Config();
        $apiV = $configClass->apiV;

        if($apiV){
            $apiObject = 'locality/search';
        }else{
            $apiObject = 'search';
        }
        $httpClient = new Client($apiObject);
        return $httpClient;
    }

    /** Getting status of authorization and account balance
     * @param string $name
     * @return array
     */

    public static function getCity($name, $region = '', $locationCode = '')
    {
        $httpMethod = 'POST';
        $requestData = array('target' => $name, 'region' => $region, 'bitrix_code' => $locationCode);
        $httpClient = self::getHttpClient();
        $deliveryRequest = $httpClient->request($httpMethod, $requestData);
        return $deliveryRequest;
    }
}
?>