<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-11-23
 * Time: 上午11:33
 */
namespace Dy\Restful;
require_once(__DIR__ . '/Validator.php');
require_once(__DIR__ . '/RestBase.php');
use Dy\Event;

/**
 * Class Resource
 * @package Dy\Restful
 *
 * @todo default value. put it in write filter?
 */
class Resource extends RestBase
{

    const TABLE_NAME = '';
    const PRIMARY_KEY_NAME = 'id'; // you can override const in child class

    /**
     * @var bool
     *
     * some resource may not want cache at all
     */
    const DISABLE_CACHE = FALSE;

    /**
     * @var array 数组的key是数据库中的主键(字符串),value是一行的内容
     * eg: $_cached_rows['1'] = array('id'=>'1','name'=>'xiaoming');
     */
    protected static $_cached_rows;
    /**
     * @var array rules for the validator which will be called when insert or update
     *
     * if it is NULL then we wont do the validate, this is for speed when mass assign, though not secure
     */
    protected static $validate_rules = NULL;
    /**
     * @var Validator only create the validator when we have to insert or update
     */
    protected static $validator = NULL;


    /**
     * @var bool 是否在数据库中
     */
    protected $_in_db = FALSE;

    /**
     * @var array 属性,通过__get和__set来访问
     */
    protected $_attributes;

    /**
     * @var array 原始数据.（不要考虑内存,那不是瓶颈，开发速度才是)
     */
    protected $_original;

    /**
     * @var bool decide if we use soft delete
     * @todo soft delete is moved to the class, due to static methods problem
     */
    const SOFT_DEL_COL_NAME = 'status';
    const SOFT_DEL_ACTIVE = ROW_STATUS_ACTIVE;
    const SOFT_DEL_DELETED = ROW_STATUS_DELETED;


    /**
     * @param array $row_array the queried result from database
     * @throws \Exception
     */
    public function __construct($row_array = array())
    {
        // get ci and redis as static attributes
        parent::__construct();

        if (empty(static::$validate_rules) AND ENVIRONMENT === 'development') {
            throw new \Exception('it is recommended to set validate_rules for resource!');
        }

        // TODO:the read filters, the cacheable filter and uncacheable filter
        if (!empty($row_array)) {
            $this->_set_attributes($row_array);
        }
    }

    public function __get($name)
    {
//        if (ENVIRONMENT != 'production') {
//            log_message('debug', $name . ' is required ');
//            log_message('debug', json_encode($this->_attributes));
//            log_message('debug', $this->_attributes[$name]);
//        }
//        var_dump($this->_attributes);
        return $this->_attributes[$name];
    }

    public function __set($name, $value)
    {
        $this->_attributes[$name] = $value;
    }

    /**
     * Set attributes for the class so we can use it like a orm
     * @param $row_array
     * @throws \Exception
     */
    protected function _set_attributes($row_array)
    {
        if (!isset($row_array[static::PRIMARY_KEY_NAME])) {
            throw new \Exception('must have primary key when _set_attributes!');
        }

        $primary_key_value = 0;
        // 如果有主键值,并且不为0,则一定已经在数据库里了
        if (isset($row_array[static::PRIMARY_KEY_NAME])) {
            $primary_key_value = intval($row_array[static::PRIMARY_KEY_NAME]);
        }

        // TODO:直接给属性赋值,很不安全.
        $this->_attributes = $row_array;
        $this->_original = $row_array;

        if ($primary_key_value) {
            $this->_in_db = TRUE;
            if (!static::DISABLE_CACHE) {
                // Update all the caches
                // update the memory cache, we use array instead of stdClass to avoid the copy by ref to cause trouble
                static::$_cached_rows[(string)$primary_key_value] = $row_array;
                $redis_cache_name = static::get_cache_name($primary_key_value);
                // update the redis cache as well
                static::save_cache($redis_cache_name, $row_array);
            }
        }
    }

