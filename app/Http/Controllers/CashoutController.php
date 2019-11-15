<?php

namespace App\Http\Controllers;

use App\Binary;
use App\Matrix;
use App\User;
use App\Usrcode;
use Illuminate\Http\Request;

class CashoutController extends Controller
{
    // Not login user
    
    public function index() {
        $actv_link = "cashout";
        $user = User::find(auth()->user()->id);
        $cashout = $user->account->cashout;
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        $binearnings = 0;
        $vpnts = 0;
        $refearnings = $user->referral->earnings;
        
        if($user->account->binary->count() > 0){
            $binearnings = Binary::where('account_id',$user->account->id)->sum('earnings');
            $vpnts = Binary::where('account_id',$user->account->id)->sum('vpnts');
        }
        
        $cur_table_mtrx = Matrix::where('account_id',$user->account->id);
        if($cur_table_mtrx->count() > 0) {
            $cur_table_mtrx_earnings = $cur_table_mtrx->sum('earnings');
            $cur_table_mtrx_uni_bns = $cur_table_mtrx->sum('uni_bns');
        }
        
        return view('cashout.index', compact('user','actv_link','cashout','binearnings','vpnts','refearnings','cur_table_mtrx_earnings','cur_table_mtrx_uni_bns','all_acnts'));
    }
}
