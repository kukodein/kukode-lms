<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    public function __construct()
    {

         // $this->middleware('guest')->except('logout');
    }

    public function login(Request $request)
    {
       // dd(Auth::guard('api')->check()) ;

        $rules = [
            'username' => 'required|string|numeric',
            'password' => 'required|string|min:6',
        ];

        if ($this->username() == 'email') {
            $rules['username'] = 'required|string|email';
        }
        validateParam($request->all(), $rules);

        return $this->attemptLogin($request);

    }

    public function username()
    {
        $email_regex = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";

        if (empty($this->username)) {
            $this->username = 'mobile';
            if (preg_match($email_regex, request('username', null))) {
                $this->username = 'email';
            }
        }
        return $this->username;
    }

    protected function attemptLogin(Request $request)
    {
        $credentials = [
            $this->username() => $request->get('username'),
            'password' => $request->get('password')
        ];


        if (!$token = auth('api')->attempt($credentials)) {
            return apiResponse2(0, 'incorrect', trans('auth.incorrect'));
        }
        return $this->afterLogged($request, $token);
    }
    public function afterLogged(Request $request, $token, $verify = false)
    {
        $user = auth('api')->user();

        if ($user->ban) {
            $time = time();
            $endBan = $user->ban_end_at;
            if (!empty($endBan) and $endBan > $time) {
                auth('api')->logout();
                return $this->sendBanResponse($user);
            } elseif (!empty($endBan) and $endBan < $time) {
                $user->update([
                    'ban' => false,
                    'ban_start_at' => null,
                    'ban_end_at' => null,
                ]);
            }
        }
        //   $verificationController = new VerificationController();
        //    $checkConfirmed = $verificationController->checkConfirmed($user, $this->username(), $request->get('username'));

        //   $verify = ($checkConfirmed['status'] == 'verified') ? true : false;
        //   $verify = $user->verifed;
        if ($user->status != User::$active and !$verify) {
            auth('api')->logout();
            $verificationController = new VerificationController();
            $checkConfirmed = $verificationController->checkConfirmed($user, $this->username(), $request->get('username'));
            // $checkConfirmed['status'] = 'verified';

            if ($checkConfirmed['status'] == 'send') {

                return apiResponse2(0, 'not_verified', trans('auth.not_verified'));
                //   return apiResponse(1, 'the user is not verified.verification code sent to user`s ' . $this->username());

            } elseif ($checkConfirmed['status'] == 'verified') {
                $user->update([
                    'status' => User::$active,
                ]);
            }
        } elseif ($verify) {
            //   session()->forget('verificationId');
            $user->update([
                'status' => User::$active,
            ]);

        }

        if ($user->status != User::$active) {
            \auth('api')->logout();
            return $this->sendNotActiveResponse($user);
        }

        $profile_completion = [];
        $data  ['token'] = $token;
        $data['user_id']=$user->id ;
        if (!$user->full_name) {
            $profile_completion[] = 'full_name';
            $data['profile_completion'] = $profile_completion;
        }

        return apiResponse2(1, 'login', trans('auth.login'), $data);


        /* if ($user->isAdmin()) {
             return redirect('/admin');
         } else {
             return redirect('/panel');
         }*/
    }


    protected function sendBanResponse($user)
    {
        return apiResponse2(0, 'banned_account', trans('auth.banned_account'));
    }

    protected function sendNotActiveResponse($user)
    {

        apiResponse2(0, 'inactive_account', trans('auth.inactive_account'));

    }

    public function logout(){

       auth('api')->logout() ;

       if(!apiAuth()){

     return   apiResponse2(1, 'logout', trans('auth.logout'));
       }
     return  apiResponse2(0, 'failed', trans('auth.logout.failed'));

    }


}
