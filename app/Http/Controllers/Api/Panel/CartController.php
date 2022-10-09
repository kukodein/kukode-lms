<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Api\Cart ;
use App\Models\ReserveMeeting ;
use App\Models\Api\Webinar ;
use Illuminate\Validation\Rule; 
use App\Models\Discount ;
use App\Models\PaymentChannel ;
use App\Models\OrderItem ;
use App\Models\Order ;
use Illuminate\Support\Facades\URL;



class CartController extends Controller
{
    public function index()
    {
        $user = apiAuth();
        $carts = Cart::where('creator_id', $user->id)
        -> get();
           $cartt=null ;

        if (!empty($carts) and !$carts->isEmpty()) {
            $calculate = $this->calculatePrice($carts, $user);

            if (!empty($calculate)) {
                
                $cartt=[
                    'items'=>$carts->map(function($cart){
                        return $cart->details ;
                    }) ,
                    'amounts'=>$calculate  ,
                    'user_group' => $user->userGroup ? $user->userGroup->group : null,
                ] ;
             

             }
        }
 
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),[
            'cart'=>$cartt
        ]);
        
    }

    public function destroy($id)
    {
        $user_id = apiAuth()->id;
        $cart = Cart::where('id', $id)
            ->where('creator_id', $user_id)
            ->first();
        abort_unless($cart,404) ;

        if (!empty($cart->reserve_meeting_id)) {
            $reserve = ReserveMeeting::where('id', $cart->reserve_meeting_id)
                ->where('user_id', $user_id)
                ->first();

            if (!empty($reserve)) {
                $reserve->delete();
            }
        }

        $cart->delete();
        return apiResponse2(1, 'deleted', trans('api.public.deleted'));
     
      
    }

    public function store(Request $request)
    {
        $user = apiAuth();

        validateParam($request->all(),
        [
            'webinar_id' => ['required',
                Rule::exists('webinars', 'id')->where('private', false)
                ->where('status', 'active')
            ],
            'ticket_id' => 'nullable',
        ]
    );

        $webinar_id = $request->get('webinar_id');
        $ticket_id = $request->input('ticket_id');

        $webinar = Webinar::find($webinar_id) ;
        

        $checkCourseForSale =$webinar->canAddToCart() ;

        if ($checkCourseForSale != 'ok') {
            return apiResponse2(0,$checkCourseForSale, trans('api.course.purchase.'.$checkCourseForSale)) ;
        }

        $activeSpecialOffer = $webinar->activeSpecialOffer();

       
        Cart::updateOrCreate([
            'creator_id' => $user->id,
            'webinar_id' => $webinar_id,
        ], [
            'ticket_id' => $ticket_id,
            'special_offer_id' => !empty($activeSpecialOffer) ? $activeSpecialOffer->id : null,
            'created_at' => time()
        ]);

        return apiResponse2(1, 'stored', trans('api.public.store'));

    }

    public function validateCoupon(Request $request)
    {
        $user = apiAuth() ;
        $coupon = $request->get('coupon');

        $discountCoupon = Discount::where('code', $coupon)
            ->where('expired_at', '>', time())
            ->first();

            if(!$discountCoupon ||!$discountCoupon->checkValidDiscount($user)  )
            {
                return apiResponse2(0, 'invalid', trans('api.cart.invalid_coupon'));

            } 

            $carts = Cart::where('creator_id', $user->id)
            ->get();

        if (!empty($carts) and !$carts->isEmpty()) {
            $calculate = $this->calculatePrice($carts, $user, $discountCoupon);

            if (!empty($calculate)) {
                

       return apiResponse2(1, 'valid', trans('api.cart.valid_coupon'),[
           'amounts'=> $calculate  ,
           'discount'=>$discountCoupon ,
       ]);

            }
        }


        
    
    }

    public function createOrderAndOrderItems($carts, $calculate, $user, $discountCoupon = null)
    {
        $order = Order::create([
            'user_id' => $user->id,
            'status' => Order::$pending,
            'amount' => $calculate["sub_total"],
            'tax' => $calculate["tax_price"],
            'total_discount' => $calculate["total_discount"],
            'total_amount' => $calculate["total"],
            'created_at' => time(),
        ]);

        foreach ($carts as $cart) {
            $price = 0;
            $discount = 0;
            $discountCouponPrice = 0;
            $sellerUser = null;

            if (!empty($cart->webinar_id)) {
                $price = $cart->webinar->price;
                $discount = $cart->webinar->getDiscount($cart->ticket, $user);
                $sellerUser = $cart->webinar->creator;
            } elseif (!empty($cart->reserve_meeting_id)) {
                $price = $cart->reserveMeeting->paid_amount;
                $discount = $price * $cart->reserveMeeting->discount / 100;
                $sellerUser = $cart->reserveMeeting->meeting->creator;
            }

            if (!empty($discountCoupon)) {
                $couponAmount = $price * $discountCoupon->percent / 100;

                if (!empty($discountCoupon->amount) and $couponAmount > $discountCoupon->amount) {
                    $discountCouponPrice += $discountCoupon->amount;
                } else {
                    $discountCouponPrice += $couponAmount;
                }
            }

            $financialSettings = getFinancialSettings();
            $commission = $financialSettings['commission'] ?? 0;
            $tax = $financialSettings['tax'] ?? 0;

            if (!empty($sellerUser)) {
                $commission = $sellerUser->getCommission();
            }

            $allDiscountPrice = $discount + $discountCouponPrice;
            $subAmount = $price - $allDiscountPrice;

            if ($allDiscountPrice > $price) {
                $subAmount = 0;
            }

            $taxPrice = ($tax and $subAmount > 0) ? ($subAmount * $tax) / 100 : 0;
            $commissionPrice = $subAmount > 0 ? ($subAmount * $commission) / 100 : 0;
            $totalAmount = $subAmount + $taxPrice;

            $ticket = $cart->ticket;
            if (!empty($ticket) and !$ticket->isValid()) {
                $ticket = null;
            }

            OrderItem::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'webinar_id' => $cart->webinar_id ?? null,
                'reserve_meeting_id' => $cart->reserve_meeting_id ?? null,
                'subscribe_id' => $cart->subscribe_id ?? null,
                'promotion_id' => $cart->promotion_id ?? null,
                'ticket_id' => !empty($ticket) ? $ticket->id : null,
                'discount_id' => $discountCoupon ? $discountCoupon->id : null,
                'amount' => $price,
                'total_amount' => $totalAmount,
                'tax' => $tax,
                'tax_price' => $taxPrice,
                'commission' => $commission,
                'commission_price' => $commissionPrice,
                'discount' => $discount + $discountCouponPrice,
                'created_at' => time(),
            ]);
        }

        return $order;
    }

    public function webCheckout(Request $request){

        return apiResponse2(1, 'generated', trans('api.link.generated'),
    [
        'link'=>URL::signedRoute('api.payment.request', ['user_id' =>apiAuth()->id])
    ]
    );
        
      //  return U;
    }
    public function checkout(Request $request)
    {

        $discountId = $request->input('discount_id');

        $paymentChannels = PaymentChannel::where('status', 'active')->get();

        $discountCoupon = Discount::where('id', $discountId)->first();

        if (empty($discountCoupon) or !$discountCoupon->checkValidDiscount()) {
            $discountCoupon = null;
        }

        $user =apiAuth();
        $carts = Cart::where('creator_id', $user->id)
            ->get();

        if (!empty($carts) and !$carts->isEmpty()) {
            $calculate = $this->calculatePrice($carts, $user, $discountCoupon);

            $order = $this->createOrderAndOrderItems($carts, $calculate, $user, $discountCoupon);

            if (!empty($order) and $order->total_amount > 0) {
                $razorpay = false;
                foreach ($paymentChannels as $paymentChannel) {
                    if ($paymentChannel->class_name == 'Razorpay') {
                        $razorpay = true;
                    }
                }

                $data = [
              //      'pageTitle' => trans('public.checkout_page_title'),
                    'paymentChannels' => $paymentChannels,
                    'carts' => $carts->map(function($cart){
                        return $cart->details ;
                    }),
                   // 'subTotal' => $calculate["sub_total"],
                   // 'totalDiscount' => $calculate["total_discount"],
                  //  'tax' => $calculate["tax"],
                 //   'taxPrice' => $calculate["tax_price"],
               //     'total' => $calculate["total"],
                    'user_group' => $user->userGroup ? $user->userGroup->group : null,
                    'order' => $order,
                    'count' => $carts->count(),
                    'userCharge' => $user->getAccountingCharge(),
                    'razorpay' => $razorpay ,
                    'amounts'=>$calculate ,
                ];

        return apiResponse2(1, 'checkout', trans('api.cart.checkout'),$data);

                
            } else {
                return $this->handlePaymentOrderWithZeroTotalAmount($order);
            }
        }

        return apiResponse2(0, 'empty_cart', trans('api.payment.empty_cart'));

    } 
    private function handlePaymentOrderWithZeroTotalAmount($order)
    {
        $order->update([
            'payment_method' => Order::$paymentChannel
        ]);

        $paymentController = new PaymentsController();

        $paymentController->setPaymentAccounting($order);

        $order->update([
            'status' => Order::$paid
        ]);
        return apiResponse2(1, 'paid', trans('api.payment.paid'));
        
    }

    private function calculatePrice($carts, $user, $discountCoupon = null)
    {
        $totalDiscount = 0;

        $subTotal = $carts->sum(function ($cart) use ($user, &$totalDiscount) {
            $price = 0;

            if (!empty($cart->webinar_id)) {
                $price = $cart->webinar->price;
                $totalDiscount += $cart->webinar->getDiscount($cart->ticket, $user);
            } elseif (!empty($cart->reserve_meeting_id)) {
                $price = $cart->reserveMeeting->paid_amount;
                $totalDiscount += $price * $cart->reserveMeeting->discount / 100;
            }

            return $price;
        });


        if (!empty($discountCoupon)) {
            $couponAmount = $subTotal * $discountCoupon->percent / 100;

            if (!empty($discountCoupon->amount) and $couponAmount > $discountCoupon->amount) {
                $totalDiscount += $discountCoupon->amount;
            } else {
                $totalDiscount += $couponAmount;
            }
        }

        if ($totalDiscount > $subTotal) {
            $totalDiscount = $subTotal;
        }

        $subTotalWithDiscount = $subTotal - $totalDiscount;

        $financialSettings = getFinancialSettings();
        $commission = $user->getCommission();

        $tax = 0;
        $taxPrice = 0;

        if (!empty($financialSettings['tax']) and $financialSettings['tax'] > 0 and $subTotalWithDiscount > 0) {
            $tax = $financialSettings['tax'];
            $taxPrice = $subTotalWithDiscount * $tax / 100;
        }

        $commissionPrice = $subTotalWithDiscount > 0 ? $subTotalWithDiscount * $commission / 100 : 0;

        $total = $subTotalWithDiscount + $taxPrice;

        if ($total < 0) {
            $total = 0;
        }

        return [
            'sub_total' => round($subTotal, 2),
            'total_discount' => $totalDiscount,
            'tax' => $tax,
            'tax_price' => round($taxPrice, 2),
            'commission' => $commission,
            'commission_price' => round($commissionPrice, 2),
            'total' => round($total, 2),
        ];
    }
          

}
