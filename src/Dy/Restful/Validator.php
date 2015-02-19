<?php
// copied from Dy\Tq. need to improve it so it works with the restful resources
namespace Dy\Restful;

/**
 * Class Validator
 * @package Dy\Restful
 */
final class Validator
{
    static $regular_expressions = array(
        // 从laravel里抄的,\p可以用于支持unicode
        'alpha' => '/^[\pL\pM]+$/u',
        'alpha_num' => '/^[\pL\pM\pN]+$/u',
        // 这个其实同时也允许了数字和下划线
        'alpha_chinese' => '/^([\x{4e00}-\x{9fa5}]|[a-zA-Z0-9])*$/u',
        'user_name' => '/^([\x{4e00}-\x{9fa5}]|[a-zA-Z0-9]){1}([\x{4e00}-\x{9fa5}]|[a-zA-Z0-9_])*$/u',
        // 这个email的正则不太对吧,怎么这么长(PHP还有个filter_var的功能额)
        'email' => '/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[\.][a-z]{2,3}([\.][a-z]{2})?/i',
        'phone' => '/^1[34587]{1}\d{9}$/',
        // mobile和phone是一样的，鉴于没人用座机来注册额
        'mobile' => '/^1[34587]{1}\d{9}$/',
        'chinese' => '/^[\x{4e00}-\x{9fa5}]*$/u',
        'password' => '/((?=.*\d)(?=.*\D)|(?=.*[a-zA-Z])(?=.*[^a-zA-Z]))^.{6,16}$/'
    );

    protected $error_messages = array();
    protected $passed = TRUE;


    // 设置成public便于测试
    public $current_name = '';
    public $current_value = '';
    public $current_rule = array();

