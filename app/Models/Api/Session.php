<?php
namespace App\Models\Api ;
use App\Models\Session as WebSession;

class Session extends WebSession {

    public function getDetailsAttribute(){
        return [
            'id'=>$this->id ,
            'title'=>$this->title ,
            'auth_has_read'=>$this->read,
            'status'=>$this->status ,
            'order'=>$this->order ,
            'moderator_secret'=>$this->moderator_secret ,
            'date'=>$this->date ,
            'duration'=>$this->duration ,
            'link'=>$this->link ,
            'zoom_start_link'=>$this->zoom_start_link ,
            'session_api'=>$this->session_api ,
  'api_secret'=>$this->api_secret ,
            
            
            'description'=>$this->description ,
            
            'created_at'=>$this->created_at ,   
            'updated_at'=>$this->updated_at ,
        ] ;



    }

    public function getReadAttribute(){
        $user=apiAuth();
        if(!$user){
            return null ;
        }
        
        return ($this->learningStatus()->where('user_id',$user->id)->count())?true:false ;



    }
}



 