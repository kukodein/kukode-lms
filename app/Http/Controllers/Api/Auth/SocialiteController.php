<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Role;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Exception;
use App\User;

class SocialiteController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function redirectToGoogle()
    {

        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Create a new controller instance.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function handleGoogleCallback1()
    {
        try {
            $account = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $account->id)
                ->orWhere('email', $account->email)
                ->first();

            if (empty($user)) {
                $user = User::create([
                    'full_name' => $account->name,
                    'email' => $account->email,
                    'google_id' => $account->id,
                    'role_id' => Role::getUserRoleId(),
                    'role_name' => Role::$user,
                    'status' => User::$active,
                    'verified' => true,
                    'created_at' => time(),
                    'password' => null
                ]);
            }

            $user->update([
                'google_id' => $account->id,
            ]);

            Auth::login($user);

            return redirect('/');
        } catch (Exception $e) {
            $toastData = [
                'title' => trans('public.request_failed'),
                'msg' => trans('auth.fail_login_by_google'),
                'status' => 'error'
            ];
            return $this->apiResponse('0', $toastData);
            //  return back()->with(['toast' => $toastData]);
        }
    }

    public function handleGoogleCallback(Request $request)
    {
        validateParam($request->all(), [
            'email' => 'required|email',
            'name' => 'required',
            'id' => 'required'
        ]);
        $data = $request->all();
        $user = User::where('google_id', $data['id'])
            ->orWhere('email', $data['email'])
            ->first();
        $registered = true;
        if (empty($user)) {
            $registered = false;
            $user = User::create([  
                'full_name' => $data['name'],
                'email' => $data['email'],
                'google_id' => $data['id'],
                'role_id' => Role::getUserRoleId(),
                'role_name' => Role::$user,
                'status' => User::$active,
                'verified' => true,
                'created_at' => time(),
                'password' => null
            ]);
        }
        $user->update([
            'google_id' => $data['id'],
        ]);
      
        $data = [];
        $data['user_id']=$user->id ;
        $data['already_registered'] = $registered;
        if ($registered) {

          //  $user=User::first() ;
            $token = auth('api')->tokenById($user->id);

            //$data['token'] = auth('api')->login($user);
            $data['token']=$token ;
            return apiResponse2(1, 'login', trans('auth.login'), $data);

        }
        return apiResponse2(1, 'registered', 'user registered successfully', $data);


    }

    /**
     * Create a redirect method to facebook api.
     *
     * @return void
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    /**
     * Return a callback method from facebook api.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function handleFacebookCallback(Request $request)
    {
        validateParam($request->all(), [
            'email' => 'required|email',
            'name' => 'required',
            'id' => 'required'
        ]);
        $data = $request->all();
        $user = User::where('facebook_id', $data['id'])->orWhere('email', $data['email'])->first();
        $registered = true;
        if (empty($user)) {
            $registered = false;
            $user = User::create([
                'full_name' => $data['name'],
                'email' => $data['email'],
                'facebook_id' => $data['id'],
                'role_id' => Role::getUserRoleId(),
                'role_name' => Role::$user,
                'status' => User::$active,
                'verified' => true,
                'created_at' => time(),
                'password' => null
            ]);
        }
        $data = [];
        $data['user_id']=$user->id ;
        $data['already_registered'] = $registered;
        if ($registered) {

            $token = auth('api')->tokenById($user->id);
           // $data['token'] = auth('api')->login($user);
           $data['token']= $token ;
            return apiResponse2(1, 'login', trans('auth.login'), $data);

        }
        return apiResponse2(1, 'registered', 'user registered successfully', $data);


    }
}
