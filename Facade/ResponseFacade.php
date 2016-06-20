<?php
namespace SlaxFramework\Framework\Facade;

use \SlimFacades\Response;

class ResponseFacade extends Response{
    public static function json($data, $status = 200){
        $app = self::$slim;
        $app->response->headers->set('Content-Type', 'application/json');
        $app->response->setStatus($status);
        if($data instanceof \Illuminate\Support\Contracts\JsonableInterface){
            $app->response->setBody($data->toJson());
        }else{
            $app->response->setBody(json_encode($data));
        }
        $app->response->finalize();
    }
}