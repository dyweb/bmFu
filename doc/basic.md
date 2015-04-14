# Basic Usage

````php
final class Notification extends \Dy\Orm\Model
{
    const TABLE_NAME = 'notification';
    // comment this line if you need create_time and update_time
    public static $timestamps = false;
}
````