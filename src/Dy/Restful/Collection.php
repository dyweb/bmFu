<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-11-24
 * Time: 上午10:37
 */
namespace Dy\Restful;
/**
 * Class Collection
 *
 * collection is a group of resources and used for read only. allow pagination
 *
 * @package Dy\Restful
 * @todo resource id. eg: the group's topic need to have the group id
 *
 */
abstract class Collection extends RestBase
{

    /*
    * @var int the count of this collection
    * -1 is used as a flag for init
    */
    protected $_count = -1;

    // data is readonly so we mark it as protected. it must be an array
    protected $data;

    protected $_is_empty = TRUE;

    // for pagination
    private $_page_number;
    private $_per_page;
    protected $_order_by_column = '';
    protected $_order_by_order = '';
    protected $number = 10;
    protected $offset = 0;


    // get the real count from database, instead of the cached one in class or in redis
    /**
     * @return int
     */
    abstract public function count_real();

    /**
     * @return array
     */
    abstract public function get_real();

    // like add one topic to topic collections
//    abstract public function add_resource(Resource $resource);

    // set the ids that will be used for this collection
    // we need to use these id to update the namespace and
    // eg: namespace is v2/talk/group/{group_id}/topic
    // then set_ids(array('group_id'=>123);
    public function __construct($ids = array('sample_id' => 1))
    {
        parent::__construct();
        $this->assign_ids($ids);
        $this->disable_all_filters(); // disable filters by default
        $this->count();// init the count
    }

    // get the count of current collection from cache.
    public function count($use_cache = TRUE)
    {
        $count = $this->_count;
        if ($count === -1) {
            // init the count
            $cache_name = md5($this->uri . 'collection');
            $cached_count = $use_cache ? $this->redis->get($cache_name) : FALSE;
            if ($cached_count === FALSE) {
                $count = $this->count_real();
                if ($this->cache_time === -1) {
                    $this->redis->set($cache_name, $count); // TODO:if save it forever, then auto update is very important
                } else {
                    $this->redis->setex($cache_name, $this->cache_time, $count);
                }
            } else {
                $count = intval($count);
            }
            $this->_count = $count;
        }
        return $count;
    }

    // call this from event
    public function update_count()
    {
        $this->_count = -1;
        $this->count(FALSE);
    }

    // TODO:order for collections

    public function page($page_number)
    {
        $this->_page_number = intval($page_number);
        return $this;
    }

    public function per_page($num)
    {
        $this->_per_page = intval($num);
        return $this;
    }

    public function get($use_cache = TRUE)
    {
        $cached_data = FALSE;
        if ($use_cache) {
            $cached_data = $this->get_cache();
        }
        if ($cached_data === FALSE) {
            // get the newest data from db

            // calc the number and offset so the get_real could use it
            $this->offset = ($this->_page_number - 1) * $this->_per_page;
            $this->number = $this->_per_page;

            $cached_data = $this->get_real();

            $this->data = $cached_data;
            // apply filter
            if (!empty($this->data) AND $this->read_filter_enabled) {
                $this->read_filter();
            }
            $this->save_cache($this->data);
        } else {
            $this->data = $cached_data;
        }
        $is_empty = empty($this->data);
        if (!$is_empty AND $this->read_un_cache_filter_enabled) {
            $this->read_un_cache_filter();
        }
        $this->_is_empty = $is_empty;
        return $is_empty;
    }

    public function is_empty()
    {
        return $this->_is_empty;
    }

    public function get_cache_name()
    {
        return $this->uri . $this->_order_by_column . $this->_order_by_order
        . $this->_page_number . $this->_per_page . 'collection';
    }


}