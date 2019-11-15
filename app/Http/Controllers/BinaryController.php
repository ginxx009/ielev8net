<?php

namespace App\Http\Controllers;

use App\Account;
use App\Binary;
use App\BinaryLogs;
use App\Package;
use App\User;
use App\Usrcode;
use Illuminate\Http\Request;

class BinaryController extends Controller
{
    public function index() {
        $user = User::find(auth()->user()->id);
        
        // $all_bin = Binary::All()->count();
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        
        $bin_acnts = $all_acnts;
        if($all_acnts->count()>0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        $actv_link = "account";
        $bin_logs = null;
        $bin_earn = null;
        $tree_id = 1;
        $tree_link = 1;
        $tree_info = array();
        if($user->account->binary->count() > 0) {
            $topacnt = Binary::where('account_id',$user->account->id)->first();
            $pack = Package::find($topacnt->package_id);
            $tree_info = array(array($topacnt->id,"Top",$user->name,$pack->name,"Left (".$topacnt->left_cnt.") | Right (".$topacnt->right_cnt.")","https://system.ielev8net.com/img/user.png"));
            
            $bin_logs = $topacnt->binarylogs;
            $bin_earn = $topacnt->binaryearnings;

            $treearray = array(array($topacnt->id));
            $lvlarray = 0;

            for($a=0; $a < count($treearray);$a++){
                $accntarray = array();
                for ($i=0; $i < count($treearray[$a]); $i++) { 
                    $acnt_info = Binary::where('binary_id',$treearray[$a][$i])->get();
                    $emp_ctr = 0;
                    if($acnt_info->count() > 0) {
                        foreach ($acnt_info as $info) {
                            // $emp_ctr++;
                            $tree_usr = Account::find($info->account_id);
                            $acntinfo = User::find($tree_usr->user_id);
                            $pack_tree = Package::find($info->package_id);
                            array_push($accntarray,$info->id);
                            array_push($tree_info,array($info->id,$treearray[$a][$i],$acntinfo->name,$pack_tree->name,"Left (".$info->left_cnt.") | Right (".$info->right_cnt.")","https://system.ielev8net.com/img/user.png"));
                        }
                        
                        // if($emp_ctr<2){
                        //     $all_bin++;
                        //     array_push($tree_info,array($all_bin,$treearray[$a][$i],"Empty","-","Add Account","https://system.ielev8net.com/img/empty_user.png"));
                        // }
                    }
                    // else{
                    //     for($x=0;$x < 2; $x++){
                    //         $all_bin++;
                    //         array_push($tree_info,array($all_bin,$treearray[$a][$i],"Empty","-","Add Account","https://system.ielev8net.com/img/empty_user.png"));
                    //     }
                    // }
                }
                if (count($accntarray)>0) {
                    array_push($treearray,$accntarray);
                }
            }
        }
        
        return view('binary.index', compact('user','bin_logs','bin_earn','tree_info','actv_link','all_acnts','bin_acnts'));
    }
}
