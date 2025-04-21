<?
namespace Eshoplogistic\Delivery\Helpers;

use \CFile,
    \Eshoplogistic\Delivery\Config;



/** Class for logo file handling
 * Class LogotipHandler
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */

class LogotipHandler
{

    /** Get id of logo file
     * @param $logotipFileName
     * @return int
     */
    public static function getLogotipFileId($logotipFileName)
    {
        $fileInfo = new \SplFileInfo($logotipFileName);
        $ext = $fileInfo->getExtension();

        $arFields["ATTACH_IMG"] = [
            'name' => $logotipFileName,
            'tmp_name' => $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/eshoplogistic.delivery/install/img/'.$logotipFileName,
            'type' => 'image/'.$ext
        ];

        $logotipFileId = CFile::SaveFile($arFields['ATTACH_IMG'], Config::MODULE_ID);

        return $logotipFileId;

    }

    /** Delete logo file
     * @param $logotipFileId
     * @return int
     */
    public static function deleteLogotipFile($logotipFileId)
    {
        CFile::Delete($logotipFileId);
        return 0;
    }

}