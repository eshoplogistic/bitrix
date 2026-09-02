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

		// Один батч-запрос вместо N запросов в цикле; сами габариты (в т.ч. приоритет
		// источников — стандартные поля ДхШхВ и/или кастомные свойства товара) считает
		// Dimensions::resolveForProducts (см. options.php, секция "Габариты").
		$productDimensions = $productIds ? Dimensions::resolveForProducts($productIds) : array();

		foreach ($basketItems as $basketItem) {
			$productId = $basketItem->getProductId();
			$dimensions = $productDimensions[$productId] ?? array();
			$width  = $dimensions['WIDTH']  ?? $widthDefault;
			$height = $dimensions['HEIGHT'] ?? $heightDefault;
			$length = $dimensions['LENGTH'] ?? $lengthDefault;

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