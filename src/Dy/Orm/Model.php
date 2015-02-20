<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午10:45
 */
namespace Dy\Orm;

use Dy\Orm\Exception\NotFound;
use Dy\Orm\Exception\NotModified;
use Dy\Orm\Exception\NotSaved;

/**
 * Class Model
 * @package Dy\Orm
 */
abstract class Model
{
    const TABLE_NAME = '';
    const PRIMARY_KEY_NAME = 'id';

    protected static $_booted = false;
    protected static $_ci;
    protected static $_redis;

    protected $_attributes = array();
    protected $_original = array();

    public function __construct($attributes = array())
    {
        if (empty(static::TABLE_NAME)) {
            $class_name = get_called_class();
            throw new \Exception("Model {$class_name} must have table name");
        }
        // assign the attributes
        // TODO: how to know if attributes are from database not user input?
        if (!empty($attributes)) {
            $this->_set_attributes($attributes);
        }
    }

    /**
     *
     * @todo Exception when attribute not exists?
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->_attributes[$name];
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->_attributes[$name] = $value;
    }

    /**
     * Find one record by primary_key_value
     *
     * @param $primary_key_value
     * @return static
     * @throws NotFound
     */
    public static function find($primary_key_value)
    {
        if (!static::$_booted) {
            static::_boot();
        }
        static::$_ci->db->where(static::PRIMARY_KEY_NAME, $primary_key_value);
        // we must add limit 1 to avoid ci to get more result
        $record = static::$_ci->db->limit(1)
            ->get(static::TABLE_NAME)
            ->row_array();
        if (empty($record)) {
            throw new NotFound($primary_key_value);
        }

        return new static($record);
    }

    /**
     * @throws NotModified
     */
    public function save()
    {
        if (!empty($this->_attributes)) {
            if (empty($this->_original)) {
                $this->create();
            } else {
                $this->update();
            }
        } else {
            throw new NotModified();
        }
    }

    public function create()
    {
        if (!isset($this->_attributes['update_time'])) {
            $current_time = date("Y-m-d H:i:s");
            $this->_attributes['create_time'] = $current_time;
            $this->_attributes['update_time'] = $current_time;
        }

        try {
            static::$_ci->db->insert(static::TABLE_NAME, $this->_attributes);
        } catch (\Exception $e) {
            throw new NotSaved($e->getMessage());
        }

        $id = intval(static::$_ci->db->insert_id());

        // in production, ci's database wont have exception so we check the id.
        if (!$id) {
            throw new NotSaved();
        }

        $this->_attributes[static::PRIMARY_KEY_NAME] = $id;
        $this->_original = $this->_attributes;
    }

    public function update()
    {

    }

    protected static function _boot()
    {
        // get ci ... since we are using ci...
        static::$_ci = &get_instance();
        static::$_redis = static::$_ci->redis_lib->client;
    }

    protected function _set_attributes($attributes)
    {
        if (!isset($attributes[static::PRIMARY_KEY_NAME])) {
            throw new \Exception('must have primary key when _set_attributes!');
        }
        // TODO:it's not safe to assign the values directly
        $this->_attributes = $attributes;
        $this->_original = $attributes;
    }


}