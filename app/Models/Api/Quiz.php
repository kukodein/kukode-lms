<?php
namespace App\Models\Api ;
use App\Models\Quiz as WebQuiz;
use App\User ;
use App\Models\Role;
use \App\Models\Api\QuizzesResult ;
use Illuminate\Support\Facades\Auth;

use Illuminate\Auth\Events\Failed;

class Quiz extends WebQuiz{

  

    public function webinar()
    {
        return $this->belongsTo('App\Models\Api\Webinar', 'webinar_id', 'id');
    }

    public function chapter()
    {
        return $this->belongsTo('App\Models\Api\WebinarChapter', 'webinar_id', 'id');
    }
    public function quizResults()
    {
        return $this->hasMany('App\Models\Api\QuizzesResult', 'quiz_id', 'id') ;
    }
    public function quizQuestions()
    {
        return $this->hasMany('App\Models\Api\QuizzesQuestion', 'quiz_id', 'id');
    }
    
    

    public function getDetailsAttribute(){
         
         return [
            'id'=>$this->id  ,
            'questions'=>$this->quizQuestions->map(function($question){
                return $question->details ;
            }) ,
            'title'=>$this->title  ,
            'webinar'=>$this->webinar->brief ,
            'chapter'=>($this->chapter)?$this->chapter->details:null ,
            'total_mark'=>$this->total_mark ,
            'pass_mark'=>$this->pass_mark ,
            'attempt'=>$this->attempt ,
            'time'=>$this->time ,
            'status'=>$this->status ,
            'certificate'=>$this->certificate ,
            'auth_can_download_certificate'=> $this->auth_can_download_certificate ,
            'participated_count'=>$this->quizResults->count() ,
            'success_rate'=>$this->success_rate ,
            'question_count'=>$this->quizQuestions->count() ,
            'auth_status'=>$this->auth_status ,
             'average_grade'=>$this->average_grade , 
           'auth_can_try_again'=>$this->UserCanTryAgain  ,
           'latest_students'=>$this->latest_students ,

        ] ;
    }

    public function getAuthCanDownloadCertificateAttribute(){

        if(!apiAuth()){
            return null ;
        }

        $canDownloadCertificate = false;

        if(!$this->certificate){
            return false ;
        }

        $user_passed_quiz=apiAuth()->quizResults->where('quiz_id',$this->id)->where('status','passed') ;

        if($user_passed_quiz->count()){
            $canDownloadCertificate=true ;
        }

        return $canDownloadCertificate ;

    }

    public function getSuccessRateAttribute()
    {
        if($this->quizResults->count())
        {
           return round($this->quizResults->where('status',QuizzesResult::$passed)->count() /$this->quizResults->count() ) *100 ;
        }
        return null ;
       
    }

    public function getLatestStudentsAttribute(){

     ///   return 'f' ;
 return  $this->quizResults()->orderBy('created_at', 'desc')->groupBy('user_id')->get()->map(function($result){
         return   $result->user->brief 
            /// ->user()
            ;
        }) ;
     }

    public function getAverageGradeAttribute()
    {
        if($this->quizResults->count())
        {
            return round($this->quizResults->sum('user_grade')/$this->total_mark) ;
        }
        return null ;
       
    }
    
    public function getAuthStatusAttribute(){

      //  $user=User::find(922) ;
        $user=apiAuth() ;
        if(!$user){
            return null ;
        }

        
      //  $user_quiz_result=$user->quizResults->where('quiz_id',$this->id) ;
     
        if(!$user->quizResults->count() ||  !$user->quizResults->where('quiz_id',$this->id)->count()  )
        {
            return 'not_participated' ; 
        }
        $user_quiz_result=$user->quizResults->where('quiz_id',$this->id) ;


        if($user_quiz_result->where('status','passed')->count()>0){

            return 'passed' ;
        }

        return 'failed' ;

        // ->where('status','passed')->count() 
    }

    public function getUserCanTryAgainAttribute()
    {
       
        $canTryAgainQuiz=false ;
        if(!apiAuth()){
            return null ;
        }
        $user_quiz_result=apiAuth()->quizResults->where('quiz_id',$this->id) ;

        if($user_quiz_result->where('status','passed')->count()){
            return false ;
        }

       if (
           !isset($this->attempt) or 
        ($user_quiz_result->count() < $this->attempt and 
        
        !$user_quiz_result->where('status','passed')->count() )) 
        
        {
           return true ;
        }
        return false ;
    }

    public function getCountTryAgainAttribute()
    {
        if(!apiAuth()){
            return null ;
        }
        $user_quiz_result=apiAuth()->quizResults->where('quiz_id',$this->id) ;

        if(!$this->attempt){
            return 'unlimited' ;
        }

       return $this->attempt - $user_quiz_result->count() ;

     }
     public function scopeHandleFilters( $query)
     {
        $request=request() ;
         $from = $request->get('from', null);
         $to = $request->get('to', null);
           $quiz_id = $request->get('quiz_id', null);
         $total_mark = $request->get('total_mark', null);
         $status = $request->get('status', null);
         $user_id = $request->get('user_id', null);
         $creator_id = $request->get('creator_id', null);
         $webinar_id = $request->get('webinar_id',null);
         $instructor = $request->get('instructor', null);
         $open_results = $request->get('open_results', null);
 
         $query = fromAndToDateFilter($from, $to, $query, 'created_at');
 
         if (!empty($webinar_id)) {
            $query->where('webinar_id', $webinar_id);
        }
        
         if (!empty($quiz_id) and $quiz_id != 'all') {
             $query->where('quiz_id', $quiz_id);
         }
 
         if ($total_mark) {
             $query->where('total_mark', $total_mark);
         }
 
         if (!empty($user_id) and $user_id != 'all') {
             $query->where('user_id', $user_id);
         }
         if (!empty($creator_id) and $creator_id != 'all') {
             $query->where('creator_id', $creator_id);
         }
 
         if ($instructor) {
             $userIds = User::whereIn('role_name', [Role::$teacher, Role::$organization])
                 ->where('full_name', 'like', '%' . $instructor . '%')
                 ->pluck('id')->toArray();
 
             $query->whereIn('creator_id', $userIds);
         }
 
         if ($status and $status != 'all') {
             $query->where('status', strtolower($status));
         }
 
         if (!empty($open_results)) {
             $query->where('status', 'waiting');
         }
 
         return $query;
     }

     

}





