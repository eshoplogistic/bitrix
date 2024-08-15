<?
namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main,
	\Bitrix\Sale,
	\Bitrix\Catalog;
use Bitrix\Main\Config\Option;
use Eshoplogistic\Delivery\Api\Delivery;
use Eshoplogistic\Delivery\Api\Site;
use Eshoplogistic\Delivery\Config;

/** Class for handing current order
 * Class OrderHandler
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */

class OrderHandler
{

	/** Getting items of current order
	 * @param $basket
	 * @param string $paymentType
	 * @return array
	 */
	public static function getCurrentBasketItems($basket)
	{
		$offers = array();
		$width = (int)Option::get(Config::MODULE_ID, 'width_default', 0);
		$height = (int)Option::get(Config::MODULE_ID, 'height_default', 0);
		$length = (int)Option::get(Config::MODULE_ID, 'length_default', 0);
        $weightDefault = (int)Option::get(Config::MODULE_ID, 'weight_default', 1);

        foreach ($basket as $basketItem) {

			if(!$basketItem->canBuy() || $basketItem->isDelay()) continue;


			$result = Catalog\ProductTable::getList(array(
				'filter' => array('=ID'=>$basketItem->getProductId()),
				'select' => array('WIDTH', 'LENGTH', 'HEIGHT')
			));
			if($product=$result->fetch())

			{
				if($product['WIDTH']) $width = $product['WIDTH'] / 10;
				if($product['LENGTH']) $height = $product['LENGTH'] / 10;
				if($product['HEIGHT']) $length = $product['HEIGHT'] / 10;
			}


			$item = array(
				"article" => $basketItem->getProductId(),
				"name" => $basketItem->getField('NAME'),
				"count" => $basketItem->getQuantity(),
				"price" => $basketItem->getPrice(),
				"weight" => ($basketItem->getWeight() > 0)? $basketItem->getWeight() / 1000 : $weightDefault,
				"dimensions" => $width."*".$height."*".$length
			);


			$offers[] = $item;
		}

		return \Bitrix\Main\Web\Json::encode($offers);
	}

	public static function getCodeCityByApi(){
		$siteClass = new Site();
		$authStatus = $siteClass->getAuthStatus();
		if(!isset($authStatus['settings']['city_name']))
			return '';

		$resultCity = array('CODE'=>'');
		$locationName = $authStatus['settings']['city_name'];

		$res = \Bitrix\Sale\Location\LocationTable::getList(array(
			'filter' => array(
				'=NAME.NAME_UPPER' => ToUpper($locationName),
				'=NAME.LANGUAGE_ID' => "ru"
			),
			'select' => array('ID', 'CODE')
		));

		if($loc = $res->fetch())
			$resultCity = $loc;

		return $resultCity['CODE'];
	}

}