<?php

namespace App\Models\Api ;
use App\Models\Certificate as WebCertificate ;

class Certificate extends WebCertificate {

    public function getDetailsAttribute(){
        return [
            'id'=>$this->id ,
            'user_grade'=>$this->user_grade ,
            'user'=>$this->student->brief ,
            'quiz'=>$this->quiz->details ,
            'quiz_result'=>$this->quizzesResult->details  ,
            'file'=>url($this->file) ,
            'created_at'=>$this->created_at ,
        ] ;
    }

    public function getBriefAttribute(){
        return [
            'id'=>$this->id ,
            'user_grade'=>$this->user_grade ,
            'file'=>url($this->file) ,
            'created_at'=>$this->created_at ,
        ] ;
    }
    

    public function quiz()
    {
        return $this->hasOne('App\Models\Api\Quiz', 'id', 'quiz_id');
    }

    public function student()
    {
        return $this->hasOne('App\Models\Api\User', 'id', 'student_id');
    }

    public function quizzesResult()
    {
        return $this->hasOne('App\Models\Api\QuizzesResult', 'id', 'quiz_result_id');
    }
}