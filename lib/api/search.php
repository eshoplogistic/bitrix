<?
namespace Eshoplogistic\Delivery\Api;


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
    private function getHttpClient()
    {
        $apiObject = 'search';
        $httpClient = new Client($apiObject);
        return $httpClient;
    }

    /** Getting status of authorization and account balance
     * @param string $name
     * @return array
     */

    public function getCity($name)
    {
        $httpMethod = 'POST';
        $requestData = array('target' => $name);
        $httpClient = self::getHttpClient();
        $deliveryRequest = $httpClient->request($httpMethod, $requestData);
        return $deliveryRequest;
    }
}
?>