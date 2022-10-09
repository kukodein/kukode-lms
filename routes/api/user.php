<?php

use Illuminate\Support\Facades\Route;

Route::group([] ,function () {

Route::get('/', function () {
    return 'test panel';
});

Route::get('/config', ['uses' => 'Config\ConfigController@list']);


Route::get('/quick-info', ['uses' => 'SummaryController@list']);
Route::post('/webinars/{id}/free', ['uses' => 'WebinarsController@free']);

Route::get('/subscribe', ['uses' => 'SubscribesController@index']);
Route::post('/subscribe', ['uses' => 'SubscribesController@pay']);
Route::post('/subscribe/web_pay', ['uses' => 'SubscribesController@webPay']);


Route::post('/subscribe/apply', ['uses' => 'SubscribesController@apply']);


Route::get('/webinars/purchases', ['uses' => 'WebinarsController@indexPurchases']);
Route::get('/webinars/organization', ['uses' => 'WebinarsController@indexOrganizations']);

Route::group(['prefix' => '/comments'], function () {
    Route::get('/', ['uses' => 'CommentsController@list']);
    Route::post('/', ['uses' => 'CommentsController@store', 'middleware' => 'api.request.type']);
    Route::delete('/{id}', ['uses' => 'CommentsController@destroy']);
    Route::put('/{id}', ['uses' => 'CommentsController@update', 'middleware' => 'api.request.type']);
    Route::post('/{id}/reply', ['uses' => 'CommentsController@reply', 'middleware' => 'api.request.type']);
    Route::post('/{id}/report', ['uses' => 'CommentsController@report', 'middleware' => 'api.request.type']);

});
Route::group(['prefix' => '/reviews'], function () {
    Route::get('/', ['uses' => 'WebinarReviewController@list']);
    Route::post('/', ['uses' => 'WebinarReviewController@store', 'middleware' => 'api.request.type']);
    Route::post('/{id}/reply', ['uses' => 'WebinarReviewController@reply', 'middleware' => 'api.request.type']);
    Route::delete('/{id}', ['uses' => 'WebinarReviewController@destroy']);
    //  Route::put('/{id}', ['uses' => 'CommentsController@update', 'middleware' => 'format']);
});
Route::group(['prefix' => '/support'], function () {

    Route::get('/class_support', ['uses' => 'SupportsController@classSupport']);
    Route::get('/my_class_support', ['uses' => 'SupportsController@myClassSupport']);
    Route::get('/tickets', ['uses' => 'SupportsController@platformSupport']);

Route::get('departments', ['uses' => 'SupportDepartmentsController@index']);


    Route::get('/{id}', ['uses' => 'SupportsController@show']);
    Route::get('/{id}/close', ['uses' => 'SupportsController@close']);

    Route::post('/', ['uses' => 'SupportsController@store']);

    Route::post('/{id}/conversations', ['uses' => 'SupportsController@storeConversations']);



    // SupportDepartmentsController

});
Route::group(['prefix' => 'notifications'], function () {
    Route::get('/', ['uses' => 'NotificationsController@list']);
    Route::post('/{id}/seen', ['uses' => 'NotificationsController@seen']);
});
Route::group(['prefix' => 'favorites'], function () {
    Route::get('/', ['uses' => 'FavoritesController@list']);
    Route::post('/toggle/{id}', ['uses' => 'FavoritesController@toggle']);
    Route::delete('/{id}', ['uses' => 'FavoritesController@destroy']);
});



Route::group(['prefix' => '/classes'], function () {
    Route::get('/', ['uses' => 'WebinarsController@list', 'middleware' => ['api.level-access:teacher']]);
});

Route::group(['prefix' => '/meetings'], function () {
     Route::get('reservation', ['uses' => 'ReserveMeetingsController@reservation']);
    Route::get('/{id?}', ['uses' => 'ReserveMeetingsController@list']);

});


Route::group(['prefix' => '/quizzes'], function () {

    Route::get('created', ['uses' => 'QuizzesController@created','middleware' => ['api.level-access:teacher']]);
    Route::get('not_participated', ['uses' => 'QuizzesController@notParticipated']);

    Route::get('{quizId}/result', ['uses' => 'QuizzesController@resultsByQuiz']);

    Route::get('results/my-results', ['uses' => 'QuizzesResultController@myResults']);
    Route::get('results/my-student-result', ['uses' => 'QuizzesResultController@myStudentResult','middleware' => ['api.level-access:teacher']]);
    Route::get('results/{quizResultId}/status', ['uses' => 'QuizzesResultController@status']);
    Route::get('results/{quizResultId}/download', 'CertificatesController@download');
    Route::get('results/{quizResultId}/show', 'CertificatesController@show');

   Route::post('results/{quizResultId}/review',
['uses' => 'QuizzesResultController@updateResult','middleware' => ['api.level-access:teacher']]);



    Route::get('/{id}/start', ['uses' => 'QuizzesResultController@start']);
    Route::post('{id}/store-result', ['uses' => 'QuizzesResultController@quizzesStoreResult']);


});


Route::get('certificates/achievements', ['uses' => 'CertificatesController@achievements']);
Route::get('certificates/created', ['uses' => 'CertificatesController@created' ,'middleware' => ['api.level-access:teacher'] ]);
Route::get('certificates/students', ['uses' => 'CertificatesController@students' ,'middleware' => ['api.level-access:teacher'] ]);


Route::post('/become_instructor', ['uses' => 'UsersController@store']);
Route::post('/users/{id}/follow', ['uses' => 'UsersController@followToggle']);


Route::group(['prefix' => '/cart'], function () {
    Route::get('list', ['uses' => 'CartController@index']);
    Route::delete('{id}', ['uses' => 'CartController@destroy']);
    Route::post('coupon/validate', ['uses' => 'CartController@validateCoupon']);
    Route::post('checkout', ['uses' => 'CartController@checkout']);
    Route::post('store', ['uses' => 'CartController@store']);
    Route::post('web_checkout', ['uses' => 'CartController@webCheckout']);


});

Route::group(['prefix' => 'financial'], function () {

    Route::get('sales', ['uses' => 'SalesController@index']);
    Route::post('/charge', ['uses' => 'PaymentsController@charge']);
    Route::post('/web_charge', ['uses' => 'PaymentsController@webCharge']);


    Route::get('/summary', ['uses' => 'AccountingsController@summary']);
    Route::get('/platform-bank-accounts', ['uses' => 'AccountingsController@platformBankAccounts']);
    Route::get('/accounts-type', ['uses' => 'AccountingsController@accountTypes']);

    //
    //   $siteBankAccounts = getSiteBankAccounts();
    Route::group(['prefix' => 'payout'], function () {
        Route::get('/', ['uses' => 'PayoutsController@list']);
        Route::post('/', ['uses' => 'PayoutsController@requestPayout']);

    });
    Route::group(['prefix' => 'offline-payments'], function () {
        Route::get('/', ['uses' => 'OfflinePayments@list']);
        Route::put('{id}', ['uses' => 'OfflinePayments@update']);
        Route::delete('{id}/', ['uses' => 'OfflinePayments@destroy']);
        Route::post('/', ['uses' => 'OfflinePayments@store']);


    });

});

Route::group(['prefix' => 'payments'], function () {
    Route::post('/request', 'PaymentsController@paymentRequest');
    Route::post('/credit', 'PaymentsController@paymentByCredit');
    Route::get('/verify/{gateway}', ['as' => 'payment_verify', 'uses' => 'PaymentController@paymentVerify']);
    Route::post('/verify/{gateway}', ['as' => 'payment_verify_post', 'uses' => 'PaymentController@paymentVerify']);
});
Route::group(['prefix' => 'profile-setting'], function () {
    Route::get('/', ['uses' => 'UsersController@setting']);
    Route::put('/password', ['uses' => 'UsersController@updatePassword', 'middleware' => 'api.request.type']);
    Route::put('/', ['uses' => 'UsersController@update' ]);
    Route::post('/images', ['uses' => 'UsersController@updateImages' ]);
});


});
