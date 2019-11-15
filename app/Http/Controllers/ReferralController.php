<?php

namespace App\Http\Controllers;

use App\Binary;
use App\Usrcode;
use App\User;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index() {
        $user = User::find(auth()->user()->id);
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        return view('referral.index', compact('user','all_acnts'));
    }
}
