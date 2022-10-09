<?php

namespace App\Http\Controllers\Api\Objects;

use App\Http\Controllers\Api\Config\ConfigController;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Web\UserController;
use App\Http\Controllers\Api\Web\WebinarController;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\ReserveMeeting;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\Webinar;
use App\Models\WebinarChapter;
use App\Models\WebinarFilterOption;
use App\User;

class UserObj extends Controller
{
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
                'email' => $user->email,
                'mobile' => $user->mobile,
                'bio' => $user->bio,
                'rate' => $user->rates(),
                'avatar' => getUrl($user->getAvatar()),
                'meeting_status' => $meeting,
            ];
        });

        if ($single) {
            return $users->first();
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
            ->first();

        return self::brief($user, true);

    }

    public static function getDetailsById($id)
    {
        $user = User::where('id', $id)
            ->whereIn('role_name', [Role::$organization, Role::$teacher, Role::$user])
            ->get();

        return self::details($user, true);

    }

    public static function obj($id)
    {
        $user = User::where('id', $id)
            ->whereIn('role_name', [Role::$organization, Role::$teacher, Role::$user])
            ->first();

        return self::brief($user, true);

    }

    public static function get($id)
    {
        $user = User::where('id', $id)
            //  ->whereIn('role_name', [Role::$organization, Role::$teacher, Role::$user])
            ->first();

        return self::brief($user, true);

    }

    public static function details($users, $single = false)
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
                'email' => $user->email,
                'language' => $user->language,
                'newsletter' => ($user->newsletter) ? true : false,
                'public_message' => ($user->public_message) ? true : false,
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
             //   'ref'
                'auth_user_is_follower' => $authUserIsFollower,
                'bio' => $user->bio,
                'rate' => $user->rates(),
                'avatar' => $user->getAvatar(),
                'referral'=>null ,
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

        if ($single) {
            return [
                'user' => $users->first()
            ];
        }
        return [
            'count' => count($users),
            'users' => $users,
        ];
    }
}


