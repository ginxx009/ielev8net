<?php

namespace App\Http\Controllers;

use App\Binary;
use App\Matrix;
use App\Usrcode;
use App\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Not login user
    
    public function index() {
        // $this->authorize('view', $user->wallet);
        $user = User::find(auth()->user()->id);
        $cur_table_mtrx_earnings = 0;
        $cur_table_mtrx_uni_bns = 0;
        
        $cur_table_mtrx = Matrix::where('account_id',$user->account->id)->get();
        if($cur_table_mtrx->count() > 0) {
            $cur_table_mtrx_earnings = $cur_table_mtrx->sum('earnings');
            $cur_table_mtrx_uni_bns = $cur_table_mtrx->sum('uni_bns');
        }
        
        $binearnings = 0;
        $vpnts = 0;
        $refearnings = $user->referral->earnings;
        
        if($user->account->binary->count() > 0){
            $binearnings = Binary::where('account_id',$user->account->id)->sum('earnings');
            $vpnts = Binary::where('account_id',$user->account->id)->sum('vpnts');
        }
        
        $accu_income = $binearnings+$refearnings+$cur_table_mtrx_uni_bns+$cur_table_mtrx_earnings;
        
        $actv_link = "dashboard";
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts!=null){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        $sambin = $user->account->binary;
        
        return view('dashboard.index', compact('sambin','user','ref_info','actv_link','binearnings','vpnts','cur_table_mtrx_earnings','cur_table_mtrx_uni_bns','accu_income','all_acnts'));
    }
}