    /**
     * $data = array('name'=>'jack') $rules = array('jack'=> array('required'=>true))
     * @param $data
     * @param $rules
     * @throws \Exception
     */
    public function validate($data, $rules)
    {
        if (!is_array($rules)) {
            throw new \Exception('validate rules is not set and must be array');
        }
        // make the $data in array form
        // TODO:or just use the stdClass for efficient? i don't think this would be the neck
        if (is_object($data)) {
            $data = json_decode(json_encode($data), TRUE);
        }

        $this->passed = TRUE;
        // 记得清空错误信息
        $this->error_messages = array();

        // 用json_decode的时候记得把第二个参数设置为true,否则不是关联数组
        if (!is_array($data)) {
            // 关闭了error提示的情况下exception会被忽略
            $this->passed = FALSE;
            throw new \Exception('validate data is not set and must be array.
            json_decode need to set the second param TRUE to return array');

        }

        // 然后来一项一项的检查
        foreach ($rules as $name => $rule) {
            $this->current_name = $name;

            // 校验confirm(如果name是以confirm结尾的就不要管它了)
            if ('confirm' === substr($name, -7, 7)) {
                continue;
            }

            $this->current_value = $value = isset($data[$name]) ? $data[$name] : NULL;
            // TODO:is the & useful here?
            $this->current_rule = &$rule;

            $this->validate_required();
            // 如果没有值的话下面的校验就没用了
            if ($value === NULL) {
                continue;
            }

            // 检查confirm
            if (isset($rule['confirm'])) {
                if (!isset($data[$rule['confirm']]) OR $value !== $data[$rule['confirm']]) {
                    $err_msg = isset($this->current_rule['desc']) ?
                        $this->current_rule['desc'] : $this->current_name . '不一致';
                    $this->add_error($this->current_name, VALIDATE_ERROR_CONFIRM, $err_msg);
                    // 不一致的话就没必要检查下面的了....
                    continue;
                }
            }

            // 如果有枚举就忽略type(枚举统一都是int,在Constant里定义)
            if (isset($rule['enum'])) {
                $this->validate_enum();
            } else {
                // TODO:filter_var,还得有个默认的吧，要是我不想写type怎么办
                $this->validate_type();
            }

            // 然后检查长度,字符串要用mb_string的东西吧?
            if (isset($rule['min_length']) OR isset($rule['max_length'])) {
                $this->validate_length();
            }

            // TODO:检查值的范围 range?

            // 检查自定义的正则
            if (isset($rule['regexp']) AND !preg_match($rule['regexp'], $value)) {
                $err_msg = isset($this->current_rule['desc']) ?
                    $this->current_rule['desc'] : $this->current_name . '不符合格式';
                $this->add_error($this->current_name, VALIDATE_ERROR_REGEXP, $err_msg);
            }
        }

        // 用于防止忽略error之后前面的exception被忽略
        if ($this->passed) {
            $this->passed = empty($this->error_messages);
        }
    }

    public function validate_required()
    {
        $e = FALSE;
        if (isset($this->current_rule['required']) AND $this->current_rule['required'] !== FALSE) {
            if ($this->current_value === NULL) {
                $e = TRUE;
            }
            if (empty($this->current_value)) {
                if (isset($this->current_rule['allow_zero']) AND $this->current_rule['allow_zero'] === TRUE) {
                    $e = FALSE;
                } else {
                    $e = TRUE;
                }
            }
        }
        if ($e) {
            $err_msg = isset($this->current_rule['desc']) ?
                $this->current_rule['desc'] : $this->current_name . '不能为空';
            $this->add_error($this->current_name, VALIDATE_ERROR_REQUIRED, $err_msg);
            return FALSE;
        }
        return TRUE;
    }

    // 允许email手机之类的
    // 验证码也被认为是一种特殊的type
    public function validate_type()
    {
        if (!isset($this->current_rule['type'])) {
            return TRUE;
        }

        $type = $this->current_rule['type'];
        $e = FALSE; // 默认是没错的


        // 匹配常用正则
        if (isset(self::$regular_expressions[$type])) {
            $e = !preg_match(self::$regular_expressions[$type], $this->current_value);
        }

        if ($type === 'int' OR $type === 'integer') {
            if (intval($this->current_value) === 0) {
                if (isset($this->current_rule['allow_zero']) AND $this->current_rule['allow_zero'] === TRUE) {
                    $e = FALSE;
                } else {
                    $e = TRUE;
                }
            } else {
                $e = FALSE;
            }
        }

        if ($type === 'string' OR $type === 'str') {
            if (!is_string($this->current_value)) {
                $e = TRUE;
            }
        }

        // TODO:other type,eg:array,json?....
        if ($e) {
            $err_msg = isset($this->current_rule['desc']) ?
                $this->current_rule['desc'] : $this->current_name . '不符合要求';
            $this->add_error($this->current_name, VALIDATE_ERROR_TYPE, $err_msg);
            return FALSE;
        }
        return TRUE;
    }


    public function validate_enum()
    {
        $value = intval($this->current_value);
        // TODO:数字的key,没有问题,字符串和数字都会识别
        if (!isset($this->current_rule['enum'][$value])) {
            $err_msg = isset($this->current_rule['desc']) ? $this->current_rule['desc'] : $this->current_name . '不符合要求';
            $this->add_error($this->current_name, VALIDATE_ERROR_ENUM, $err_msg);
            return FALSE;
        }
        return TRUE;
    }

    // 检查长度length
    public function validate_length()
    {
        $length = mb_strlen($this->current_value, 'utf8');
        if (isset($this->current_rule['min_length']) AND $length < $this->current_rule['min_length']) {
            $err_msg = isset($this->current_rule['desc']) ? $this->current_rule['desc'] : $this->current_name . '过短';
            $this->add_error($this->current_name, VALIDATE_ERROR_SHORT, $err_msg);
            return FALSE;
        }
        if (isset($this->current_rule['max_length']) AND $length > $this->current_rule['max_length']) {
            $err_msg = isset($this->current_rule['desc']) ? $this->current_rule['desc'] : $this->current_name . '过长';
            $this->add_error($this->current_name, VALIDATE_ERROR_LONG, $err_msg);
            return FALSE;
        }
        return TRUE;
    }

    public function add_error($name, $code, $msg)
    {
        $this->error_messages[] = array(
            'name' => $name,
            'code' => $code,
            'msg' => $msg);
    }

    public function get_error()
    {
        return $this->error_messages;
    }

    public function last_error()
    {
        if (!empty($this->error_messages)) {
            return $this->error_messages[count($this->error_messages) - 1];
        }
        return array();
    }

    public function last_error_code()
    {
        if (!empty($this->error_messages)) {
            return $this->error_messages[count($this->error_messages) - 1]['code'];
        }
        return NULL;
    }

    public function pass()
    {
        return $this->passed;
    }
} 