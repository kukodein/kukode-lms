<?php
namespace App\Models\Api ;
use App\Models\Webinar as WebWebinar;
use App\Models\Api\Favorite;
use App\Models\Api\WebinarChapter ;
use App\Models\Sale;
use App\Models\Ticket ;
use App\Models\WebinarFilterOption;
use App\Models\CourseLearning ;

use Illuminate\Support\Facades\Auth;

class Webinar extends WebWebinar{

    private $auth ;
    public function __construct()
    {
        $this->auth=Auth::user('api') ;
    }



    public function quizzes()
    {
        return $this->hasMany('App\Models\Api\Quiz', 'webinar_id', 'id');
    }
     public function getBriefAttribute()
    {
        $webinar=$this ;
        if (!$webinar) {
            return null;
        }
        $user =apiAuth();
        $hasBought = $webinar->checkUserHasBought($user);
        return [
            'auth' => ($user) ? true : false,
            'id' => $webinar->id,
            'status' => $webinar->status,
            'title' => $webinar->title,
            'type' => $webinar->type,
            'link'=>url('course/'.$this->slug) ,
            'live_webinar_status' => $this->liveWebinarStatus(),
            'auth_has_bought' => ($user) ? $hasBought : null,
            'sales' => [
                'count' => $webinar->sales->count(),
                'amount' => $webinar->sales->sum('amount'),
            ],
            'is_favorite' => $this->isFavorite(),
            'price' => $webinar->price,
            'price_with_discount' => ($webinar->activeSpecialOffer()) ? (
            number_format($webinar->price - ($webinar->price * $webinar->activeSpecialOffer()->percent / 100), 2)) : $webinar->price,

            'active_special_offer' => $webinar->activeSpecialOffer()?:null,
            'discount' => $webinar->getDiscount(),
            'best_ticket_percent'=>$this->bestTicket(true)['percent'] ,
            'best_ticket_price'=>$this->bestTicket(true)['bestTicket'] ,

            'duration' => $webinar->duration,
           'teacher' => $webinar->teacher->brief,
           'students_count' => $webinar->sales->count(),
           'rate' => $webinar->getRate(),
            'rate_type' => [
                'content_quality' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('content_quality'), 1) : 0,
                'instructor_skills' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('instructor_skills'), 1) : 0,
                'purchase_worth' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('purchase_worth'), 1) : 0,
                'support_quality' => $webinar->reviews->count() > 0 ? round($webinar->reviews->avg('support_quality'), 1) : 0,

            ],

            'created_at' => $webinar->created_at,
            'purchased_at' => $this->purchasedDate(),
            'reviews_count' => $webinar->reviews->where('status','active')->count(),
            'start_date' => $webinar->start_date,
            'progress' => $this->progress(),
            'category' => $webinar->category->title,
            'image' => getUrl($webinar->getImage()),
            'capacity'=>$this->capacity ,
            'support' => $this->support ? true : false,
            'subscribe'=>$this->subscribe?true:false  ,




        ];
    }


    public function getDetailsAttribute(){

        $user=apiAuth() ;
      //  $user=null;
        $details= [


            'auth_has_subscription'=> ($user)?$user->hasActiveSubscription:null  ,
            'reviews' => $this->reviews->where('status','active')->map(function ($review) {
                return $review->details ;
                  }),

                  'comments' => $this->comments()->where('status','active')
                  ->get()
                  ->map(function ($comment) {
                  return $comment->details ;

            }),
            '_can_add_to_cart'=>$this->canAddToCart() ,
            'video_demo' => $this->video_demo?url($this->video_demo):null,
            'isDownloadable' => $this->isDownloadable()?true:false,

            'teacher'=>$this->teacher->brief   ,
            'description' => $this->description,
            'tags' => $this->tags,

            'tickets' => $this->tickets->map(function ($ticket) {
         //       $cart->webinar->price - $cart->webinar->getDiscount($cart->ticket), 2, ".", ""
                return [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'sub_title' => $ticket->getSubTitle(),
                    'discount' => $ticket->discount,
                  //  'price_with_ticket_discount'=>$this->price -  ($ticket->discount) * $this->price/100 ,
                    'price_with_ticket_discount'=>$this->price - $this->getDiscount($ticket)  ,

                    //  'order' => $ticket->order,
                    'is_valid' => $ticket->isValid(),

                ];
            }),
            'prerequisites' => $this->prerequisites()->whereHas('prerequisiteWebinar')
            ->get()
            ->map(function ($prerequisite) {
                if($prerequisite->prerequisiteWebinar){
                    return [
                        'required' => $prerequisite->required,
                     //   'webinar' => $prerequisite->prerequisiteWebinar ,
                          'webinar' => $prerequisite->prerequisiteWebinar->brief??null ,
                    ];
                }



            }),
            'faqs' => $this->faqs->map(function($faq){
                return $faq->details ;
            }) ,

            'quizzes'=>$this->quizzes->map(function($quiz){
                return $quiz->details ;
            })  ,
            'quizzes_count'=>$this->quizzes->count() ,
            'certificate'=>$this->quizzes->where('certificate',1)->map(function($quiz){
               return $quiz->details ;
            }) ,
            'auth_certificates'=>$user?$user->achievement_certificates($this):[] ,
            'text_lesson_chapters' => $this->chapters()->where('type', WebinarChapter::$chapterTextLesson)->get()
            ->map(function($chapter){
                return $chapter->details ;
            })
            ,

            'text_lessons_count'=>$this->chapters()->where('type', WebinarChapter::$chapterTextLesson)->count() ,

            'session_chapters' => $this->chapters()->where('type', WebinarChapter::$chapterSession)->get()
            ->map(function($chapter){
                return $chapter->details ;
            }) ,
            'sessiones_count'=>$this->chapters()->where('type', WebinarChapter::$chapterSession)->count() ,

            'files_chapters' => $this->chapters()->where('type', WebinarChapter::$chapterFile)->get()
            ->map(function($chapter){
                return $chapter->details ;
            }),

            'files_count'=>$this->chapters()->where('type', WebinarChapter::$chapterFile)->count() ,



        ] ;


      // return $details ;
        return array_merge($this->brief,$details) ;


    }

    public function scopeHandleFilters($query){
        $request=request() ;
         $onlyNotConducted = $request->get('not_conducted');
         $offset = $request->get('offset', null);
         $limit = $request->get('limit', null);
         $upcoming = $request->get('upcoming', null);
         $isFree = $request->get('free', null);
         $withDiscount = $request->get('discount', null);
         $isDownloadable = $request->get('downloadable', null);
         $sort = $request->get('sort', null);
         $filterOptions = $request->get('filter_option', null);
         $type = $request->get('type', []);
         $moreOptions = $request->get('moreOptions', []);
         $category = $request->get('cat', null);


        if (!empty($onlyNotConducted)) {
            $query->where('status', 'active')
                ->where('start_date', '>', time());
        }

        if (!empty($category) and is_numeric($category)) {
            $query->where('category_id', $category);
        }
        if (!empty($upcoming) and $upcoming == 1) {
            $query->whereNotNull('start_date')
                ->where('start_date', '>=', time());
        }

        if (!empty($isFree) and $isFree == 1) {
            $query->where(function ($qu) {
                $qu->whereNull('price')
                    ->orWhere('price', '0');
            });
        }

        if (!empty($isDownloadable) and $isDownloadable == 1) {
            $query->where('downloadable', 1);
        }

        if (!empty($withDiscount) and $withDiscount == 1) {
            $now = time();
            $webinarIdsHasDiscount = [];

            $tickets = Ticket::where('start_date', '<', $now)
                ->where('end_date', '>', $now)
                ->get();
              //  dd($tickets) ;

            foreach ($tickets as $ticket) {
                if ($ticket->isValid()) {
                    $webinarIdsHasDiscount[] = $ticket->webinar_id;
                }
            }

            $webinarIdsHasDiscount = array_unique($webinarIdsHasDiscount);


            $query->whereIn('webinars.id', $webinarIdsHasDiscount);
        }

        if (!empty($sort)) {
            if ($sort == 'expensive') {
                $query->orderBy('price', 'desc');
            }

            if ($sort == 'newest') {
                $query->orderBy('created_at', 'desc');
            }

            if ($sort == 'inexpensive') {
                $query->orderBy('price', 'asc');
            }

            if ($sort == 'bestsellers') {
                $query->whereHas('sales')
                    ->with('sales')
                    ->get()
                    ->sortBy(function ($qu) {
                        return $qu->sales->count();
                    });
            }

            if ($sort == 'best_rates') {
                $query->whereHas('reviews', function ($query) {
                    $query->where('status', 'active');
                })->with('reviews')
                    ->get()
                    ->sortBy(function ($qu) {
                        return $qu->reviews->avg('rates');
                    });
            }
        }

        if (!empty($filterOptions)) {
            $webinarIdsFilterOptions = WebinarFilterOption::where('filter_option_id', $filterOptions)
                ->pluck('webinar_id')
                ->toArray();

            $query->whereIn('webinars.id', $webinarIdsFilterOptions);
        }

        if (!empty($type) ) {
            $query->where('type', $type);
        }

        if (!empty($moreOptions) and is_array($moreOptions)) {
            if (in_array('subscribe', $moreOptions)) {
                $query->where('subscribe', 1);
            }

            if (in_array('certificate_included', $moreOptions)) {
                $query->whereHas('quizzes', function ($query) {
                    $query->where('certificate', 1)
                        ->where('status', 'active');
                });
            }

            if (in_array('with_quiz', $moreOptions)) {
                $query->whereHas('quizzes', function ($query) {
                    $query->where('status', 'active');
                });
            }

            if (in_array('featured', $moreOptions)) {
                $query->whereHas('feature', function ($query) {
                    $query->whereIn('page', ['home_categories', 'categories'])
                        ->where('status', 'publish');
                });
            }
        }

        if (!empty($offset) && !empty($limit)) {
            $query->skip($offset);
        }
        if (!empty($limit)) {
            $query->take($limit);
        }

        return $query;
    }

    public function scopeValidWebinar($query){

      return $query->where('private', false)->where('status', 'active') ;
    }


    private  function liveWebinarStatus()
    {

        $live_webinar_status = null;
        if ($this->type == 'webinar') {
            if ($this->start_date > time()) {
                $live_webinar_status = 'not_conducted';
            } elseif ($this->isProgressing()) {
                $live_webinar_status = 'in_progress';
            } else {
                $live_webinar_status = 'finished';
            }
        }
        return $live_webinar_status;

    }

    private  function progress()
    {
        $user = apiAuth();
        /* progressbar status */
        $hasBought = $this->checkUserHasBought($user);
        $progress = null;
        if ($hasBought or $this->isWebinar()) {
            if ($this->isWebinar()) {
                if ($hasBought and $this->isProgressing()) {
                    $progress = $this->getProgress();

                } else {

                    $progress =(!$this->capacity)?: $this->sales()->count() / $this->capacity;
                }
            } else {
                $progress = $this->getProgress();
            }
        }

        return $progress;
    }
    public function getProgress($isLearningPage = false)
    {
        $progress = 0;
        $user=apiAuth() ;
        if ($this->isWebinar() and !empty($this->capacity)) {
            if ($this->isProgressing() and $this->checkUserHasBought($user)) {
                $user_id = $user->id;
                $sessions = $this->sessions;
                $files = $this->files;
                $passed = 0;

                foreach ($files as $file) {
                    $status = CourseLearning::where('user_id', $user_id)
                        ->where('file_id', $file->id)
                        ->first();

                    if (!empty($status)) {
                        $passed += 1;
                    }
                }

                foreach ($sessions as $session) {
                    $status = CourseLearning::where('user_id', $user_id)
                        ->where('session_id', $session->id)
                        ->first();

                    if (!empty($status)) {
                        $passed += 1;
                    }
                }

                if ($passed > 0) {
                    $progress = ($passed * 100) / ($sessions->count() + $files->count());
                }
            } else {
                $salesCount = !empty($this->sales_count) ? $this->sales_count : $this->sales()->count();

                if ($salesCount > 0) {
                    $progress = ($salesCount * 100) / $this->capacity;
                }
            }
        } elseif (!$this->isWebinar() and  $user and $this->checkUserHasBought( $user)) {
            $user_id =  $user->id;
            $files = $this->files;
            $textLessons = $this->textLessons;

            $passed = 0;

            foreach ($files as $file) {
                $status = CourseLearning::where('user_id', $user_id)
                    ->where('file_id', $file->id)
                    ->first();

                if (!empty($status)) {
                    $passed += 1;
                }
            }

            foreach ($textLessons as $textLesson) {
                $status = CourseLearning::where('user_id', $user_id)
                    ->where('text_lesson_id', $textLesson->id)
                    ->first();

                if (!empty($status)) {
                    $passed += 1;
                }
            }

            if ($passed > 0) {
                $progress = ($passed * 100) / ($files->count() + $textLessons->count());
            }
        }

        return round($progress, 2);
    }

    private  function isFavorite()
    {
        $user =apiAuth();
        $isFavorite = false;
        if (!empty($user)) {
            $isFavorite = Favorite::where('webinar_id', $this->id)
                ->where('user_id', $user->id)
                ->first();
        }
        return ($isFavorite) ? true : false;
    }

    private  function purchasedDate()
    {
        $user =apiAuth() ;
        $sale = null;
        if ($user) {
            $sale = Sale::where('buyer_id', $user->id)
                ->whereNotNull('webinar_id')
                ->where('type', 'webinar')
                ->where('webinar_id', $this->id)
                ->whereNull('refund_at')
                ->first();
        }


        return ($sale) ? $sale->created_at : null;
    }

    public function contentItems(){
       // if($this->ty)
    }

    public function chapters()
    {
        return $this->hasMany('App\Models\Api\WebinarChapter', 'webinar_id', 'id');
    }

    public function sessions()
    {
        return $this->hasMany('App\Models\Api\Session', 'webinar_id', 'id');
    }

    public function files()
    {
        return $this->hasMany('App\Models\Api\File', 'webinar_id', 'id');
    }

    public function textLessons()
    {
        return $this->hasMany('App\Models\Api\TextLesson', 'webinar_id', 'id');
    }
    public function creator()
    {
        return $this->belongsTo('App\Models\Api\User', 'creator_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo('App\Models\Api\User', 'teacher_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo('App\Models\Category', 'category_id', 'id');
    }


    public function tags()
    {
        return $this->hasMany('App\Models\Tag', 'webinar_id', 'id');
    }
    public function purchases()
    {
        return $this->hasMany('App\Models\Purchase', 'webinar_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany('App\Models\Api\Comment', 'webinar_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany('App\Models\Api\WebinarReview', 'webinar_id', 'id');
    }
    public function prerequisites()
    {
        return $this->hasMany('App\Models\Api\Prerequisite', 'webinar_id', 'id');
    }

    public function faqs()
    {
        return $this->hasMany('App\Models\Api\Faq', 'webinar_id', 'id');
    }

    public function canAddToCart($user=null){

        if(!apiAuth()){
            return null ;
        }
        if(!$this->price){
            return 'free' ;
        }
        return $this->checkCourseForSale($user) ;

    }

    public function getExpiredAttribute(){

        if($this->type==self::$webinar){
            return ($this->start_date < time()) ;
        }
        return false ;
    }

    public function getHasCapacityAttribute(){

        $salesCount = !empty($this->sales_count) ? $this->sales_count : $this->sales()->count();
        if ($this->type == 'webinar') {
            return ( $salesCount < $this->capacity);
        }
        return true ;
    }
    public function sameUser($user=null){

       $user=$user?: apiAuth();

       if ($this->creator_id == $user->id or $this->teacher_id == $user->id) {
            return true ;
        }

        return false ;
    }

    public function notPassedRequiredPrerequisite($user=null){

        $user=$user?: apiAuth();
        $isRequiredPrerequisite = false;
        $prerequisites = $this->prerequisites;
        if (count($prerequisites)) {
            foreach ($prerequisites as $prerequisite) {
                $prerequisiteWebinar = $prerequisite->prerequisiteWebinar;

                if ($prerequisite->required and !empty($prerequisiteWebinar) and !$prerequisiteWebinar->checkUserHasBought($user)) {
                    $isRequiredPrerequisite = true;
                }
            }
        }

        return $isRequiredPrerequisite ;

    }




    function checkCourseForSale( $user=null)
    {

        $course=$this ;
          $user=($user)?:apiAuth() ;


    if ($this->expired) return 'expired';

    if (!$this->hasCapacity) return 'no_capacity';

    if ($course->checkUserHasBought($user))  return 'already_bought' ;

    if ($this->sameUser()) return 'same_user' ;

    if($this->notPassedRequiredPrerequisite())   return 'required_prerequisites';

    return 'ok';
}



}
