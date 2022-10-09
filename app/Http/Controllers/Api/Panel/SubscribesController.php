<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Accounting;
use App\Models\Api\Webinar;
use App\User ;
use App\Models\SubscribeUse ;
use App\Models\OrderItem;
use App\Models\PaymentChannel;
use App\Models\Api\Subscribe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\URL;


class SubscribesController extends Controller
{
    public function index(Request $request)
    {
        $user = apiAuth();

        $subscribes = Subscribe::all()->map(function ($subscribe) {
            return $subscribe->details ;
        });

        $activeSubscribe=Subscribe::getActiveSubscribe($user->id) ;
        $dayOfUse=Subscribe::getDayOfUse($user->id) ;
     //   $has_active_subscribe=($activeSubscribe)?true:false ;

        $data = [
            'subscribes' => $subscribes,
            'subscribed'=>($activeSubscribe)?true:false ,
            'subscribed_title' => ($activeSubscribe)?$activeSubscribe->title:null,
            'remained_downloads'=>($activeSubscribe)?$activeSubscribe->usable_count - $activeSubscribe->used_count:null ,
            'days_remained'=>($activeSubscribe)?$activeSubscribe->days - $dayOfUse:null ,
            'dayOfUse' =>$dayOfUse,
        ];
        return apiResponse2(1, 'retrieved', trans('public.retrieved'), $data);
    }

    public function webPay(Request $request){

        validateParam($request->all(),[
            'subscribe_id'=>['required',Rule::exists('subscribes','id')]
        ]) ;

        return apiResponse2(1, 'generated', trans('api.link.generated'),
        [
            'link'=> URL::signedRoute('api.subscribe.request',['user_id'=>apiAuth()->id
            , 'subscribe_id'=>$request->input('subscribe_id')
            ]  ) 
            
        ]
        );
    }

    public function apiSubscribeRequest(Request $request){

        if (! $request->hasValidSignature()) {
            abort(401);
        }

        validateParam($request->all(),[
            'user_id'=>'required|exists:users,id'
        ]) ;
     
      $id=$request->input('subscribe_id') ;
        $subscribe = Subscribe::find($id);
        $amount=$subscribe->price;


       
        $user=User::find($request->input('user_id')) ;
        Auth::login($user) ;
     
        return view('api.subscribe_request',compact('amount','id')) ;
    }

    public function pay(Request $request)
    {
        validateParam($request->all(),[
            'subscribe_id'=>['required',Rule::exists('subscribes','id')]
        ]) ;
        $paymentChannels = PaymentChannel::where('status', 'active')->get();

        $subscribe_id=$request->input('subscribe_id') ;
        $subscribe = Subscribe::find($subscribe_id);

        $user = apiAuth();
        $activeSubscribe = Subscribe::getActiveSubscribe($user->id);

        if ($activeSubscribe) {
           
            return apiResponse2(0, 'has_active_subscribe', trans('api.subscribe.has_active_subscribe'));
        }

        $financialSettings = getFinancialSettings();
        $tax = $financialSettings['tax'] ?? 0;

        $amount =$subscribe->price ;

        $taxPrice = $tax ? $amount *  $tax / 100 : 0;

        $order = Order::create([
            "user_id" => $user->id,
            "status" => Order::$pending,
            'tax' => $taxPrice,
            'commission' => 0,
            "amount" => $amount,
            "total_amount" => $amount + $taxPrice,
            "created_at" => time(),
        ]);

        OrderItem::updateOrCreate([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'subscribe_id' => $subscribe->id,
        ], [
            'amount' => $order->amount,
            'total_amount' => $amount + $taxPrice,
            'tax' => $tax,
            'tax_price' => $taxPrice,
            'commission' => 0,
            'commission_price' => 0,
            'created_at' => time(),
        ]);

        $razorpay = false;
        foreach ($paymentChannels as $paymentChannel) {
            if ($paymentChannel->class_name == 'Razorpay') {
                $razorpay = true;
            }
        }



        $data = [
          //  'pageTitle' => trans('public.checkout_page_title'),
            'paymentChannels' => $paymentChannels,
            'total' => $order->total_amount,
            'order' => $order,
           // 'count' => 1,
            'userCharge' => $user->getAccountingCharge(),
            'razorpay' => $razorpay
        ];

       return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),$data);

    }

    public function apply(Request $request)
    {
        validateParam($request->all(),[

            'webinar_id' => ['required',
                Rule::exists('webinars', 'id')->where('private', false)
                ->where('status', 'active') ]
        ]) ;

        $user = apiAuth();

        $subscribe = Subscribe::getActiveSubscribe($user->id);
        $webinar=Webinar::find($request->input('webinar_id')) ;

        if(!$webinar->subscribe)
        {
            return apiResponse2(0, 'not_subscribable', trans('api.course.not_subscribable'));

        }

        if (!$subscribe) {

       return apiResponse2(0, 'no_active_subscribe', trans('api.subscribe.no_active_subscribe'));
           
        }

        $checkCourseForSale =$webinar->canAddToCart($user);


        if ($checkCourseForSale != 'ok') {
            return apiResponse2(0,$checkCourseForSale, trans('api.course.subscribe.'.$checkCourseForSale)) ;
        }

        $financialSettings = getFinancialSettings();
        $commission = $financialSettings['commission'] ?? 0;

        $pricePerSubscribe = $subscribe->price / $subscribe->usable_count;
        $commissionPrice = $commission ? $pricePerSubscribe * $commission / 100 : 0;

        $admin = User::getAdmin();

        $sale = Sale::create([
            'buyer_id' => $user->id,
            'seller_id' => $webinar->creator_id,
            'webinar_id' => $webinar->id,
            'type' => Sale::$webinar,
            'payment_method' => Sale::$subscribe,
            'amount' => 0,
            'total_amount' => 0,
            'created_at' => time(),
        ]);

        Accounting::create([
            'user_id' => $webinar->creator_id,
            'amount' => $pricePerSubscribe - $commissionPrice,
            'webinar_id' => $webinar->id,
            'type' => Accounting::$addiction,
            'type_account' => Accounting::$income,
            'description' => trans('public.paid_form_subscribe'),
            'created_at' => time()
        ]);

        Accounting::create([
            'system' => true,
            'user_id' => $admin->id,
            'amount' => $pricePerSubscribe - $commissionPrice,
            'webinar_id' => $webinar->id,
            'type' => Accounting::$deduction,
            'type_account' => Accounting::$asset,
            'description' => trans('public.paid_form_subscribe'),
            'created_at' => time()
        ]);

        SubscribeUse::create([
            'user_id' => $user->id,
            'subscribe_id' => $subscribe->id,
            'webinar_id' => $webinar->id,
            'sale_id' => $sale->id,
        ]);

    return apiResponse2(1, 'subscribed', trans('api.subscribe.subscribed'));
   
        
    }
}


