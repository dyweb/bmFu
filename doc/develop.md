# Develop

[style](style.md)

#### 1. we use a laravel application as migration tool

````
# This is just an example!
# generate a migration
cd migrate
php artisan make:migration create_posts_table

# generate a seed
# just copy and paste and rename ....

# run migration with seed
php artisan migrate --seed
````

#### 2. use phpunit to test

````
# must dump the classmap for test models
composer dump-autoload

# use the system phpunit in dev environment
phpunit
# or use the one in vendor
./vendor/bin/phpunit
````

#### 3. Folder structure

All lib files are in src folder. migrate folder is a laravel application used
for test. ( because we need migration and seed in tests and we can't use laravel
in our working environment )
````
Dy
  Orm
    Exception
      NotFound
      NotModified
      NotSaved
    Model
  Restful  -- the old orm.
    Collection -- for many records
    Relation -- for relations (not implemented)
    Resource -- for one record
    RestBase -- Base for Resource and Collection, initialize ci and redis
    Validator -- Validate based on rules. out of date. newest is in dygroup.
  Dummy -- for test psr-4 autoload
  Event -- A simple event dispatcher which only has listen and fire.
````

``Note:`` Hacked line in CodeIgniter db: Line 631, DB_drive.php