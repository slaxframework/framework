<?php
namespace SlaxFramework\Framework\Facade;

use \SlimFacades\Facade;

class ModuleManagerFacade extends Facade{
    protected static function getFacadeAccessor() { return 'module'; }
}