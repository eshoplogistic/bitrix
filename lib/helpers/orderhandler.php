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
		$widthDefault  = (int)Option::get(Config::MODULE_ID, 'width_default', 0);
		$heightDefault = (int)Option::get(Config::MODULE_ID, 'height_default', 0);
		$lengthDefault = (int)Option::get(Config::MODULE_ID, 'length_default', 0);
		$weightDefault = (int)Option::get(Config::MODULE_ID, 'weight_default', 1);

		// Собираем активные позиции и их product ID за один проход
		$basketItems = array();
		$productIds  = array();
		foreach ($basket as $basketItem) {
			if (!$basketItem->canBuy() || $basketItem->isDelay()) continue;
			$basketItems[] = $basketItem;
			$productIds[]  = $basketItem->getProductId();
		}

		// Один батч-запрос вместо N запросов в цикле
		$productDimensions = array();
		if ($productIds) {
			$result = Catalog\ProductTable::getList(array(
				'filter' => array('=ID' => $productIds),
				'select' => array('ID', 'WIDTH', 'LENGTH', 'HEIGHT')
			));
			while ($product = $result->fetch()) {
				$productDimensions[$product['ID']] = $product;
			}
		}

		$width  = $widthDefault;
		$height = $heightDefault;
		$length = $lengthDefault;
		foreach ($basketItems as $basketItem) {
			$productId = $basketItem->getProductId();
			if (isset($productDimensions[$productId])) {
				$product = $productDimensions[$productId];
				if ($product['WIDTH'])  $width  = $product['WIDTH']  / 10;
				if ($product['LENGTH']) $height = $product['LENGTH'] / 10;
				if ($product['HEIGHT']) $length = $product['HEIGHT'] / 10;
			}

			$item = array(
				"article"    => $productId,
				"name"       => $basketItem->getField('NAME'),
				"count"      => $basketItem->getQuantity(),
				"price"      => $basketItem->getPrice(),
				"weight"     => ($basketItem->getWeight() > 0) ? $basketItem->getWeight() / 1000 : $weightDefault,
				"dimensions" => $width . "*" . $height . "*" . $length,
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