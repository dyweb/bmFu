# Style guide for bmfu

1. we use psr-4 autoload
2. use snake_case for variable and function names.
3. add `_` for private property and methods. better add it for protected as well
4. use single quote for strings, and remember to use `{}` when embed variables
5. use phpstorm to format code. LF for all files

````php
namespace Dy\Orm;

class Model{
    private static $_ci;
    public static $redis_client;
    
    public function __construct(){
        // da di di da da di du
    }
    
    public function knock($name){
        return "knock {$name} down";
    }
    
    protected function _mie(){
        return 'mie';
    }
}
````