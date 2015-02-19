<?php
// set the password for mysql
if(getenv('IS_TRAVIS')){
    $pwd = '';
}else{
    $pwd = 'vagrant';
}
return array(
    'driver' => 'mysqli',
    'host' => 'localhost',
    'username' => 'root',
    'password' => $pwd,
    'database' => 'bmfu_test',
    'db_debug' => TRUE,
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'stricton' => TRUE
);