# Basic Usage

````php
namespace MyApp/Resources;

final class Notification extends \Dy\Orm\Model
{
    const TABLE_NAME = 'notification';
    // comment this line if you need create_time and update_time
    public static $timestamps = false;
}
````

In your controller file

````php
use MyApp/Resources/Notification as Notify;

class Notification extends CI_Controller{
    public function index(){
        echo Notify::countAll();
    }
    
    public function create(){
        $n = new Notify();
        $n->title = 'jack got a new book';
        $n->body = 'what does the book say? mie mie mie mie';
        $n->save();
        echo $n->id;
    }
}
````