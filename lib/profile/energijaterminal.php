<?
namespace Eshoplogistic\Delivery\Profile;

use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Loader,
    \Bitrix\Main\Application,
    \Bitrix\Sale,
    \Bitrix\Sale\Location\LocationTable,
    \CFile,
    \Eshoplogistic\Delivery\Helpers;

Loc::loadMessages(__FILE__);

class EnergijaTerminal extends \Bitrix\Sale\Delivery\Services\Base
{
    protected $logotip;
    protected $logotipFileName = 'energija.png';
    protected static $isProfile = true;
    protected static $service = 'energija';
    protected static $type = 'terminal';
    protected static $profileCode = 'eslogistic:energija_term';
    protected $parent = null;
    protected $fiasFrom = null;

    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
        $this->parent = Sale\Delivery\Services\Manager::getObjectById($this->parentId);
    }

    public function prepareFieldsForSaving(array $fields)
    {

        $request = Application::getInstance()->getContext()->getRequest();

        $isDelLogotip = $request->getPost("LOGOTIP_del");
        $logotipFileId = $request->getPost("LOGOTIP_FILE_ID");

        if($isDelLogotip === 'Y' && $logotipFileId > 0) {
            $this->logotip = Helpers\LogotipHandler::deleteLogotipFile($logotipFileId);
            $fields["LOGOTIP"] = $this->logotip;
        } elseif ($this->logotip == 0 && $this->logotipFileName) {
            $this->logotip = Helpers\LogotipHandler::getLogotipFileId($this->logotipFileName);
            $fields["LOGOTIP"] = $this->logotip;
        }

        $fields["CODE"] = self::$profileCode;

        return parent::prepareFieldsForSaving($fields);
    }

    public static function getClassTitle()
    {
        return Loc::getMessage("ESHOP_LOGISTIC_ENERGIJA_TERMINAL_CLASS_TITLE");
    }

    public static function getClassDescription()
    {
        return Loc::getMessage("ESHOP_LOGISTIC_ENERGIJA_TERMINAL_CLASS_DESCRIPTION");
    }

    public function getParentService()
    {
        return $this->parent;
    }

    public function isCalculatePriceImmediately()
    {
        return $this->getParentService()->isCalculatePriceImmediately();
    }

    public static function isProfile()
    {
        return self::$isProfile;
    }

    public function isCompatible(Sale\Shipment $shipment)
    {
        return true;
    }

    public function calculate(Sale\Shipment $shipment = null, $extraServices = array())
    {
        $result = Helpers\CalculateHandler::getDefaultCalculateDelivery($shipment, self::$service, self::$type);
        return $result;
    }

    public static function getPvzData($locationCode, $paymentId)
    {
        $deliveryProfileData = Helpers\CalculateHandler::getDefaultPvzData($locationCode, self::$service, $paymentId);
        return $deliveryProfileData['data'];
    }
}