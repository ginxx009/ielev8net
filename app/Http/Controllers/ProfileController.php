<?php

namespace App\Http\Controllers;

use App\Binary;
use App\Matrix;
use App\User;
use App\Usrcode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    // Not login user
    
    public function index() {
        $actv_link = "profile";
        $user = User::find(auth()->user()->id);
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        return view('profile.index', compact('user','actv_link','all_acnts'));
    }
    
    public function createCode() {
        
        $usrcode = new Usrcode;
        $usrcode->unq_code = Str::random(6);
        $usrcode->no_acnt = 3;
        $usrcode->user_id = auth()->user()->id;
        $usrcode->stats = 0;

        $usrcode->save();
        $usrcode->push();

        return response()->json(array('success'=> true,'usrcode' => $usrcode->unq_code,'noacnt' => $usrcode->no_acnt), 200);
    }
}
