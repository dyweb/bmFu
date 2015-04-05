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
use Dy\Orm\Exception\NotExists;
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

    public static function select($select = '*')
    {
        if ($select === '*') {
            $select = static::$_white_list;
        } else {
            $select = static::_filter_select($select);
        }
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
     */
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

    public static function where($whereRaw, $whereAs = NULL)
    {
        static::$_ci->db->where(static::_filter_where(is_array($whereRaw) ? $whereRaw : array($whereRaw => $whereAs)));
    }

    private static function _filter_where($whereRaw)
    {
        $where = array();
        foreach ($whereRaw as $key => $value) {
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
        return $where;
    }

    public static function paging($page = 1, $perPage = 10)
    {
        static::limit(($page - 1) * $perPage, $perPage);
    }

    public static function limit($offset, $number)
    {
        static::$_ci->db->limit($number, $offset);
    }

    public static function countAll()
    {
        static::$_ci->db->select('count(*) as num');
        return static::get()->row()->num;
    }

    /**
     * @todo Design the return value.
     * @return object
     */
    public static function get()
    {
        return static::_real_get();
    }

    /**
     * @param $whereRaw
     * @param $default
     * @return array [
     *     'count' => $count,
     *     'result' => [$item0, $item1, ...],
     *     'option' => ['page' => $page, 'per_page' => $perPage]
     * ]
     */
    public static function getWhere($whereRaw, $default = array())
    {
        $whereRaw = array_merge(array_merge(array(
            'select' => '*',
            'order' => '',
            'page' => 1,
            'per_page' => 10
        ), $default), $whereRaw);

        static::select($whereRaw['select']);
        if ($whereRaw['order']) {
            static::order($whereRaw['order']);
        }
        static::paging(
            $page = max(1, intval($whereRaw['page'])),
            $perPage = min(50, max(1, intval($whereRaw['per_page'])))
        );

        unset($whereRaw['select']);
        unset($whereRaw['order']);
        unset($whereRaw['page']);
        unset($whereRaw['per_page']);

        $where = static::_filter_where($whereRaw);

        static::$_ci->db->where($where);
        $result = static::get()->result();

        static::$_ci->db->where($where);
        $count = static::countAll();

        return array(
            'count'  => $count,
            'result' => $result,
            'option' => array(
                'page'     => $page,
                'per_page' => $perPage
            )
        );
    }

    /**
     * Perform the real getting.
     *
     * @return object result object
     */
    protected static function _real_get()
    {
        return static::$_ci->db->get(static::TABLE_NAME);
    }

    /**
     * Find one record by primary_key_value.
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
        static::$_ci->db->limit(1);
        $record = static::get()->row_array();
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
        $primary_key_values = is_array($primary_key_values) ? $primary_key_values : func_get_args();

        // Use destroy_or_fail actually
        try {
            return static::destroyOrFail($primary_key_values);
        } catch (\Exception $e) {
            return FALSE;
        }
    }

    /**
     * Delete certain rows according to where clauses and number limitation.
     *
     * @param array|string  $whereRaw where clause; note an empty argument is not allowed
     * @param int           $limit the maximum number of rows to be deleted, positive numbers only
     * @return int          Count of deleted rows.
     * @throws \InvalidArgumentException
     * @throws DbException
     * @throws NotDeleted If the deletion fails.
     */
    public static function destroyWhere($whereRaw, $limit = 1)
    {
        if (!static::$_booted) {
            static::_boot();
        }

        // Check arguments for security
        if (empty($whereRaw)) {
            throw new \InvalidArgumentException('Empty where statement is not allowed');
        }
        if ($limit <= 0) {
            throw new \InvalidArgumentException('Limit has to be set when destroying');
        }

        // Execute the query
        self::where($whereRaw);
        self::limit(0, $limit);
        $count = static::_real_destroy();

        // Throws exception if no rows are affected
        if ($count == 0) {
            throw new NotDeleted($whereRaw);
        }
        return $count;
    }

    /**
     * Delete a row or rows by primary key(s) and throw an exception if query fails.
     *
     * @param array|int   $primary_key_values
     * @return int        Count of deleted rows.
     * @throws \InvalidArgumentException
     * @throws NotDeleted If the deletion fails.
     */
    public static function destroyOrFail($primary_key_values)
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
    protected static function _real_destroy()
    {
        static::$_ci->db->delete(static::TABLE_NAME);
        return static::$_ci->db->affected_rows();
    }

    /**
     * @return int primary_key_value for the created or updated record.
     *
     * @throws NotModified
     * @throws NotSaved
     */
    public function save()
    {
        if (!empty($this->_attributes)) {
            if (!$this->exists()) {
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

        $this->_real_insert();
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

    /**
     * Perform the real insertion.
     *
     * @throws NotSaved
     */
    protected function _real_insert()
    {
        try {
            static::$_ci->db->insert(static::TABLE_NAME, $this->_attributes);
        } catch (\Exception $e) {
            throw new NotSaved($e->getMessage());
        }
    }

    public function update()
    {
        $dirty = $this->_get_dirty();
        if (empty($dirty)) {
            throw new NotModified();
        }

        $this->_attributes['update_time'] = date("Y-m-d H:i:s");
        $this->_real_update($dirty);

        // check if really saved. in production and dev environment ci's database doesn't throw error.
        $affected_rows = static::$_ci->db->affected_rows();
        if ($affected_rows !== 1) {
            throw new NotSaved();
        }

        return $this->_attributes[static::PRIMARY_KEY_NAME];
    }

    /**
     * Perform the real update.
     *
     * @param array $dirty Associative array of values to be updated.
     * @throws NotSaved
     */
    protected function _real_update($dirty)
    {
        try {
            static::$_ci->db->update(static::TABLE_NAME,
                $dirty,
                array(static::PRIMARY_KEY_NAME => $this->_attributes[static::PRIMARY_KEY_NAME]));
        } catch (\Exception $e) {
            throw new NotSaved($e->getMessage());
        }
    }

    /**
     * Delete the current object.
     *
     * @todo Update the state of other models if id of another model equals id of mine.
     * @return bool Whether the process succeeds.
     */
    public function delete()
    {
        if (!$this->exists()) {
            return FALSE;
        }

        try {
            $this->deleteOrFail();
        } catch (\Exception $e) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Delete the current object and throw an exception when it fails.
     *
     * @todo Update the state of other models if id of another model equals id of mine.
     * @throws NotDeleted
     * @throws NotExists
     * @return bool Whether the process succeeds.
     */
    public function deleteOrFail()
    {
        if (!$this->exists()) {
            throw new NotExists('The current object does\'t exist.');
        }

        static::$_ci->db->where(static::PRIMARY_KEY_NAME, $this->_attributes[static::PRIMARY_KEY_NAME]);
        $this->_real_delete();
        $this->_original = array();
        return TRUE;
    }

    /**
     * Perform the real deletion.
     *
     * @throws NotDeleted
     */
    protected function _real_delete()
    {
        try {
            static::$_ci->db->delete(static::TABLE_NAME);
            unset($this->_attributes[static::PRIMARY_KEY_NAME]);
        } catch (\Exception $e) {
            throw new NotDeleted(array($this->_attributes[static::PRIMARY_KEY_NAME]));
        }
    }

    /**
     * Find whether the object exists(not deleted).
     * Note: This method doesn't query the db for a second time.
     *
     * @return bool Whether the object exists
     */
    public function exists()
    {
        return !empty($this->_original);
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