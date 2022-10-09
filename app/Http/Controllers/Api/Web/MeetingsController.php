<?php


namespace App\Http\Controllers\Api\Web;

use App\Models\Sale;
use Doctrine\Inflector\Rules\French\Rules;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Controller;
use App\Models\Cart;
use App\Models\Meeting;
use App\Models\MeetingTime;
use App\Models\ReserveMeeting;
use App\User;

class MeetingsController extends Controller
{


    public function reserve(Request $request)
    {
        validateParam($request->all(), [
           // 'time_id' => 'required|array|min:1',
            'time_id' => 'required|exists:meeting_times,id',
            'date' => 'required|date',
        ]);
     
        $user = apiAuth();
        $timeIds =[ $request->input('time_id')];
        $day = $request->input('date');
        //   $day = dateTimeFormat($day, 'Y-m-d');

        $meetingTimes = MeetingTime::whereIn('id', $timeIds)
            ->with('meeting')
            ->get();


        if ($meetingTimes->isNotEmpty()) {
            $meetingId = $meetingTimes->first()->meeting_id;
            $meeting = Meeting::find($meetingId);

            if (!empty($meeting) and !$meeting->disabled) {
                if (!empty($meeting->amount) and $meeting->amount > 0) {
                    foreach ($meetingTimes as $meetingTime) {
                        $reserveMeeting = ReserveMeeting::where('meeting_time_id', $meetingTime->id)
                            ->where('day', $day)
                            ->first();

                        if (!empty($reserveMeeting) and $reserveMeeting->locked_at) {
                            return apiResponse2(0, 'locked', trans('api.meeting.locked'));
                        }

                        if (!empty($reserveMeeting) and $reserveMeeting->user_id == $user->id) {
                            return apiResponse2(0, 'already_reserved', trans('api.meeting.already_reserved'));
                        }

                        if (!empty($reserveMeeting) and $reserveMeeting->reserved_at) {
                            return apiResponse2(0, 'reserved', trans('api.meeting.reserved'));
                        }

                        $hourlyAmount = $meetingTime->meeting->amount;
                        $explodetime = explode('-', $meetingTime->time);
                        $hours = (strtotime($explodetime[1]) - strtotime($explodetime[0])) / 3600;

                        $reserveMeeting = ReserveMeeting::updateOrCreate([
                            'user_id' => $user->id,
                            'meeting_time_id' => $meetingTime->id,
                            'meeting_id' => $meetingTime->meeting_id,
                            'status' => ReserveMeeting::$pending,
                            'day' => $day,
                            'date' => strtotime($day),
                        ], [
                            'paid_amount' => (!empty($hourlyAmount) and $hourlyAmount > 0) ? $hourlyAmount * $hours : 0,
                            'discount' => $meetingTime->meeting->discount,
                            'created_at' => time(),
                        ]);

                        $cart = Cart::where('creator_id', $user->id)
                            ->where('reserve_meeting_id', $reserveMeeting->id)
                            ->first();

                        if (empty($cart)) {
                            Cart::create([
                                'creator_id' => $user->id,
                                'reserve_meeting_id' => $reserveMeeting->id,
                                'created_at' => time()
                            ]);
                        }
                    }
                    return apiResponse2(1, 'added_to_cart', trans('api.public.added_to_cart'));
                } else {
                    return $this->handleFreeMeetingReservation($user, $meeting, $meetingTimes, $day);
                }
            } else {

                return apiResponse2(0, 'disabled', trans('api.meeting.disabled'));

            }
        }

    }

    private function handleFreeMeetingReservation($user, $meeting, $meetingTimes, $day)
    {
        foreach ($meetingTimes as $meetingTime) {
            $hourlyAmount = $meetingTime->meeting->amount;
            $explodetime = explode('-', $meetingTime->time);
            $hours = (strtotime($explodetime[1]) - strtotime($explodetime[0])) / 3600;

            $reserve = ReserveMeeting::updateOrCreate([
                'user_id' => $user->id,
                'meeting_time_id' => $meetingTime->id,
                'meeting_id' => $meetingTime->meeting_id,
                'status' => ReserveMeeting::$pending,
                'day' => $day,
                'date' => strtotime($day),
            ], [
                'paid_amount' => (!empty($hourlyAmount) and $hourlyAmount > 0) ? $hourlyAmount * $hours : 0,
                'discount' => $meetingTime->meeting->discount,
                'created_at' => time(),
            ]);

            if (!empty($reserve)) {
                $sale = Sale::create([
                    'buyer_id' => $user->id,
                    'seller_id' => $meeting->creator_id,
                    'meeting_id' => $meeting->id,
                    'type' => Sale::$meeting,
                    'payment_method' => Sale::$credit,
                    'amount' => 0,
                    'total_amount' => 0,
                    'created_at' => time(),
                ]);

                if (!empty($sale)) {
                    $reserve->update([
                        'sale_id' => $sale->id,
                        'reserved_at' => time()
                    ]);
                }
            }
        }
        return apiResponse2(1, 'stored', trans('api.public.stored'));
    }



}
