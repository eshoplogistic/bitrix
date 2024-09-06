<?php
namespace Eshoplogistic\Delivery\Logger;

/** Class for custom log
 *  * Example
$logger = new Logger('test', '*test');
$logger->log($message);
 */

class Logger
{
    public static $PATH = __DIR__;
    protected static $loggers=array();

    protected $name;
    protected $file;
    protected $fp;
    protected $fullPath;

    public function __construct($name=null, $file=null){
        $this->name=$name;
        $this->file=$file;

        $this->open();
    }

    public function open(){
        if(self::$PATH==null){
            return ;
        }

        $this->fullPath = $this->file==null ? self::$PATH.'/'.$this->name.'.log' : self::$PATH.'/'.$this->file;
        $this->fp=fopen($this->fullPath,'a+');
    }

    public static function getLogger($name='root',$file=null){
        if(!isset(self::$loggers[$name])){
            self::$loggers[$name]=new Logger($name, $file);
        }

        return self::$loggers[$name];
    }

    public function log($message){
        if(!is_string($message)){
            $this->logPrint($message);

            return ;
        }

        $log='';

        $log.='['.date('D M d H:i:s Y',time()).'] ';
        if(func_num_args()>1){
            $params=func_get_args();

            $message=call_user_func_array('sprintf',$params);
        }

        $log.=$message;
        $log.="\n";

        $this->_write($log);
    }

    public function logPrint($obj){
        ob_start();

        print_r($obj);

        $ob=ob_get_clean();
        $this->log($ob);
    }

    protected function _write($string){
        if (file_exists($this->fullPath)) {
            $size = filesize($this->fullPath);
            $sizeMb = round($size / 1024 / 1024, 2);
            if($sizeMb > 10){
                file_put_contents($this->fullPath, '');
            }
        }
        fwrite($this->fp, $string);
    }

    public function __destruct(){
        fclose($this->fp);
    }
}
