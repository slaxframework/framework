<?php

namespace SlaxFramework\Framework;

use \Illuminate\Database\Capsule\Manager as DatabaseManager;
use \SlaxFramework\Module\Manager as ModuleManager;
use \SlaxFramework\Menu\MenuManager;
use \SlimFacades\Facade;
use \Cartalyst\Sentry\Cookies\NativeCookie;
use \Cartalyst\Sentry\Sessions\NativeSession;
use \Cartalyst\Sentry\Groups\Eloquent\Provider as GroupProvider;
use \Cartalyst\Sentry\Hashing\BcryptHasher;
use \Cartalyst\Sentry\Hashing\NativeHasher;
use \Cartalyst\Sentry\Hashing\Sha256Hasher;
use \Cartalyst\Sentry\Hashing\WhirlpoolHasher;
use \Cartalyst\Sentry\Sentry;
use \Cartalyst\Sentry\Throttling\Eloquent\Provider as ThrottleProvider;
use \Cartalyst\Sentry\Users\Eloquent\Provider as UserProvider;
use \Slim\Slim;
use \Slim\Views\TwigExtension;
use \SlaxFramework\TwigExtension\MenuRenderer;

class Framework
{
    protected $app;
    protected $config;

    public function __construct(Slim $app = null)
    {
        $this->app = $app;
    }

    public function setConfig($config)
    {
        $this->config = $config;
        foreach($config as $key => $val) {
            $this->app->config($key, $val);
        }
    }

    public function setApp(Slim $app)
    {
        $this->app = $app;
    }

    public function bootFacade($aliases)
    {
        Facade::setFacadeApplication($this->app);
        Facade::registerAliases($aliases);
    }

    public function bootEloquent($connection)
    {
        try {
            $this->app->container->singleton('db', function(){
                return new DatabaseManager;
            });

            $db = $this->app->db;
            $db->addConnection($connection);
            $db->setAsGlobal();
            $db->bootEloquent();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function bootModuleManager()
    {
        $app = $this->app;
        $this->app->container->singleton('module', function() use($app){
            return new ModuleManager($app);
        });
    }

    public function bootSentry($config)
    {
        $app = $this->app;
        $this->app->container->singleton('sentry', function() use($app, $config){
            $hasher = $this->hasherProviderFactory($config);
            $user   = $this->userProviderFactory($hasher, $config);
            $group  = $this->groupProviderFactory($config);
            $throttle = $this->throttleProviderFactory($user, $config);

            return new Sentry($user, $group, $throttle, new NativeSession, new NativeCookie, $app->request->getIp());
        });
    }

    protected function hasherProviderFactory($config)
    {
        $hasher = $config["hasher"];
        switch($hasher){
            case "native":
                return new NativeHasher;
            break;

            case "bcrypt":
                return new BcryptHasher;
            break;

            case "sha256":
                return new Sha256Hasher;
            break;

            case "whirlpool":
                return new WhirlpoolHasher;
            break;
        }

        throw new \InvalidArgumentException("Invalid hasher [".$hasher."] chosen for Sentry.");
    }

    protected function userProviderFactory($hasher, $config)
    {
        $model = $config["users"]["model"];

        if(method_exists($model, 'setLoginAttributeName')) {
            $loginAttribute = $config["users"]["login_attribute"];

            forward_static_call_array(array($model, 'setLoginAttributeName'), array($loginAttribute));
        }

        if(method_exists($model, 'setGroupModel'))
        {
            $groupModel = $config["groups"]["model"];

            forward_static_call_array(array($model, 'setGroupModel'), array($groupModel));
        }

        if(method_exists($model, 'setUserGroupsPivot'))
        {
            $pivotTable = $config["user_groups_pivot_table"];

            forward_static_call_array(array($model, 'setUserGroupsPivot'), array($pivotTable));
        }

        return new UserProvider($hasher, $model);
    }

    protected function groupProviderFactory($config)
    {
        $model = $config["groups"]["model"];

        if(method_exists($model, 'setUserModel'))
        {
            $userModel = $config["users"]["model"];

            forward_static_call_array(array($model, 'setUserModel'), array($userModel));
        }

        if(method_exists($model, 'setUserGroupsPivot'))
        {
            $pivotTable = $config["user_groups_pivot_table"];

            forward_static_call_array(array($model, 'setUserGroupsPivot'), array($pivotTable));
        }

        return new GroupProvider($model);
    }

    protected function throttleProviderFactory($userProvider, $config)
    {
        $model = $config["throttling"]["model"];

        $throttleProvider = new ThrottleProvider($userProvider, $model);

        if($config["throttling"]["enabled"] === false) {
            $throttleProvider->disable();
        }

        if(method_exists($model, 'setAttemptLimit'))
        {
            $attemptLimit = $config["throttling"]["attempt_limit"];

            forward_static_call_array(array($model, 'setAttemptLimit'), array($attemptLimit));
        }

        if(method_exists($model, 'setSuspensionTime'))
        {
            $suspensionTime = $config["throttling"]["suspension_time"];

            forward_static_call_array(array($model, 'setSuspensionTime'), array($suspensionTime));
        }

        if(method_exists($model, 'setUserModel'))
        {
            $userModel = $config["users"]["model"];

            forward_static_call_array(array($model, 'setUserModel'), array($userModel));
        }

        return $throttleProvider;
    }

    public function bootTwig($config)
    {
        $app  = $this->app;
        $view = $app->view;

        $view->parserOptions = $config;
        $view->parserExtensions = array(
            new TwigExtension(),
            new MenuRenderer()
        );
    }

    public function bootMenuManager()
    {
        $this->app->container->singleton('menu', function(){
            return new MenuManager;
        });
    }

    public function boot()
    {
        $this->bootFacade($this->config["aliases"]);
        $this->bootMenuManager();
        $this->bootEloquent($this->config["database"]["connections"][$this->config["database"]["default"]]);
        $this->bootModuleManager();
        $this->bootTwig($this->config["twig"]);
        $this->bootSentry($this->config["sentry"]);
    }

    public function run()
    {
        $this->app->run();
    }
}