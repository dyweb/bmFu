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
use Dy\Orm\Exception\UnknownColumn;

/**
 * Class Model
 * @package Dy\Orm
 */
abstract class Model
{
    const TABLE_NAME = '';
    const PRIMARY_KEY_NAME = 'id';
    const DEFAULT_PAGE_SIZE = 10;

    protected static $_booted = false;
    /**
     * @var \FakeCI
     */
    protected static $_ci;
    protected static $_redis;
    protected static $_white_list = array();

    protected $_attributes = array();
    protected $_original = array();

    public function __construct($attributes = array())
    {
        if (empty(static::TABLE_NAME)) {
            $class_name = get_called_class();
            throw new \Exception('Model ' . $class_name . ' must have table name');
        }
        // assign the attributes
        // TODO: how to know if attributes are from database not user input?
        if (!empty($attributes)) {
            $this->_set_attributes($attributes);
        }
    }

    private static function _check_field($name)
    {
        if (in_array($name, static::$_white_list)) {
            return TRUE;
        } else {
            throw new UnknownColumn($name);
        }
    }

    /**
     *
     * @todo Exception when attribute not exists?
     *
     * @param $name
     * @throws UnknownColumn
     * @return mixed
     */
    public function __get($name)
    {
        static::_check_field($name);
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

    public static function select($select)
    {
        $select = static::_filter_select($select);
        if (count($select)) {
            static::$_ci->db->select($select);
        }
    }

    private static function _filter_select($select)
    {
        if (is_string($select)) {
            $select = explode(',', $select);
        }
        $selectFiltered = array();
        foreach ($select as $val) {
            $val = trim($val);
            if ($val === '') {
                continue;
            }
            static::_check_field($val);
            $selectFiltered[] = $val;
        }
        return $selectFiltered;
    }

    public static function order($order)
    {
        $order = static::_filter_order($order);
        foreach ($order as $val) {
            static::$_ci->db->order_by($val[0], $val[1]);
        }
    }

    private static function _filter_order($order)
    {
        if (is_string($order)) {
            $src = $order;
            $order = array();
            $offset = 0;
            $len = strlen($src);
            while ($offset < $len) {
                $offset += 1;
                $pos = static::_strpos_or($src, array('+', '-', ',', ' '), $offset);
                $name = trim(substr($src, $offset, $pos - $offset));
                if ($name !== '') {
                    $direction = substr($src, $offset - 1, 1);
                    if ($direction === '+') {
                        $direction = 'ASC';
                    } elseif ($direction === '-') {
                        $direction = 'DESC';
                    } else {
                        if ($offset === 1) {
                            $name = ltrim($direction . $name);
                        }
                        $direction = 'ASC';
                    }
                    static::_check_field($name);
                    $order[] = array($name, $direction);
                }
                $offset = $pos;
            }
        } else {
            foreach ($order as $val) {
                static::_check_field($val[0]);
            }
        }
        return $order;
    }

    /**
     * @param string $haystack
     * @param array $needles
     * @param int $offset
     * @return int the position as an integer or length of haystack if needle not found.
     */
    private static function _strpos_or($haystack, $needles, $offset = 0)
    {
        $posMin = strlen($haystack);
        foreach ($needles as $needle) {
            $pos = strpos($haystack, $needle, $offset);
            if ($pos !== FALSE AND $pos < $posMin) {
                $posMin = $pos;
            }
        }
        return $posMin;
    }

    /**
     * `where([], [])` will take $whereAs as default, e.g. `where($_GET, ['per_page' => 30])`.
     * `where(string $key, string $value)` is same as `where([$key => $value])`.
     * `where([])` is the most common style.
     *
     * Support keyword 'select', 'order', 'page' and 'per_page'.
     * NOTE: No pagination will be produced if `page === 'nopage'`.
     *
     * @param array|string $where
     * @param array|mixed $whereAs
     */
    public static function where($where, $whereAs = NULL)
    {
        if (!is_null($whereAs)) {
            if (!is_array($where)) {
                $where = array($where, $whereAs);
            } else {
                $where = array_merge($whereAs, $where);
            }
        }
        $page = 1;
        $perPage = 10;
        foreach ($where as $key => $value) {
            if ($key === 'select') {
                static::select($value);
            } elseif ($key === 'order') {
                static::order($value);
            } elseif ($key === 'page') {
                if ($value === 'nopage') {
                    $page = 0;
                } else {
                    $page = max(1, intval($value));
                }
            } elseif ($key === 'per_page') {
                $perPage = max(1, intval($value));
            } else {
                static::_check_field($key);
                switch (substr($value, 0, 2)) {
                    case '>=':          $key .= '>=';   $value = substr($value, 2); break;
                    case '<=':          $key .= '<=';   $value = substr($value, 2); break;
                    default:
                        switch (substr($value, 0, 1)) {
                            case '!':   $key .= '!=';   $value = substr($value, 1); break;
                            case '>':   $key .= '>';    $value = substr($value, 1); break;
                            case '<':   $key .= '<';    $value = substr($value, 1);
                        }
                }
                static::$_ci->db->where($key, $value);
            }
        }
        if ($page >= 1) {
            static::paging($page, $perPage);
        }
    }

    public static function paging($page = 1, $perPage = 10)
    {
        static::$_ci->db->limit($perPage, ($page - 1) * $perPage);
    }

    public static function get()
    {
        return static::$_ci->db->get(static::TABLE_NAME);
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
     * @return int primary_key_value for the created or updated record
     * @throws NotModified
     * @throws NotSaved
     */
    public function save()
    {
        if (!empty($this->_attributes)) {
            if (empty($this->_original)) {
                return $this->create();
            } else {
                return $this->update();
            }
        } else {
            throw new NotModified();
        }
    }

    public function create()
    {
        // add the timestamps
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

        // TODO:should we refetch everything from database?
        $this->_attributes[static::PRIMARY_KEY_NAME] = $id;
        $this->_original = $this->_attributes;

        return $id;
    }

    public function update()
    {
        $dirty = $this->_get_dirty();
        if (empty($dirty)) {
            throw new NotModified();
        }

        $this->_attributes['update_time'] = date("Y-m-d H:i:s");

        try {
            static::$_ci->db->update(static::TABLE_NAME,
                $dirty,
                array(static::PRIMARY_KEY_NAME => $this->_attributes[static::PRIMARY_KEY_NAME]));
        } catch (\Exception $e) {
            throw new NotSaved($e->getMessage());
        }

        // check if really saved. in production and dev environment ci's database doesn't throw error.
        $affected_rows = static::$_ci->db->affected_rows();
        if ($affected_rows !== 1) {
            throw new NotSaved();
        }

        return $this->_attributes[static::PRIMARY_KEY_NAME];
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

    protected function _get_dirty()
    {
        $dirty = array();
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
}