<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-11-19
 * Time: 下午11:20
 */
namespace Dy;

final class Event
{
    private static $_events = array();
    /**
     * @var \Redis
     */
    protected static $_redis = null;
    /**
     * @var \CI_Dummy
     */
    public static $ci = null;

    /**
     * @todo allow pr and prevent for events
     * @param $eventName
     * @param $callback
     */
    public static function listen($eventName, $callback)
    {
        if (!isset(self::$_events[$eventName])) {
            self::$_events[$eventName] = array($callback);
        } else {
            self::$_events[$eventName][] = $callback;
        }
    }

    /**
     * @todo allow pr and prevent for events
     * @param $eventName
     * @param array $payload
     */
    public static function fire($eventName, $payload = array())
    {
        // check all the listeners
        if (isset(self::$_events[$eventName])) {
            foreach (self::$_events[$eventName] as $callback) {
                call_user_func_array($callback, $payload);
            }
        }
    }

    /**
     * publish message to channel
     *
     * @param string $channelName
     * @param string|array|object $pack
     * @return int
     */
    public static function pub($channelName, $pack)
    {
        if (!is_string($pack)) {
            $pack = json_encode($pack);
        }
        // load ci
        // load redis_lib and get its client
        if (is_null(self::$ci)) {
            self::$ci = &get_instance();
            self::$ci->load_library('redis_lib');
            self::$_redis = self::$ci->redis_lib->client;
        }

        // do the pub, return the number of clients that got the message
        return self::$_redis->publish($channelName, $pack);
    }
}