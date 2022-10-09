<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Models\OfflinePayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfflinePayments extends Controller
{
    public function list()
    {
        $user = apiAuth();
        $offlinePayments = OfflinePayment::where('user_id', $user->id)->orderBy('created_at', 'desc')->get()
            ->map(function ($offlinePayment) {
                return [
                    'id' => $offlinePayment->id,
                    'amount' => $offlinePayment->amount,
                    'bank' => $offlinePayment->bank,
                    'reference_number' => $offlinePayment->reference_number,
                    'status' => $offlinePayment->status,
                    'created_at' => $offlinePayment->created_at,
                    'pay_date' => $offlinePayment->pay_date,
                ];

            });
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'), $offlinePayments);

    }

    public function update(Request $request, $id)
    {
        $user = apiAuth();
        $offline = OfflinePayment::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$offline) {
            abort(404);
        }

        $data = $request->all();

        validateParam($request->all(), [
            'amount' => 'required|integer',
            //   'gateway' => 'required|in:offline',
            'account' => ['required',
                Rule::in(getOfflineBanksTitle())
            ],
            'referral_code' => 'required',
            'date' => 'required|date',

        ]);

        $offline->update([
            'amount' => $data['amount'],
            'bank' => $data['account'],
            'reference_number' => $data['referral_code'],
            'status' => OfflinePayment::$waiting,
            'pay_date' => $data['date'],
        ]);

        return apiResponse2(1, 'updated', trans('api.public.updated'));

    }

    public function destroy($id)
    {
        $user = apiAuth();
        $offline = OfflinePayment::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$offline) {
            abort(404);
        }
        $offline->delete();
        return apiResponse2(1, 'deleted', trans('api.public.deleted'));

    }

    public function store(Request $request){
        $array=[] ;
        foreach(getSiteBankAccounts()  as $account ){
             if(isset($account['title'])){
                $array[]=$account['title'] ;
             }
         
        }

        validateParam($request->all(),[
            'amount' => 'required|numeric',
            'bank' => ['required',Rule::in($array)],
            'reference_number' => 'required',
            'pay_date' => 'required',
        ]) ;

        $gateway = $request->input('gateway');
        $amount = $request->input('amount');
        $account = $request->input('bank');
        $referenceNumber = $request->input('reference_number');
        $date = $request->input('pay_date');

        $userAuth =apiAuth();

        OfflinePayment::create([
            'user_id' => $userAuth->id,
            'amount' => $amount,
            'bank' => $account,
            'reference_number' => $referenceNumber,
            'status' => OfflinePayment::$waiting,
            'pay_date' =>$date,
            'created_at' => time(),
        ]);

        $notifyOptions = [
            '[amount]' => $amount,
        ];
        sendNotification('offline_payment_request', $notifyOptions, $userAuth->id);
        return apiResponse2(1, 'stored', trans('api.public.stored'));

    }


}