    /**
     * Find a new instance
     *
     * @todo the database level cache. which is not so important i think
     * @param $primary_key_value
     * @param $cache_level
     * @return self
     */
    public static function find($primary_key_value, $cache_level = CACHE_LEVEL_MEMORY)
    {
        // init db and redis
        if (!static::$_init_flag) {
            static::init();
        }

        $primary_key_value = (string)$primary_key_value;
        $row = FALSE;
        $user_redis = ($cache_level === CACHE_LEVEL_REDIS) ? TRUE : FALSE;
        $redis_cache_name = static::get_cache_name($primary_key_value);
        // try cache. for different level
        if ($cache_level !== CACHE_LEVEL_NONE) {

            if ($cache_level === CACHE_LEVEL_MEMORY) {
                $row = isset(static::$_cached_rows[$primary_key_value]) ?
                    static::$_cached_rows[$primary_key_value] : FALSE;
                // if memory cache fails, we need to use redis
                if ($row === FALSE) {
                    $user_redis = TRUE;
                }
            }
            if ($cache_level === CACHE_LEVEL_REDIS OR $user_redis) {
                $row = static::get_cache($redis_cache_name);
                if (ENVIRONMENT !== 'production') {
                    log_message('debug', 'using redis');
                }
            }
        }


        // cache missed or require no cache
        if (CACHE_LEVEL_NONE OR !$row) {
            if (ENVIRONMENT !== 'production') {
                log_message('debug', 'using database');
            }
            $row = static::_find($primary_key_value);
        }


        if (!$row) {

            if (ENVIRONMENT !== 'production') {
                log_message('debug', 'row not found');
                log_message('debug', static::$ci->db->last_query());
            }

            return FALSE;
        } else {
            // return a new instance with attributes, the _set_attributes will update all the cache
            return new static($row);
        }
    }

