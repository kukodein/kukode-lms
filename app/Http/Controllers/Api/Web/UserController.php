<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\Config\ConfigController;
use App\Http\Controllers\Api\Objects\UserObj;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Follow;
use App\Models\Api\Meeting;
use App\Models\Newsletter;
use App\Models\ReserveMeeting;
use App\Models\Role;
use App\Models\Sale;
use App\Models\UserOccupation;
use App\Models\Api\Webinar;
use App\Models\Api\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Api\Setting ;
use Exception;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    private static $auth;

    public function sendMessage(Request $request, $id)
    {

        $user=User::find($id) ;
        abort_unless($user,404) ;
        if(!$user->public_message){
            return apiResponse2(0, 'disabled_public_message', trans('api.user.disabled_public_message'));
        }

        validateParam($request->all(),[
            'title' => 'required|string',
            'email' => 'required|email',
            'description' => 'required|string',
        //    'captcha' => 'required|captcha',
        ]) ;
        $data = $request->all();

        $mail = [
            'title' => $data['title'],
            'message' => trans('site.you_have_message_from', ['email' => $data['email']]) . "\n" . $data['description'],
        ];

        try {
            Mail::to($user->email)->send(new \App\Mail\SendNotifications($mail));
 
           
      return apiResponse2(1, 'email_sent', trans('api.user.email_sent'));

        } catch (Exception $e) {

            return apiResponse2(0, 'email_error', $e->getMessage());

           
        }

        
        
    }

  


    public function profile(Request $request, $id)
    {
        $user = User::where('id', $id)
            ->whereIn('role_name', [Role::$organization, Role::$teacher, Role::$user])
            ->first();
        if (!$user) {
            abort(404);
        }
        return apiResponse2(1, 'retrieved', trans('public.retrieved'), [
            'user'=>$user->details
        ]);
 
    }

    public function providers(Request $request)
    {
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'), [
            'instructors' => $this->instructors($request),
            'organizations' => $this->organizations($request),
            'consultations' => $this->consultations($request),
        ]);

    }

    public function instructors(Request $request)
    {
        $providers= $this->handleProviders($request, [Role::$teacher]);

        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),  $providers );

    }

    public function consultations(Request $request)
    {
        $providers= $this->handleProviders($request, [Role::$teacher, Role::$organization], true);
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),  $providers );

 
    }

    public function organizations(Request $request)
    {
        $providers= $this->handleProviders($request, [Role::$organization]);

        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),  $providers );


    }

    public function handleProviders(Request $request, $role, $has_meeting = false)
    {
        $query = User::whereIn('role_name', $role)
            //->where('verified', true)
            ->where('users.status', 'active')
            ->where(function ($query) {
                $query->where('users.ban', false)
                    ->orWhere(function ($query) {
                        $query->whereNotNull('users.ban_end_at')
                            ->orWhere('users.ban_end_at', '<', time());
                    });
            });
        if ($has_meeting) {
            $query->whereHas('meeting');
        }
        /*   ->with(['meeting' => function ($query) {
               $query->with('meetingTimes');
               $query->withCount('meetingTimes');
           }]);*/

        $users= $this->filterProviders($request, deepClone($query), $role)
            ->get()
            ->map(function($user){
                return $user->brief ;
            })
            ;

        return [
            'count'=>$users->count() ,
            'users'=>$users ,
        ];

    }

    private function filterProviders($request, $query, $role)
    {
        $categories = $request->get('categories', null);
        $sort = $request->get('sort', null);
        $availableForMeetings = $request->get('available_for_meetings', null);
        $hasFreeMeetings = $request->get('free_meetings', null);
        $withDiscount = $request->get('discount', null);
        $search = $request->get('search', null);
        $organization_id = $request->get('organization', null);
        $downloadable = $request->get('downloadable', null);

        if ($downloadable) {
            $query->whereHas('webinars', function ($qu) {
                return $qu->where('downloadable', 1);
            });
        }
        if (!empty($categories) and is_array($categories)) {
            $userIds = UserOccupation::whereIn('category_id', $categories)->pluck('user_id')->toArray();

            $query->whereIn('users.id', $userIds);
        }
        if ($organization_id) {
            $query->where('organ_id', $organization_id);
        }

        if (!empty($sort) and $sort == 'top_rate') {
            $query = $this->getBestRateUsers($query, $role);
        }

        if (!empty($sort) and $sort == 'top_sale') {
            $query = $this->getTopSalesUsers($query, $role);
        }

        if (!empty($availableForMeetings) and $availableForMeetings == 1) {
            $hasMeetings = DB::table('meetings')
                ->where('meetings.disabled', 0)
                ->join('meeting_times', 'meetings.id', '=', 'meeting_times.meeting_id')
                ->select('meetings.creator_id', DB::raw('count(meeting_id) as counts'))
                ->groupBy('creator_id')
                ->orderBy('counts', 'desc')
                ->get();

            $hasMeetingsInstructorsIds = [];
            if (!empty($hasMeetings)) {
                $hasMeetingsInstructorsIds = $hasMeetings->pluck('creator_id')->toArray();
            }

            $query->whereIn('users.id', $hasMeetingsInstructorsIds);
        }

        if (!empty($hasFreeMeetings) and $hasFreeMeetings == 1) {
            $freeMeetingsIds = Meeting::where('disabled', 0)
                ->where(function ($query) {
                    $query->whereNull('amount')->orWhere('amount', '0');
                })->groupBy('creator_id')
                ->pluck('creator_id')
                ->toArray();

            $query->whereIn('users.id', $freeMeetingsIds);
        }

        if (!empty($withDiscount) and $withDiscount == 1) {
            $withDiscountMeetingsIds = Meeting::where('disabled', 0)
                ->whereNotNull('discount')
                ->groupBy('creator_id')
                ->pluck('creator_id')
                ->toArray();

            $query->whereIn('users.id', $withDiscountMeetingsIds);
        }

        if (!empty($search)) {
            $query->where(function ($qu) use ($search) {
                $qu->where('users.full_name', 'like', "%$search%")
                    ->orWhere('users.email', 'like', "%$search%")
                    ->orWhere('users.mobile', 'like', "%$search%");
            });
        }

        return $query;
    }

    private function getBestRateUsers($query, $role)
    {
        $query->leftJoin('webinars', function ($join) use ($role) {
            if ($role == Role::$organization) {
                $join->on('users.id', '=', 'webinars.creator_id');
            } else {
                $join->on('users.id', '=', 'webinars.teacher_id');
            }

            $join->where('webinars.status', 'active');
        })->leftJoin('webinar_reviews', function ($join) {
            $join->on('webinars.id', '=', 'webinar_reviews.webinar_id');
            $join->where('webinar_reviews.status', 'active');
        })
            ->whereNotNull('rates')
            ->select('users.*', DB::raw('avg(rates) as rates'))
            ->orderBy('rates', 'desc');

        if ($role == Role::$organization) {
            $query->groupBy('webinars.creator_id');
        } else {
            $query->groupBy('webinars.teacher_id');
        }

        return $query;
    }

    private function getTopSalesUsers($query, $role)
    {
        $query->leftJoin('sales', function ($join) {
            $join->on('users.id', '=', 'sales.seller_id')
                ->whereNull('refund_at');
        })
            ->whereNotNull('sales.seller_id')
            ->select('users.*', 'sales.seller_id', DB::raw('count(sales.seller_id) as counts'))
            ->groupBy('sales.seller_id')
            ->orderBy('counts', 'desc');

        return $query;
    }


    public function makeNewsletter(Request $request)
    {
        validateParam($request->all(), [
            'email' => 'required|string|email|max:255|unique:newsletters,email'
        ]);

        $data = $request->all();
        $user_id = null;
        $email = $data['email'];
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->email == $email) {
                $user_id = $user->id;

                $user->update([
                    'newsletter' => true,
                ]);
            }
        }

        Newsletter::create([
            'user_id' => $user_id,
            'email' => $email,
            'created_at' => time()
        ]);

        return apiResponse2('1', 'subscribed_newsletter', 'email subscribed in newsletter successfully.');


    }

    public static function brief($users, $single = false)
    {
        if ($single) {
            $users = collect([$users]);
        }

        $users = $users->map(function ($user) {
            $meeting = 'no';
            if ($user->meeting) {
                $meeting = 'available';
                if ($user->meeting->disabled) {
                    $meeting = 'unavailable';
                }
            }
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'role_name' => $user->role_name,
                'bio' => $user->bio,
                'rate' => $user->rates(),
                'avatar' => $user->getAvatar(),
                'meeting_status' => $meeting,
            ];
        });

        if ($single) {
            return $users;
        }

        return [
            'count' => count($users),
            'users' => $users,
        ];
    }

    public static function getSingle($id)
    {
        $user = User::where('id', $id)
            ->whereIn('role_name', [Role::$organization, Role::$teacher, Role::$user])
            ->get();

        return self::brief($user);

    }

    public static function details($users)
    {


        $auth = apiAuth();
        $users = $users->map(function ($user) use ($auth) {

            $meeting_status = 'no';
            if ($user->meeting) {
                $meeting_status = 'available';
                if ($user->meeting->disabled) {
                    $meeting_status = 'unavailable';
                }
            }

            $authUserIsFollower = false;
            if ($auth) {
                $authUserIsFollower = $user->followers()->where('follower', $auth->id)
                    ->where('status', Follow::$accepted)
                    ->first();
            }
            return [
                //   'ff'=>$user->userMetas->where('name', 'experience') ,
                'id' => $user->id,
                'full_name' => $user->full_name,
                'role_name' => $user->role_name,
                'offline' => $user->offline,
                'verified' => $user->verified,
                'headline' => $user->headline,
                'public_message' => $user->public_message,
                'students_count' => 0,
                'courses_count' => $user->webinars->count(),
                'reviews_count' => $user->reviewsCount(),
                'appointments_count' => $user->ReserveMeetings->whereNotNull('reserved_at')
                    ->where('status', '!=', ReserveMeeting::$canceled)
                    ->count(),
                'followers' => $user->followers(),
                'following' => $user->following(),
                'auth_user_is_follower' => $authUserIsFollower,
                'bio' => $user->bio,
                'rate' => $user->rates(),
                'avatar' => $user->getAvatar(),
                'meeting_status' => $meeting_status,
                'about' => [
                    'offline_message' => $user->offline_message,
                    'education' => $user->userMetas->where('name', 'education')->map(function ($meta) {
                        return $meta->value;
                    }),
                    'experience' => $user->userMetas->where('name', 'experience')->map(function ($meta) {
                        return $meta->value;
                    }),
                    'occupations' => $user->occupations->map(function ($occupation) {
                        return $occupation->category->title;
                    }),
                    //   'about' => $user->about,
                ],
                'webinars' => WebinarController::brief($user->webinars),
                'badges' => $user->getBadges(),

                /* ->map(function ($badge) {
                 return [
                     'image' => ($badge->badge_id) ? $badge->badge->image : $badge->image,
                     'title' => ($badge->badge_id) ? $badge->badge->title : $badge->title,
                     'description' => ($badge->badge_id) ? $badge->badge->description : $badge->description,
                 ];
            }),*/


                'meeting' => ($user->meeting) ? [
                    'id' => $user->meeting->id,
                    'disabled' => $user->meeting->disabled,
                    'discount' => $user->meeting->discount,
                    //  'price' =>ConfigController::get()['currency']['sign']. $user->meeting->amount,
                    'price' => ($user->meeting->amount) ? (ConfigController::get()['currency']['sign'] . $user->meeting->amount) : $user->meeting->amount,
                    'price_with_discount' => ($user->meeting->discount) ? (ConfigController::get()['currency']['sign'] . ($user->meeting->amount - (($user->meeting->amount * $user->meeting->discount) / 100))) : $user->meeting->amount,

                    'timing' => $user->meeting->meetingTimes->map(function ($time) {
                        return [
                            'id' => $time->id,
                            'day_label' => $time->day_label,
                            'time' => $time->time,
                        ];
                    }),
                    'timing_group_by_day' => $user->meeting->meetingTimes->groupBy('day_label')->map(function ($time) {
                        return $time->map(function ($ee) {
                            return [
                                'id' => $ee->id,
                                'day_label' => $ee->day_label,
                                'time' => $ee->time,
                            ];
                        });

                    }),

                ] : [],
                'organization_teachers' => UserController::brief($user->getOrganizationTeachers),

            ];

        });
        return [
            'count' => count($users),
            'users' => $users,
        ];
    }

    public function availableTimes(Request $request, $id)
    {
        $date = $request->input('date');
        $timestamp = strtotime($date);
        $user = User::where('id', $id)
            ->whereIn('role_name', [Role::$teacher, Role::$organization])
            ->where('status', 'active')
            ->first();

        if (!$user) {
            abort(404);
        }

        $meeting = Meeting::where('creator_id', $user->id)
            ->with(['meetingTimes'])
            ->first();

        $meetingTimes = [];

        if (!empty($meeting->meetingTimes)) {
            foreach ($meeting->meetingTimes->groupBy('day_label') as $day => $meetingTime) {

                foreach ($meetingTime as $time) {
                    $can_reserve = true;

                    $explodetime = explode('-', $time->time);
                    $secondTime = dateTimeFormat(strtotime($explodetime['0']), 'H') * 3600 + dateTimeFormat(strtotime($explodetime['0']), 'i') * 60;

                    $reserveMeeting = ReserveMeeting::where('meeting_time_id', $time->id)
                        ->where('day', dateTimeFormat($timestamp, 'Y-m-d'))
                        ->where('meeting_time_id', $time->id)
                        ->first();

                    if ($reserveMeeting && ($reserveMeeting->locked_at || $reserveMeeting->reserved_at)) {
                        $can_reserve = false;
                    }

                    if ($timestamp + $secondTime < time()) {
                        $can_reserve = false;
                    }
                    $time_explode = explode('-', $time->time);
                    Carbon::parse($time_explode[0]);
                    $meetingTimes[$day]["times"][] =
                        [
                            "id" => $time->id,
                            "time" => $time->time,
                            "can_reserve" => $can_reserve

                        ];
                }
            }
        }
        //  return $meetingTimes ;
        $array = [];
        foreach ($meetingTimes as $day => $time) {
            if ($day == strtolower(dateTimeFormat($timestamp, 'l'))) {
                $array = $time['times'];

            }
        }

        return apiResponse2(1, 'retrieved', trans('public.retrieved'), [
            'count' => count($array),
            'times' => $array
        ]);

    }


}
