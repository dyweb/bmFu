<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-3-3
 * Time: 下午4:02
 */

namespace Dy\Orm;

trait Page
{
    private $_default_page_size = 10;

    final public function can_page()
    {
        return true;
    }

    /**
     * sanitize the page input and return the right number
     *
     * @todo do the real pagination here as well
     * @param $page
     * @param $per_page
     * @return array
     */
    final public function page($page, $per_page)
    {
        // sanitize the input.
        $page = intval($page);
        $per_page = intval($per_page);
        if ($page <= 0) {
            $page = 1;
        }
        if ($per_page <= 0) {
            $per_page = $this->_default_page_size;
        }
        return array($page, $per_page);
    }
}