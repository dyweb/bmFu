<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-12-1
 * Time: 下午2:33
 */
define('ENVIRONMENT', 'development');
define('PHPUNIT_FOR_CI', 1);
define('BASEPATH', __DIR__ . '/../../dev/system/');
define('APPPATH', __DIR__ . '/');

function get_config()
{
}

// TODO:store the log file and flush it at one time
function log_message($level, $msg)
{
    $level = strtolower($level);
    $file_name = __DIR__ . '/logs/' . date("Y_m_d") . "_{$level}.php";
    if (!file_exists($file_name)) {
        file_put_contents($file_name, 'create log file ' . PHP_EOL);
    }
    file_put_contents($file_name, $level . ' - ' . date("Y-m-d H:i:s") . ' --- ' . $msg . PHP_EOL, FILE_APPEND);
}

// load the real ci database stuff
// TODO:let the database class display error or throw exception
require_once(__DIR__ . '/../vendor/at15/codeignitordb/ci/system/database/DB.php');
// load the redis lib
require_once(__DIR__ . '/../vendor/at15/codeignitordb/ci/application/libraries/Redis_lib.php');

class FakeConfig
{
    public function load()
    {
        return FALSE;
    }
}

class FakeCI
{
    /**
     * @var \CI_DB
     */
    public $db;
    public $config;
    public $redis_lib;
    protected static $_instance = null;
    protected static $_db = null;

    public function __construct()
    {
        // 大家共享一个连接,减少内存消耗和连接数
        if (self::$_db === null) {
            $dbConfig = require_once(__DIR__ . '/config/database.php');
            $driver = $dbConfig['driver'];
            $host = $dbConfig['host'];
            $username = $dbConfig['username'];
            $password = $dbConfig['password'];
            $database = $dbConfig['database'];
            // load database
            self::$_db = &\DB("{$driver}://{$username}:{$password}@{$host}/{$database}", true);
        }
        $this->db = &self::$_db;
        $this->db->db_debug = TRUE;

        // load the redis lib
        $this->redis_lib = new Redis_lib();
    }

    public static function &get_instance()
    {
        if (static::$_instance === null) {
            static::$_instance = new self();
        }
        return static::$_instance;
    }
}

// return the ci instance
function &get_instance()
{
    return FakeCI::get_instance();
}