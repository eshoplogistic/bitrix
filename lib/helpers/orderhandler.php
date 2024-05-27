<?
namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main,
	\Bitrix\Sale,
	\Bitrix\Catalog;
use Eshoplogistic\Delivery\Api\Delivery;
use Eshoplogistic\Delivery\Api\Site;

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
		$width = 0;
		$height = 0;
		$length = 0;

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
				"weight" => ($basketItem->getWeight() > 0)? $basketItem->getWeight() / 1000 : 1,
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