<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-12-5
 * Time: 下午12:30
 */

namespace Dy\Restful;


abstract class RestBase
{
    /**
     * @var string
     * eg v2/talk/group/{group_id}  is a group resource
     *    v2/talk/group/{group_id}/topic is a collection of topics
     */
    protected $uri;

    protected static $_init_flag = FALSE;

    /**
     * @var \CI_Dummy the one and only ci (2333.....)
     */
    protected static $ci = NULL;

    /**
     * @var \Redis the redis client
     */
    protected static $redis = NULL;


    /**
     * @var int
     * -1 means forever
     */
    public $cache_time = 60;


    protected $_errors;

    public function __construct()
    {
        static::init();
    }

    /**
     * set ci reference and redis
     */
    public static function init()
    {
        if (static::$ci === NULL) {
            static::$ci = &get_instance(); // get ci ... since we are using ci...
        }
        if (static::$redis === NULL) {
            static::$redis = static::$ci->redis_lib->client;
        }
        static::$_init_flag = TRUE;
    }

    /**
     * @todo better error log and handling
     * @todo use monolog
     * @return array
     */
    public function get_error()
    {
        return $this->_errors;
    }


    /**
     * @param $msg  string human readable error hint
     * @param $code string machine readable error hint
     * @todo errors with same code?
     * @todo code definition. use PHP_CONSTANT is good, but how can app and browsers understand it?
     */
    protected function add_error($msg, $code)
    {
        $this->_errors[] = array(
            'code' => $code,
            'msg' => $msg
        );
    }


    /**
     * @return bool|mixed
     */
    protected static function get_cache($cache_name)
    {
        $cache_name = md5($cache_name);
        $cached_data = static::$redis->get($cache_name);
        if ($cached_data === FALSE) {
            return FALSE;
        } else {
            return unserialize($cached_data);
        }
    }

    /**
     * @param $data
     */
    protected static function save_cache($cache_name, $data, $cache_time = 60)
    {
        $cache_name = md5($cache_name);
        $data = serialize($data); // serialize can avoid the json_decode's obj and array mess
        static::$redis->setex($cache_name, $cache_time, $data);
    }

    protected static function save_cache_forever($cache_name, $data)
    {
        $cache_name = md5($cache_name);
        $data = serialize($data); // serialize can avoid the json_decode's obj and array mess
        static::$redis->set($cache_name, $data); // save it forever
    }

    /**
     * clean the cache for this resource
     */
    protected static function del_cache($cache_name)
    {
        $cache_name = md5($cache_name);
        static::$redis->del($cache_name);
    }
}