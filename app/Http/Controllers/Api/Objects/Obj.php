<?php

namespace App\Http\Controllers\Api\Objects;

abstract class Obj
{
    public static $auth;

    public function __construct()
    {
        $user = null;
        if (auth('api')->check()) {
            $user = auth('api')->user();
        }
        apiAuth() = $user;
    }

    abstract static protected function getById($obj);

    abstract static protected function getBriefById($obj);

    abstract static protected function getDetailsById($obj);

    abstract static protected function brief($obj);

    abstract static protected function details($obj);

}

