<?php

namespace App\Http\Controllers\Api\Objects;

use App\Models\Favorite;
use App\Models\Sale;
use App\Models\Webinar;
use App\Models\WebinarChapter;

class CourseObj extends Obj
{

    static public function getById($obj)
    {

    }

    static public function brief($webinar)
    {
        if (!$webinar) {
            return null;
        }
        $user = apiAuth();
        $hasBought = $webinar->checkUserHasBought($user);
        return [
            'auth' => ($user) ? true : false,
            'id' => $webinar->id,
            'status' => $webinar->status,
            'title' => $webinar->title,
            'type' => $webinar->type,
            'live_webinar_status' => self::liveWebinarStatus($webinar),
            'auth_has_bought' => ($user) ? $hasBought : null,
            'sales' => [
                'count' => $webinar->sales->count(),
                'amount' => $webinar->sales->sum('amount'),
            ],
            'is_favorite' => self::isFavorite($webinar),
            'price' => $webinar->price,
            'price_with_discount' => ($webinar->activeSpecialOffer()) ? (
            number_format($webinar->price - ($webinar->price * $webinar->activeSpecialOffer()->percent / 100), 2)) : $webinar->price,
            'active_special_offer' => $webinar->activeSpecialOffer(),
            'discount' => $webinar->getDiscount(),
            'duration' => $webinar->duration,
            'teacher' => UserObj::get($webinar->teacher->id),
            'rate' => $webinar->getRate(),
            'rate_type' => [
                'content_quality' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('content_quality'), 1) : 0,
                'instructor_skills' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('instructor_skills'), 1) : 0,
                'purchase_worth' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('purchase_worth'), 1) : 0,
                'support_quality' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('support_quality'), 1) : 0,

            ],

            'created_at' => $webinar->created_at,
            'purchased_at' => self::purchasedDate($webinar),
            'reviews_count' => $webinar->reviews->count(),
            'start_date' => $webinar->start_date,
            'progress' => $webinar->getProgress(),
            'category' => $webinar->category->title,
            'image' => getUrl($webinar->getImage()),

        ];
    }

    static public function details($webinar)
    {
        if (!$webinar) {
            return null;
        }

        $brief = self::brief($webinar);
        $details = [
            'sessions_count' => $webinar->sessions->count(),
            'text_lessons_count' => $webinar->textLessons->count(),
            'files_count' => $webinar->files->count(),
            /*    $sessionChapters = $course->chapters->where('type', WebinarChapter::$chapterSession);
            $sessionsWithoutChapter = $course->sessions->whereNull('chapter_id');*/

            'sessions_without_chapter' => $webinar->sessions->whereNull('chapter_id')->map(function ($session) {
                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'description' => $session->description,
                    'date' => dateTimeFormat($session->date, 'j M Y | H:i')
                ];

            }),
            'sessions_with_chapter' => $webinar->chapters->where('type', WebinarChapter::$chapterSession)->map(function ($chapter) {
                $chapter->sessions->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'title' => $session->title,
                        'description' => $session->description,
                        'date' => dateTimeFormat($session->date, 'j M Y | H:i')
                    ];
                });


            }),
            'reviews' => $webinar->reviews->map(function ($review) {
                return [
                    'user' => [
                        'full_name' => $review->creator->full_name,
                        'avatar' => getUrl($review->creator->getAvatar()),
                    ],
                    'create_at' => $review->created_at,
                    'description' => $review->description,
                    'replies' => $review->comments->map(function ($reply) {
                        return [
                            'user' => [
                                'full_name' => $reply->user->full_name,
                                'avatar' => getUrl($reply->user->getAvatar()),
                            ],
                            'create_at' => $reply->created_at,
                            'comment' => $reply->comment,
                        ];

                    })


                ];
            }),
            'comments' => $webinar->comments->map(function ($item) {
                return [
                    'user' => [
                        'full_name' => $item->user->full_name,
                        'avatar' => getUrl($item->user->getAvatar()),
                    ],
                    'create_at' => $item->created_at,
                    'comment' => $item->comment,
                    'replies' => $item->replies->map(function ($reply) {
                        return [
                            'user' => [
                                'full_name' => $reply->user->full_name,
                                'avatar' => getUrl($reply->user->getAvatar()),
                            ],
                            'create_at' => $reply->created_at,
                            'comment' => $reply->comment,
                        ];

                    })
                ];
            }),
            'video_demo' => $webinar->video_demo,
            'description' => $webinar->description,
            'isDownloadable' => $webinar->isDownloadable(),
            'support' => $webinar->support ? true : false,
            'certificate' => ($webinar->quizzes->where('certificate', 1)->count() > 0) ? true : false,
            'quizzes_count' => $webinar->quizzes->where('status', \App\models\Quiz::ACTIVE)->count(),
            'students_count' => $webinar->sales->count(),
            'tags' => $webinar->tags,
            'tickets' => $webinar->tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'sub_title' => $ticket->getSubTitle(),
                    'discount' => $ticket->discount,
                    //  'order' => $ticket->order,
                    'is_valid' => $ticket->isValid(),

                ];
            }),
            'prerequisites' => $webinar->prerequisites->map(function ($prerequisite) {
                return [
                    'required' => $prerequisite->required,
                    'webinar' => self::brief($prerequisite->prerequisiteWebinar, true)
                ];
            }),
            'faqs' => $webinar->faqs

        ];
        return array_merge($brief, $details);
    }

    static public function getBriefById($id)
    {
        if (!$id) {
            return null;
        }
        $webinar = Webinar::where('status', 'active')
            ->where('private', false)->where('id', $id)->first();

        return self::brief($webinar);

    }

    static public function getDetailsById($id)
    {
        if (!$id) {
            return null;
        }
        $webinar = Webinar::where('status', 'active')
            ->where('private', false)->where('id', $id)->first();

        return self::details($webinar);
    }

    private static function liveWebinarStatus($webinar)
    {
        $live_webinar_status = false;
        if ($webinar->type == 'webinar') {
            if ($webinar->start_date > time()) {
                $live_webinar_status = 'not_conducted';
            } elseif ($webinar->isProgressing()) {
                $live_webinar_status = 'in_progress';
            } else {
                $live_webinar_status = 'finished';
            }
        }
        return $live_webinar_status;


    }

    private static function progress($webinar)
    {
        $user = apiAuth();
        /* progressbar status */
        $hasBought = $webinar->checkUserHasBought($user);
        $progress = null;
        if ($hasBought or $webinar->isWebinar()) {
            if ($webinar->isWebinar()) {
                if ($hasBought and $webinar->isProgressing()) {
                    $progress = $webinar->getProgress();
                } else {
                    $progress = $webinar->sales()->count() . '/' . $webinar->capacity;
                }
            } else {
                $progress = $webinar->getProgress();
            }
        }

        return $progress;
    }

    private static function isFavorite($webinar)
    {
        $user = apiAuth();
        $isFavorite = false;
        if (!empty($user)) {
            $isFavorite = Favorite::where('webinar_id', $webinar->id)
                ->where('user_id', $user->id)
                ->first();
        }
        return ($isFavorite) ? true : false;
    }

    private static function purchasedDate($webinar)
    {
        $user = apiAuth();
        $sale = null;
        if ($user) {
            $sale = Sale::where('buyer_id', $user->id)
                ->whereNotNull('webinar_id')
                ->where('type', 'webinar')
                ->where('webinar_id', $webinar->id)
                ->whereNull('refund_at')
                ->first();
        }


        return ($sale) ? $sale->created_at : null;
    }

}