    public function create()
    {
        // use the validator
        if (!$this->validate()) {
            // TODO:throw exception when validate fails?
            return false;
        }
        // TODO:allow the return value to stop the create event
        $this->fire_event('before_create');

        // do the real create
        $id = intval($this->_insert());
        if (!$id) {
            throw new \Exception('create fail  _insert return 0');
        }
        $this->_refresh($id);
        $this->fire_event('after_create');
        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function update()
    {
        // TODO:use validator after the write filter?
        if (!$this->validate()) {
            // TODO:throw exception?
            return false;
        }
        $this->fire_event('before_update');

        $updated = $this->_update();
        // TODO:should we through exception when update fail?
        if (!$updated) {
            throw new \Exception('update fail');
        }

        $id = $this->_attributes[static::PRIMARY_KEY_NAME];
        // reload data and update cache
        $this->_refresh($id);

        $this->fire_event('after_update');
        return $updated;
    }

    public function del()
    {
        // clean the cache and do the soft del
        $this->fire_event('before_del');

        $deleted = $this->_del();
        if (!$deleted) {
            throw new \Exception('delete fail');
        }

        $id = $this->_attributes[static::PRIMARY_KEY_NAME];
        // reload data and update cache
        $this->_refresh($id);

        $this->fire_event('after_del');
        return $deleted;
    }

    public function real_del()
    {
        $this->fire_event('before_real_del');

        $deleted = $this->_del(FALSE);
        if (!$deleted) {
            throw new \Exception('real delete fail');
        }

        $id = $this->_attributes[static::PRIMARY_KEY_NAME];

        // flush all the cache
        $this->_flush_all_cache($id);

        $this->fire_event('after_del');
        return $deleted;
    }

    public function save_to_db()
    {
        if ($this->is_in_db()) {
            return $this->update();
        } else {
            return $this->create();
        }
    }

    /**
     * Update the cache in memory THIS IS NOT MEMCACHE!
     */
    public function save_to_memory_cache()
    {
        static::$_cached_rows[(string)$this->{static::PRIMARY_KEY_NAME}] = $this->to_array();
    }

    /**
     * Save current Resource to redis
     */
    public function save_to_redis()
    {
        $cache_name = static::get_cache_name($this->{static::PRIMARY_KEY_NAME});
        static::save_cache($cache_name, $this->to_array());
    }

    /**
     * If current resource is already in database
     * @return bool
     */
    public function is_in_db()
    {
        return $this->_in_db;
    }

    /**
     * If this Resource is soft deleted
     * @todo in collection, the soft deleted will be considered when select
     * @return bool
     */
    public function is_deleted()
    {
        return $this->{static::SOFT_DEL_COL_NAME} == ROW_STATUS_DELETED;
    }

    /**
     * If current resource is different from the original one
     */
    public function is_dirty()
    {
        if (empty($this->_original_data)) {
            // this should be a new resource, so it is dirty
            return TRUE;
        }
        return !empty($this->get_dirty());
    }


    /**
     * Get the diff
     *
     * @todo support attribute_name
     * @return array
     */
    public function get_dirty()
    {
        $dirty = array();
        // test all attributes
        foreach ($this->_attributes as $key => $current_value) {
            if (!isset($this->_original[$key])) {
                $dirty[$key] = $current_value;
                continue;
            }
            $origin = $this->_original[$key];
            if ($origin !== $current_value) {
                // 处理字符串数字相等但不严格等的问题
                if (is_numeric($origin) AND is_numeric($current_value) AND $origin == $current_value) {
                    continue;
                } else {
                    $dirty[$key] = $current_value;
                }
            }
        }
        return $dirty;
    }

    public function to_array()
    {
        return $this->_attributes;
    }


    /**
     * Get cache name for this resource with given id
     *
     * since it is static method, we cant use the id in the class
     *
     * @param $primary_key_value
     * @return string cache_name without hash ( the hash is done in RestBase)
     */
    public static function get_cache_name($primary_key_value)
    {
        return static::TABLE_NAME . static::PRIMARY_KEY_NAME . $primary_key_value;
    }


    /**
     * Find a row array from database
     */
    protected static function _find($primary_key_value)
    {
        // find by primary key
        static::$ci->db->where(static::PRIMARY_KEY_NAME, $primary_key_value);
        // we only need one result
        return static::$ci->db->limit(1)
            ->get(static::TABLE_NAME)
            ->row_array();
    }


    /**
     * insert new data and return insert_id
     *
     * @return mixed
     * @throws \Exception
     */
    protected function _insert()
    {
        // TODO:what if insert got error
        if (!isset($this->_attributes['update_time'])) {
            $this->_attributes['update_time'] = date("Y-m-d H:i:s");
        }
        static::$ci->db->insert(static::TABLE_NAME, $this->_attributes);
        return static::$ci->db->insert_id();
    }

    /**
     * update one row
     * @return bool FALSE if affected rows = 0
     * @throws \Exception
     */
    protected function _update()
    {

        static::$ci->db->update(static::TABLE_NAME,
            $this->get_dirty(),
            array(static::PRIMARY_KEY_NAME => $this->_attributes[static::PRIMARY_KEY_NAME]));
        return static::$ci->db->affected_rows() === 1;
    }

    /**
     * delete one row permanently or softly
     *
     * @param $soft bool
     * @return bool
     */
    protected function _del($soft = TRUE)
    {
        if ($soft) {
            static::$ci->db->update(static::TABLE_NAME,
                array(static::SOFT_DEL_COL_NAME => ROW_STATUS_DELETED),
                array(static::PRIMARY_KEY_NAME => $this->_attributes[static::PRIMARY_KEY_NAME])
            );
        } else {
            static::$ci->db->delete(static::TABLE_NAME,
                array(static::PRIMARY_KEY_NAME => $this->_attributes[static::PRIMARY_KEY_NAME])
            );
        }
        return static::$ci->db->affected_rows() === 1;
    }

    /**
     * Reset the attributes after create or update, and clean the cache as well
     *
     * @param $primary_key_value int
     */
    protected function _refresh($primary_key_value)
    {
        // _set_attributes will update the cache as well
        $this->_set_attributes(static::_find($primary_key_value));
    }

    protected function _flush_all_cache($primary_key_value)
    {
        $primary_key_value = (string)$primary_key_value;
        // clean memory cache
        if (isset(static::$_cached_rows[$primary_key_value])) {
            unset(static::$_cached_rows[$primary_key_value]);
        }
        // clean redis cache
        $redis_cache_name = static::get_cache_name($primary_key_value);
        static::del_cache($redis_cache_name);
    }

    /**
     * Validate the data before create
     * @todo enable it
     * @return bool
     */
    protected function validate()
    {
        /*
        if (static::$validator === NULL) {
            static::$validator = new Validator();
        }
        static::$validator->validate($this->_attributes, static::$validate_rules);
        return static::$validator->pass();
        */
        return TRUE;
    }

    /**
     * @todo make the event stuff work
     * @param $action
     */
    private function fire_event($action)
    {
        // pass this as payload
        Event::fire('Resource.' . $action . ':' . $this->uri, array($this));
    }
}