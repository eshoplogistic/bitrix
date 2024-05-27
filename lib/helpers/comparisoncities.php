<?php
namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Main\Localization\Loc;

class ComparisonCities
{

    public static function checkCityNamePart($name, $nameApi, $type): bool
    {
        $name = str_replace(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_YO"), Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_E"), mb_strtolower($name));
        $nameApi = str_replace(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_YO"), Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_E"), mb_strtolower($nameApi));

        if($name == $nameApi)
            return true;

        $namePart = explode(' ', $name);
        $nameApiPart = explode(' ', $nameApi);
        $count = 0;
        $nameApiPartCount = count($nameApiPart);
        $namePartDisabled = Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_PART_DIS");

        foreach ($namePart as $value){
            if(in_array($value, $namePartDisabled))
                continue;

            if($type === 'region'){
                $region = Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_ARR");
                if(isset($region[$nameApi]))
                    $nameApiPart = array_merge($nameApiPart, $region[$nameApi]);
            }
            if(in_array($value, $nameApiPart)){
                $count++;
            }
        }

        if($count > 0)
            return true;


        return false;
    }

    public static function checkCityNamePartRevert($name, $type, $nameCity = ''): string
    {
        $name = str_replace(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_YO"), Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_E"), mb_strtolower($name));
        $nameCity = str_replace(Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_YO"), Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_E"), mb_strtolower($nameCity));

        if($type === 'region'){
            $region = Loc::GetMessage("ESHOP_LOGISTIC_HELPERS_REGION_ARR");
            foreach ($region as $key=>$value){
                if (in_array($name, $value) && $key === $nameCity){
                    $name = $key;
                    break;
                }
            }
        }

        return $name;
    }


}
