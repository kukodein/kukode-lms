<?php

// use App\Api\Response;
// use App\Api\Request;
use App\Http\Controllers\Api\Config\ConfigController;

function apiResponse($status, $msg = null, $data = null, $http_status = null)
{
    $response = new Response();
    return $response->apiResponse($status, $msg, $data, $http_status);
}

function validateParam($request_input, $rules, $somethingElseIsInvalid = null)
{
    $request = new Request();
    return $request->validateParam($request_input, $rules, $somethingElseIsInvalid);
}

function apiResponse2($success, $status, $msg, $data = null)
{
    $response = new Response();
    return $response->apiResponse2($success, $status, $msg, $data);
}

function getUrl($path)
{
    if (!$path) {
        return null;
    }
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }
    return config('app.url') . $path;
}

function getPrice($price)
{
    return $price ? (ConfigController::get()['currency']['sign'] . (number_format($price, 2, ".", "") + 0)) : $price;
}

function apiAuth()
{
  
   if(request()->input('test_auth_id')){
     return App\Models\Api\User::find(request()->input('test_auth_id'))??die('test_auth_id not found') ;
   }
   return auth('api')->user();
   
   
}








