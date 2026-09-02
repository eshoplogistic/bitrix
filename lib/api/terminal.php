<?
namespace Eshoplogistic\Delivery\Api;

use \Eshoplogistic\Delivery\Helpers\Client;

/** Class for searching TK terminals (self drop-off points) by address
 * Class Terminal
 * @package Eshoplogistic\Delivery\Api
 */

class Terminal
{

    /**
     * @return Client
     */
    private static function getHttpClient()
    {
        return new Client('service/terminals');
    }

    /**
     * @param string $service
     * @param string $settlement
     * @param string $region
     * @param string $address
     * @param bool $onlyBranches
     * @return array
     */
    public static function search($service, $settlement = '', $region = '', $address = '', $onlyBranches = false)
    {
        $requestData = array('service' => $service);
        if ($settlement !== '') {
            $requestData['settlement'] = $settlement;
        }
        if ($region !== '') {
            $requestData['region'] = $region;
        }
        if ($address !== '') {
            $requestData['address'] = $address;
        }
        if ($onlyBranches) {
            $requestData['only_branches'] = 1;
        }

        $httpClient = self::getHttpClient();
        return $httpClient->request('POST', $requestData);
    }
}
?>