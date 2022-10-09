<?php
namespace App\Models\Api ;
use App\Models\TextLesson as WebTextLesson;

class TextLesson extends WebTextLesson {
    


    public function getDetailsAttribute(){
        return [
            'id'=>$this->id ,
            'title'=>$this->title ,
            'auth_has_read'=>$this->read,
            'study_time'=>$this->study_time ,
            'order'=>$this->order ,
            'created_at'=>$this->created_at ,
            'accessibility'=>$this->accessibility ,
            'status'=>$this->status ,
            'updated_at'=>$this->updated_at ,
            'summary'=>$this->summary ,
            'content'=>$this->content ,
            'locale'=>$this->locale ,
           // 'read'=>$this->read ,
            'attachments'=>$this->attachments()->get()->map(function($attachment){
              return $attachment->details ;
            }) 

        ] ;
    }
    public function getReadAttribute(){
        $user=apiAuth();
        if(!$user){
            return null ;
        }
        
        return ($this->learningStatus()->where('user_id',$user->id)->count())?true:false ;



    }

    public function attachments()
    {
        return $this->hasMany('App\Models\Api\TextLessonAttachment', 'text_lesson_id', 'id');
    }
}