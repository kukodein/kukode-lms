<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Role;
use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */


    protected function validator(array $data)
    {
        $username = $this->username();

        if ($username == 'mobile') {
            $data[$username] = ltrim($data['country_code'], '+') . ltrim($data[$username], '0');
        }

        $rules = [
            'country_code' => ($username == 'mobile') ? 'required' : 'nullable',
            $username => ($username == 'mobile') ? 'required|numeric|unique:users' : 'required|string|email|max:255|unique:users',
            'term' => 'required|in:1|boolean',
            'full_name' => 'required|string|min:3',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|same:password',
        ];

        validateParam($data, $rules);
    }


    protected function create(array $data)
    {
        $username = $this->username();

        if ($username == 'mobile') {
            $data[$username] = ltrim($data['country_code'], '+') . ltrim($data[$username], '0');
        }


        $referralSettings = getReferralSettings();
        $usersAffiliateStatus = (!empty($referralSettings) and !empty($referralSettings['users_affiliate_status']));

        $user = User::create([
            'role_name' => Role::$user,
            'role_id' => Role::getUserRoleId(), //normal user
            $username => $data[$username],
            'full_name' => $data['full_name'],
            'status' => User::$pending,
            'password' => Hash::make($data['password']),
            'affiliate' => $usersAffiliateStatus,
            'created_at' => time()
        ]);

        return $user;
    }

    public function username()
    {
        $email_regex = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";

        $data = request()->all();

        if (empty($this->username)) {
            if (in_array('mobile', array_keys($data))) {
                $this->username = 'mobile';
            } else if (in_array('email', array_keys($data))) {
                $this->username = 'email';
            }
        }

        return $this->username ?? '';
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $user = $this->create($request->all());

        event(new Registered($user));

        $username = $this->username();

        $value = $request->get($username);
        if ($username == 'mobile') {
            $value = $request->get('country_code') . ltrim($request->get($username), '0');
        }

        $verificationController = new VerificationController();
        $checkConfirmed = $verificationController->checkConfirmed($user, $username, $value);

        $referralCode = $request->get('referral_code', null);

        if ($checkConfirmed['status'] == 'send') {

            if (!empty($referralCode)) {
                session()->put('referralCode', $referralCode);
            }

            return redirect('/verification');
        } elseif ($checkConfirmed['status'] == 'verified') {
            $this->guard()->login($user);

            $user->update([
                'status' => User::$active,
            ]);

            if (!empty($referralCode)) {
                Affiliate::storeReferral($user, $referralCode);
            }

            if ($response = $this->registered($request, $user)) {
                return $response;
            }

            return $request->wantsJson()
                ? new JsonResponse([], 201)
                : redirect($this->redirectPath());
        }
    }

    public function localRegister(Request $request)
    {
        $this->validator($request->all());

        $user = $this->create($request->all());

        event(new Registered($user));

        $username = $this->username();

        $value = $request->get($username);
        if ($username == 'mobile') {
            $value = $request->get('country_code') . ltrim($request->get($username), '0');
        }

        $verificationController = new VerificationController();
        $checkConfirmed = $verificationController->checkConfirmed($user, $username, $value);
        //        $checkConfirmed['status'] = 'send';
        $referralCode = $request->get('referral_code', null);

        if ($checkConfirmed['status'] == 'send') {

            if (!empty($referralCode)) {
                //   session()->put('referralCode', $referralCode);
            }

            return apiResponse2('1', 'registered', trans('auth.registered'));
        } elseif ($checkConfirmed['status'] == 'verified') {

            return 'registerController line 231';
            $this->guard()->login($user);


            $user->update([
                'status' => User::$active,
            ]);

            if (!empty($referralCode)) {
                Affiliate::storeReferral($user, $referralCode);
            }

            if ($response = $this->registered($request, $user)) {
                return $response;
            }

            return $request->wantsJson()
                ? new JsonResponse([], 201)
                : redirect($this->redirectPath());
        }
    }

    public function stepRegister(Request $request, $step)
    {
        $data = $request->all();
        if ($step == 1) {

            $registerMethod = getGeneralSettings('register_method') ?? 'mobile';

            $username = $this->username();
            if ($registerMethod !== $username) {
                return apiResponse2(0, 'invalid_register_method', trans('auth.invalid_register_method'));
            }

            $rules = [
                'country_code' => ($username == 'mobile') ? 'required' : 'nullable',
                //   $username => ($username == 'mobile') ? 'required|numeric|unique:users' : 'required|string|email|max:255|unique:users',
                $username => ($username == 'mobile') ? 'required|numeric' : 'required|string|email|max:255',
                'password' => 'required|string|min:6|confirmed',
                'password_confirmation' => 'required|same:password',
            ];

            validateParam($data, $rules);
            if ($username == 'mobile') {
                $data[$username] = ltrim($data['country_code'], '+') . ltrim($data[$username], '0');
                //  $data[$username]='+989386376960' ;
            }
            $userCase = User::where($username, $data[$username])->first();
            if ($userCase) {
                $userCase->update(['password' => Hash::make($data['password'])]);
                $verificationController = new VerificationController();

                $checkConfirmed = $verificationController->checkConfirmed2($userCase, $username, $data[$username]);

                if ($checkConfirmed['status'] == 'verified') {
                    if ($userCase->full_name) {
                        return apiResponse2(0, 'already_registered', trans('auth.already_registered'));
                    } else {
                        return apiResponse2(0, 'go_step_3', trans('auth.go_step_3'), [
                            'user_id' => $userCase->id
                        ]);
                    }
                } else {
                    $checkConfirmed = $verificationController->checkConfirmed($userCase, $username, $data[$username]);

                    return apiResponse2(0, 'go_step_2', trans('auth.go_step_2'), [
                        'user_id' => $userCase->id
                    ]);
                }
            }


            $referralSettings = getReferralSettings();
            $usersAffiliateStatus = (!empty($referralSettings) and !empty($referralSettings['users_affiliate_status']));

            $user = User::create([
                'role_name' => Role::$user,
                'role_id' => Role::getUserRoleId(), //normal user
                $username => $data[$username],
                //    'full_name' => $data['full_name'],
                'status' => User::$pending,
                'password' => Hash::make($data['password']),
                'affiliate' => $usersAffiliateStatus,
                'created_at' => time()
            ]);

            $verificationController = new VerificationController();
            $checkConfirmed = $verificationController->checkConfirmed($user, $username, $data[$username]);


            return apiResponse2('1', 'stored', trans('public.stored'), [
                'user_id' => $user->id
            ]);

            return apiResponse2(1, 'register_step_1', trans('public.stored'), [
                'user_id' => $user->id

            ]);
        } elseif ($step == 2) {

            validateParam($request->all(), [
                'user_id' => 'required|exists:users,id',
                //  'code'=>
            ]);

            $user = User::find($request->input('user_id'));

            //  $request->input('email',$user->email) ;
            /* $request = array_merge($request->all(), [
                 'email' => $user->email
             ]);*/
            //  $request->set
            //$request->request->add(['email', $user->email]);
            $verificationController = new VerificationController();
            $ee = $user->email ?? $user->mobile;
            return $verificationController->confirmCode($request, $ee);
        } elseif ($step == 3) {
            validateParam($request->all(), [
                'user_id' => 'required|exists:users,id',
                'full_name' => 'required|string|min:3',                //  'code'=>
            ]);

            $user = User::find($request->input('user_id'));
            $user->update([
                'full_name' => $data['full_name']
            ]);
            $referralCode = $request->input('referral_code', null);
            if (!empty($referralCode)) {
                try {
                    Affiliate::storeReferral($user, $referralCode);
                } catch (\Exception $e) {
                }
            }

            //  \   dd('f') ;
            //   $token = auth('api')->login($user);
            $token = auth('api')->tokenById($user->id);

            //     $token=  auth('api')->refresh();
            //  dd($token) ;

            // $token = auth('api')->attempt(["email"=>"zohreh_daeian@yahoo.com" ,"password"//=>"1234567"
            //]) ;

            //  $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

            $data['token'] = $token;
            $data['user_id'] = $user->id;
            return apiResponse2(1, 'login', trans('auth.login'), $data);

            return apiResponse2('1', 'stored', trans('public.stored'));
        }
        abort(404);

        $this->validator($request->all());

        $user = $this->create($request->all());

        event(new Registered($user));

        $username = $this->username();

        $value = $request->get($username);
        if ($username == 'mobile') {
            $value = $request->get('country_code') . ltrim($request->get($username), '0');
        }

        $verificationController = new VerificationController();
        $checkConfirmed = $verificationController->checkConfirmed($user, $username, $value);
        //        $checkConfirmed['status'] = 'send';
        $referralCode = $request->get('referral_code', null);

        if ($checkConfirmed['status'] == 'send') {

            if (!empty($referralCode)) {
                //   session()->put('referralCode', $referralCode);
            }
            return apiResponse2('1', 'registered', trans('auth.registered'));
        } elseif ($checkConfirmed['status'] == 'verified') {

            return 'registerController line 231';
            $this->guard()->login($user);


            $user->update([
                'status' => User::$active,
            ]);

            if (!empty($referralCode)) {
                Affiliate::storeReferral($user, $referralCode);
            }

            if ($response = $this->registered($request, $user)) {
                return $response;
            }

            return $request->wantsJson()
                ? new JsonResponse([], 201)
                : redirect($this->redirectPath());
        }
    }
}
