<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Web','middleware'=>['api.auth','api.request.type','api.check.key']],function () {

    Route::get('/files/{file_id}/download', ['uses' => 'FilesController@download']);

    Route::group(['prefix' => 'courses'], function () {
        Route::get('/', ['uses' => 'WebinarController@index']);
        Route::get('reports/reasons', ['uses' => 'ReportsController@index']);
        Route::get('{id}', ['uses' => 'WebinarController@show']);

        Route::post('/{id}/report', ['uses' => 'WebinarController@report', 'middleware' => 'api.auth']);

         Route::post('/{webinar_id}/toggle', ['uses' => 'WebinarController@learningStatus', 'middleware' => 'api.auth']);



    });


    Route::get('featured-courses', ['uses' => 'FeatureWebinarController@index']);
    Route::get('categories', ['uses' => 'CategoriesController@list']);
    Route::get('categories/{id}/webinars', ['uses' => 'CategoriesController@categoryWebinar']);
    Route::get('trend-categories', ['uses' => 'CategoriesController@trendCategory']);
    Route::get('search', ['uses' => 'SearchController@list']);

    Route::group(['prefix'=>'providers'],function(){

        Route::get('instructors', ['uses' => 'UserController@instructors']);
        Route::get('organizations', ['uses' => 'UserController@organizations']);
        Route::get('consultations', ['uses' => 'UserController@consultations']);


    }) ;



 //   Route::get('blogs/{id?}', ['uses' => 'BlogController@list']);
    Route::group(['prefix'=>'blogs'],function(){
        Route::get('/', ['uses' => 'BlogController@index']);
        Route::get('/categories', ['uses' => 'BlogCategoryController@index']);
        Route::get('/{id}', ['uses' => 'BlogController@show']);

    });
    Route::get('users/{id}/profile', ['uses' => 'UserController@profile']);
    Route::post('users/{id}/send-message', 'UserController@sendMessage');

    Route::get('advertising-banner', ['uses' => 'AdvertisingBannerController@list']);
    Route::post('meetings/reserve', ['uses' => 'MeetingsController@reserve', 'middleware' => ['api.auth', 'api.request.type']]);
    Route::get('users/{id}/meetings', ['uses' => 'UserController@availableTimes']);

    Route::get('/subscribe', ['uses' => 'SubscribesController@list']);

    Route::get('instructors', ['uses' => 'UserController@instructors']);
    Route::get('organizations', ['uses' => 'UserController@organizations']);
    Route::post('newsletter', ['uses' => 'UserController@makeNewsletter', 'middleware' => 'format']);
    Route::post('contact', ['uses' => 'ContactController@store', 'middleware' => 'format']);
});
