<?php
namespace SlaxFramework\Framework\Facade;

use \SlimFacades\Facade;

class DatabaseFacade extends Facade{
    protected static function getFacadeAccessor() { return 'db'; }
}