<?php
namespace SlaxFramework\Framework\Facade;

use \SlimFacades\Facade;

class SentryFacade extends Facade{
    protected static function getFacadeAccessor() { return 'sentry'; }
}