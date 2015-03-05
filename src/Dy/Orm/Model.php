<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午10:45
 */
namespace Dy\Orm;

use Dy\Db\Exception\DbException;
use Dy\Orm\Exception\NotDeleted;
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

    /**
     * @todo Return something for the chain method.
     * @param string $order
     * @return array [[field, direction], ...]
     */
    public static function order($order)
    {
        $order = static::_filter_order($order);
        foreach ($order as $val) {
            static::$_ci->db->order_by($val[0], $val[1]);
        }
        return $order;
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
                $name = substr($src, $offset, $pos - $offset);
                $direction = substr($src, $offset - 1, 1);
                if ($direction === '+') {
                    $direction = 'ASC';
                } elseif ($direction === '-') {
                    $direction = 'DESC';
                } else {
                    if ($offset === 1) {
                        $name = $direction . $name;
                    }
                    $direction = 'ASC';
                }
                $name = trim($name);
                if ($name !== '') {
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
     * NOTE: MUST have a paging, default is `['page' => 1, 'per_page' => 10]`.
     *
     * @param array|string $whereRaw
     * @param array|mixed $whereAs
     * @return array ['where' => $where, 'page' => $page, 'per_page' => $perPage]
     */
    public static function where($whereRaw, $whereAs = NULL)
    {
        if (!is_null($whereAs)) {
            if (!is_array($whereRaw)) {
                $whereRaw = array($whereRaw, $whereAs);
            } else {
                $whereRaw = array_merge($whereAs, $whereRaw);
            }
        }
        $page = 1;
        $perPage = 10;
        $where = array();
        foreach ($whereRaw as $key => $value) {
            if ($key === 'select') {
                static::select($value);
            } elseif ($key === 'order') {
                static::order($value);
            } elseif ($key === 'page') {
                $page = max(1, intval($value));
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
                $where[$key] = $value;
            }
        }
        if ($page >= 1) {
            static::paging($page, $perPage);
        }
        static::$_ci->db->where($where);
        return array(
            'where'     => $where,
            'page'      => $page,
            'per_page'  => $perPage
        );
    }

    public static function paging($page = 1, $perPage = 10)
    {
        static::$_ci->db->limit($perPage, ($page - 1) * $perPage);
    }

    public static function countAll()
    {
        return static::$_ci->db->select('count(*) as num')->get(static::TABLE_NAME)->row()->num;
    }

    /**
     * @todo Design the return value.
     * @return object
     */
    public static function get()
    {
        return static::$_ci->db->get(static::TABLE_NAME);
    }

    /**
     * @see \Dy\Orm\Model::where
     * @param $whereRaw
     * @param $whereAs
     * @return array [
     *     'count' => $count,
     *     'result' => [$item0, $item1, ...],
     *     'option' => ['page' => $page, 'per_page' => $perPage]
     * ]
     */
    public static function getWhere($whereRaw, $whereAs)
    {
        $condition = static::where($whereRaw, $whereAs);
        $result = static::get()->result();
        static::$_ci->db->where($condition['where']);
        $count = static::countAll();
        return array(
            'count'  => $count,
            'result' => $result,
            'option' => array(
                'page'     => $condition['page'],
                'per_page' => $condition['per_page']
            )
        );
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
     * Delete a row or rows by primary key(s)
     *
     * @param array|int   $primary_key_values
     * @return int|false  Count of deleted rows. If the query failed, FALSE will be returned.
     * @throws DbException
     */
    public static function destroy($primary_key_values)
    {
        if (!static::$_booted) {
            static::_boot();
        }

        // Use destroy_or_fail actually
        try {
            return static::destroy_or_fail($primary_key_values);
        } catch (DbException $e) {
            throw $e;
        } catch (\Exception $e) {
            return FALSE;
        }
    }

    /**
     * Delete a row or rows by primary key(s) and throw an exception if query fails.
     *
     * @param array|int   $primary_key_values
     * @return int        Count of deleted rows.
     * @throws \InvalidArgumentException
     * @throws NotDeleted If the deletion fails.
     */
    public static function destroy_or_fail($primary_key_values)
    {
        if (!static::$_booted) {
            static::_boot();
        }
        $primary_key_values = is_array($primary_key_values) ? $primary_key_values : func_get_args();

        // Empty array is not allowed in case the whole table is emptied.
        if (empty($primary_key_values)) {
            throw new \InvalidArgumentException('No primary keys are set for deleting');
        }

        // We'll actually add where conditions and move the real delete work to another method.
        static::$_ci->db->where_in(static::PRIMARY_KEY_NAME, $primary_key_values);
        $count = static::_real_destroy();

        // Throws exception if no rows are affected
        if ($count == 0) {
            throw new NotDeleted($primary_key_values);
        }
        return $count;
    }

    /**
     * Internal method for deleting rows.
     *
     * @return int Count of deleted rows.
     */
    private static function _real_destroy()
    {
        static::$_ci->db->delete(static::TABLE_NAME);
        return static::$_ci->db->affected_rows();
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