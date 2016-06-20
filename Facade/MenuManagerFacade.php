<?php
namespace SlaxFramework\Framework\Facade;

use \SlimFacades\Facade;

class MenuManagerFacade extends Facade{
    protected static function getFacadeAccessor() { return 'menu'; }
}