<?php
namespace App\Models\Api ;
use App\Models\QuizzesQuestionsAnswer as WebQuizzesQuestionsAnswer;

class QuizzesQuestionsAnswer extends WebQuizzesQuestionsAnswer{

    public function getDetailsAttribute(){

        return [
            'id'=>$this->id ,
            'title'=>$this->title ,
            'correct'=>$this->correct  ,
            'image'=>getUrl($this->image) ,
            'created_at'=>$this->created_at ,
            'updated_at'=>$this->updated_at ,
            
        ] ;
    }
    
}

