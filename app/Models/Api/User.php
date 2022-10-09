<?php
namespace App\Models\Api ;
use App\User as Model;
use App\Models\ReserveMeeting ;
use App\Models\Api\Follow ;
use App\Models\Role;
use App\Models\Api\Sale ;
use App\Models\Api\Subscribe;
class User extends Model{

    public function quizResults()
    {
        return $this->hasMany('App\Models\Api\QuizzesResult', 'user_id');
    
    }

    public function meeting()
    {
        return $this->hasOne('App\Models\Api\Meeting', 'creator_id', 'id');
    }

    public function webinars()
    {
        return $this->hasMany('App\Models\Api\Webinar', 'creator_id', 'id')
            ->orWhere('teacher_id', $this->id);
    }


    public function userCreatedQuizzes()
    {
        return $this->hasMany('App\Models\Api\Quiz', 'creator_id');

    
    }
    public function userGroup()
    {
        return $this->belongsTo('App\Models\Api\GroupUser', 'id', 'user_id');
    }

    public function meetingsSaleAmount()
    {
        return Sale::where('seller_id', $this->id)
        ->whereNotNull('meeting_id')
            ->sum('amount');
    }

    public function classesSaleAmount()
    {
        return Sale::where('seller_id', $this->id)
        ->whereNotNull('webinar_id')
            ->sum('amount');
    }

    public function achievement_certificates($webinar)
    {
     
         $quiz_id=$webinar->quizzes->pluck('id') ;

       return  QuizzesResult::where('user_id',$this->id)
       ->whereIn('quiz_id',$quiz_id)
       ->where('status',QuizzesResult::$passed)
          ->get()->map(function($result){
  
              return array_merge($result->details,
              ['certificate'=>$result->certificate->brief??null ]
              ) ;
  
          }) ;
       
      
     }
 


    public function getBriefAttribute()
    {
        return [
           
            'status'=>$this->status ,
            'id' => $this->id,
            'full_name' => $this->full_name,
            'role_name' => $this->role_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'bio' => $this->bio,
            'offline' => $this->offline,
            'rate' => $this->rates(),
            'avatar' => url($this->getAvatar()),
            'meeting_status' => $this->meeting_status,
            
            'user_group'=>$this->userGroup->brief??null
        ];
    }

    public function getFinancialAttribute(){
        return [
            'account_type'=>$this->account_type ,
            'iban'=>$this->iban ,
            'account_id'=>$this->account_id ,
            'identity_scan'=>($this->identity_scan)?url($this->identity_scan):null,
            'certificate'=>($this->certificate)?url($this->certificate):null, 
            'address'=>$this->address ,

        ] ;
    }

    public function getAuthUserIsFollowerAttribute(){

        $user=apiAuth() ;
        $authUserIsFollower = false;

            if ($user) {
                $authUserIsFollower = $user->followers()->where('follower', $user->id)
                    ->where('status', Follow::$accepted)
                    ->count();
                    if($authUserIsFollower){
                        return true ;
                    }

                    return false ;
                
            }
            return $authUserIsFollower ;

    }
   
    public function followers()
    {
        return Follow::where('user_id', $this->id)->where('status', Follow::$accepted)->get();
    }

    public function following()
    {
        return Follow::where('follower', $this->id)->where('status', Follow::$accepted)->get();
    }

    public function getOrganizationTeachers()
    {
        return $this->hasMany($this, 'organ_id', 'id')->where('role_name', Role::$teacher);
    }

    public function getStudentsAttribute(){

        return Sale::whereNull('refund_at')
        ->where('seller_id', $this->id)
        ->whereNotNull('webinar_id')
      ->groupBy('buyer_id')->get()->map(function($sale){
          return $sale->buyer->brief ;
      }) ;
      
     //   ->pluck('buyer_id')
       // ->toArray();
        
 //   $user->students_count = count(array_unique($studentsIds));
    }
   
    public function getActiveSubscription(){

     return  Subscribe::getActiveSubscribe($this->id)->details??false ;
    }

    public function getHasActiveSubscriptionAttribute(){

    return  (Subscribe::getActiveSubscribe($this->id))?true:false ;
    
}

    public function getDetailsAttribute(){

            $details= [
                    'language' => $this->language,
                'newsletter' => ($this->newsletter) ? true : false,
                'public_message' => ($this->public_message) ? true : false,
               
                'verified' => $this->verified,
                'active_subscription'=>Subscribe::getActiveSubscribe($this->id)->details??null  ,
                'headline' => $this->headline,
                'public_message' => $this->public_message,
                'courses_count' => $this->webinars->count(),
                'reviews_count' => $this->reviewsCount(),
                'appointments_count' => $this->ReserveMeetings->whereNotNull('reserved_at')
                    ->where('status', '!=', ReserveMeeting::$canceled)
                    ->count(),

                    'students_count'=>$this->students->count() ,
                    'students'=>$this->students ,
                'followers' => $this->followers()->map(function($follower){
                    return $follower->user->brief ;
                }),
                'following' => $this->following()->map(function($following){
                    return $following->user->brief ;
                }),

                'auth_user_is_follower' =>$this->authUserIsFollower,
                 
                'referral'=>null ,
                'offline_message' => $this->offline_message,
                'education' => $this->userMetas()->where('name', 'education')->get()->map(function ($meta) {
                    return $meta->value;
                }),
             
                'experience' => $this->userMetas()->where('name', 'experience')->get()->map(function ($meta) {
                    return $meta->value;
                }),
                'occupations' => $this->occupations->map(function ($occupation) {
                    return $occupation->category->title;
                }),
                'about'=>$this->about ,
           
                'webinars' => $this->webinars->map(function($webinar){
                    return $webinar->brief ;
                }),
               
               'badges' => $this->badges,

                'meeting' =>($this->meeting)?$this->meeting->details:null ,
                'organization_teachers' =>$this->getOrganizationTeachers->map(function($teacher){
                    return $teacher->brief;
                }),

            ];

            return array_merge($this->brief,$details,$this->financial) ;
        ;
    }

    public function getBadgesAttribute(){

        return collect($this->getBadges())->map(function($badges){
            return [
                'id'=>$badges->id ,
                'title'=>$badges->title ,
                'type'=>$badges->type ,
                'condition'=>$badges->condition ,
                'image'=>!empty($badges->badge_id) ? url($badges->badge->image) :url( $badges->image) ,
                'locale'=>$badges->locale ,
                'description'=>$badges->description ,
                'created_at'=>$badges->created_at ,
               
            ] ;
        }) ;
        
    } 

    public function getMeetingStatusAttribute()
    {
        $meeting = 'no';
        if ($this->meeting) {
            $meeting = 'available';
            if ($this->meeting->disabled) {
                $meeting = 'unavailable';
            }
        }       

        return $meeting ;
    }
 
    

}