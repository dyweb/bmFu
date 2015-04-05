<?php
/**
 * Created by PhpStorm.
 * User: HJM
 * Date: 2015/3/5
 * Time: 22:43
 */

namespace Dy\Orm;

use \Dy\Orm\Exception\NotExists;
use \Dy\Orm\Exception\NotSaved;

/**
 * Trait SoftDelete
 * The trait is set to implement soft deletion.
 *
 * @see Dy\Orm\Model
 * @package Dy\Orm
 */
trait SoftDelete
{
    /**
     * Name of soft delete column.
     * @var string
     */
    protected static $_soft_delete_column = 'status';
    /**
     * Enumeration for soft delete values.
     * @var array Associative array.
     */
    protected static $_soft_delete_values = array(
        false => ROW_STATUS_ACTIVE,
        true => ROW_STATUS_DELETED
    );

    /**
     * Switch of soft delete filter.
     * @var bool
     */
    protected static $_use_soft_delete = true;
    /**
     * Switch of forced deleting.
     * @var bool
     */
    protected static $_forced_delete = false;

    /**
     * Include trashed items when getting items.
     *
     * @see onlyTrashed()
     * @see trashed()
     */
    public static function withTrashed()
    {
        static::$_use_soft_delete = false;
    }

    /**
     * Filtering trashed items only when getting items.
     *
     * @see withTrashed()
     * @see trashed()
     */
    public static function onlyTrashed()
    {
        static::$_use_soft_delete = false;
        static::$_ci->db->where(static::$_soft_delete_column, static::$_soft_delete_values[true]);
    }

    /**
     * Delete the item without using soft deleting.
     *
     * @return bool Whether the process succeeds.
     * @throws \Dy\Orm\Exception\NotDeleted
     * @throws \Dy\Orm\Exception\NotExists
     * @throws \Exception
     */
    public function forceDelete()
    {
        static::$_forced_delete = true;
        $result = false;
        try {
            $result = $this->delete();
        } catch (\Exception $e) {
            throw $e;
        } finally {
            static::$_forced_delete = false;
        }
        return $result;
    }

    /**
     * Restore a soft deleted item to the active status.
     *
     * @return bool Whether the process succeeds.
     * @throws \Dy\Orm\Exception\NotExists
     * @throws \Dy\Orm\Exception\NotModified
     * @see trashed()
     */
    public function restore()
    {
        if (!$this->exists()) {
            throw new NotExists('The current object does\'t exist.');
        }

        $this->_attributes[static::$_soft_delete_column] = static::$_soft_delete_values[false];
        return ($this->save() !== false);
    }

    /**
     * Get whether the item has been softly deleted.
     *
     * @return bool Whether the item has been softly deleted.
     * @see withTrashed()
     * @see onlyTrashed()
     * @see restore()
     */
    public function trashed()
    {
        if (!$this->exists()) {
            return true;
        }
        return $this->_attributes[static::$_soft_delete_column] == static::$_soft_delete_values[true];
    }

    /**
     * Perform the real getting.
     *
     * @return object result object
     */
    protected static function _real_get()
    {
        static::_filter_soft_delete();
        return static::$_ci->db->get(static::TABLE_NAME);
    }

    /**
     * Internal method for deleting rows.
     *
     * @return int Count of deleted rows.
     */
    protected static function _real_destroy()
    {
        if (static::$_forced_delete) {
            static::$_forced_delete = false;
            static::$_ci->db->delete(static::TABLE_NAME);
        } else {
            static::$_ci->db->update(static::TABLE_NAME, array(
                static::$_soft_delete_column => static::$_soft_delete_values[true],
                'update_time' => date("Y-m-d H:i:s")
            ));
        }
        return static::$_ci->db->affected_rows();
    }

    /**
     * Add the filter for soft delete before querying.
     */
    protected static function _filter_soft_delete()
    {
        if (static::$_use_soft_delete) {
            static::$_ci->db->where(static::$_soft_delete_column, static::$_soft_delete_values[false]);
        }
        static::$_use_soft_delete = true;
    }

    /**
     * Perform the real insertion.
     *
     * @throws \Dy\Orm\Exception\NotSaved
     */
    protected function _real_insert()
    {
        $this->_attributes[static::$_soft_delete_column] = static::$_soft_delete_values[false];
        try {
            static::$_ci->db->insert(static::TABLE_NAME, $this->_attributes);
        } catch (\Exception $e) {
            throw new NotSaved($e->getMessage());
        }
    }

    /**
     * Perform the real deletion.
     *
     * @throws \Dy\Orm\Exception\NotDeleted
     */
    protected function _real_delete()
    {
        try {
            if (static::$_forced_delete) {
                static::$_forced_delete = false;
                static::$_ci->db->delete(static::TABLE_NAME);
                $this->_original = array();
                unset($this->_attributes[static::PRIMARY_KEY_NAME]);
            } else {
                $this->_attributes = $this->_original;
                $this->_attributes[static::$_soft_delete_column] = static::$_soft_delete_values[true];
                $this->save();
            }
        } catch (\Exception $e) {
            throw new NotDeleted(array($this->_attributes[static::PRIMARY_KEY_NAME]));
        }
    }
}