<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountLogs;
use App\Binary;
use App\BinaryEarnings;
use App\BinaryLogs;
use App\Matrix;
use App\MatrixLogs;
use App\Package;
use App\Referral;
use App\ReferralLogs;
use App\Usrcode;
use App\User;
use App\Wallet;
use Illuminate\Http\Request;

class MatrixController extends Controller
{
    public function index($package) {
        $user = User::find(auth()->user()->id);
        $cur_package = Package::find($package);
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts!=null){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        if($cur_package != null){
            $cur_table_mtrx = Matrix::where('account_id',$user->account->id)->where('package_id',$cur_package->id);
            $cur_table_mtrx = $cur_table_mtrx->get();
            $cur_table_mtrx_earnings = $cur_table_mtrx->sum('earnings');
            $cur_table_mtrx_uni_bns = $cur_table_mtrx->sum('uni_bns');
            $tree_info = array();
            
            $actv_link = "matrix";
            $cur_table_mtrx = Matrix::where('account_id',$user->account->id)->where('package_id',$cur_package->id)->orderBy('table_no','DESC')->first();
            if($cur_table_mtrx != null) {
                
                $pack = Package::find($cur_table_mtrx->package_id);
                $tree_info = array(array($cur_table_mtrx->id,"Top",$user->name,$pack->name,"","https://system.ielev8net.com/img/user.png"));
    
                $treearray = array(array($cur_table_mtrx->id));
                $lvlarray = 0;
    
                for($a=0; $a < count($treearray);$a++){
                    $accntarray = array();
                    for ($i=0; $i < count($treearray[$a]); $i++) { 
                        $acnt_info = Matrix::where('matrix_id',$treearray[$a][$i])->get();
                    
                        if($acnt_info != null) {
                            foreach ($acnt_info as $info) {
                                $acnt_info_get = Account::find($info->account_id);
                                $tree_usr = User::find($acnt_info_get->user_id);
                                $pack_tree = Package::find($info->package_id);
                                array_push($accntarray,$info->id);
                                array_push($tree_info,array($info->id,$treearray[$a][$i],$tree_usr->name,$pack_tree->name,"","https://system.ielev8net.com/img/user.png"));
                            }
        
                        }
                    }
                    if (count($accntarray)>0) {
                        array_push($treearray,$accntarray);
                    }
                }
            }
            
            return view('matrix.index', compact('user','actv_link','cur_package','tree_info','cur_table_mtrx','cur_table_mtrx_earnings','cur_table_mtrx_uni_bns','all_acnts'));
        }else{
            return view('matrix.index',compact('all_acnts'));
        }
    }

    public function create($cur_package) {
        $user =  User::find(auth()->user()->id);
        $package = Package::find($cur_package);
        $actv_link = "matrix";
        
        $vpnts = 0;
        
        if($user->account->binary->count() > 0){
            $vpnts = Binary::where('account_id',$user->account->id)->sum('vpnts');
        }
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        return view('matrix.create', compact('user','package','actv_link','vpnts','all_acnts'));
    }

    public function store() {
        $data = request()->validate([
            'source' => [
                'required',
            ],
            'package' => [
                'required',
                'integer',
                'not_in:0',
                'min:0',
                'exists:packages,id',
                function($attribute, $value, $fail){
                    if ($usr_bin_info = auth()->user()->account->binary->count() > 0) {
                        $source = request()->source;
                        $package = Package::find($value);
                        if($package != null){
                            if($source == "wallet"){
                                if (auth()->user()->wallet->amnt < $package->m_amnt) {
                                    $fail('Insufficient wallet');
                                }
                            }elseif($source == "points"){
                                $vpnts = Binary::where('account_id',auth()->user()->account->id)->sum('vpnts');  
                                if ($vpnts < $package->m_amnt) {
                                    $fail('Insufficient point/s');
                                }
                            }else{
                                $fail('No Source Selected');
                            }
                        }else{
                            $fail('No Package Selected');
                        }
                    }else{
                        $fail('Activate your account first.');
                    }
                },
                function($attribute, $value, $fail){
                    $last_pack_exist = Binary::where('account_id',auth()->user()->account->id)->orderBy('package_id','DESC')->first();
                    if($last_pack_exist != null){
                        if($value > $last_pack_exist->package_id) {
                            $fail('Upgrade your account first account');
                        }
                    }else{
                        $fail('Activate your account first.');
                    }
                },
            ],
        ]);

        $source_funds = 0;
        $pay_meth_logs = "";
        $req_source = request()->source;
        $usr_bin_id = auth()->user()->account->id;
        $usr_bin_info = Binary::where('account_id',$usr_bin_id)->sum('vpnts');  

        $package = Package::find(request()->package);

        if ($req_source == "wallet") {
            $source_funds = auth()->user()->wallet->amnt - $package->m_amnt;
            $this->deduc_wal(auth()->user()->wallet->id,$package->m_amnt);
            $pay_meth_logs = "wallet";
        }elseif($req_source == "points"){
            $source_funds = $usr_bin_info - $package->m_amnt;
            $this->deduc_pnts(auth()->user()->account->binary->id,$package->m_amnt);
            $pay_meth_logs = "points";
        }

        $item_logs = "Matrix Account";
        $desc_logs = "Package : ".$package->id;
        $amnt_logs = $package->m_amnt;
        $stats_logs = 1;
        $remarks_logs = "approved";
        
        // Insert logs
        $this->actvLogs($usr_bin_id,$item_logs, $desc_logs, $amnt_logs, $stats_logs, $pay_meth_logs, $remarks_logs);
        
        $mtrxtree = 0;
        
        $refusrinfo = Referral::find(auth()->user()->referral_id);
        $mtrxtree = $refusrinfo->user_id;
            
        $mat_acnt = Matrix::where('account_id',auth()->user()->account->id)->get();
        if($mat_acnt->count() > 0){
            $mtrxtree = auth()->user()->id;
        }
        
        // Get last package purchased
        $last_pack_exist2 = 0;
        $last_pack_exist = Binary::where('account_id',$usr_bin_id)->where('package_id',$package->id)->first();
        if($last_pack_exist!=null){
            $last_pack_exist2 = $last_pack_exist->id;
        }else{
            $last_pack_exist = Binary::where('account_id',$usr_bin_id)->orderBy('package_id','DESC')->first();
            $last_pack_exist2 = $last_pack_exist->id;
        }
        
        $mtrx_id = auth()->user()->account->matrix()->create([
            'package_id' => $package->id,
            'table_no' => 1,
            'binary_id' => $last_pack_exist2,
        ]);

        $this->unicycleBns($package->id, auth()->user()->referral_id, $mtrx_id->id);
        $this->mtrxTree($mtrxtree,$mtrx_id->id,$package->id,1);

        // dd(request()->all());
        return redirect('/m/create/'.$package->id);
    }
    
    public function deduc_wal($wal_id,$wal_amnt) {
        $cur_wal = Wallet::find($wal_id);
        $cur_wal->amnt = $cur_wal->amnt - $wal_amnt;
        $cur_wal->save();
    }

    public function deduc_pnts($wal_id,$wal_amnt) {
        $cur_wal = Wallet::find($wal_id);
        $cur_wal->amnt = $cur_wal->amnt - $wal_amnt;
        $cur_wal->save();
    }

    public function actvLogs($account_id,$item, $desc, $amnt, $stats, $pay_meth, $remarks) {
        $acnt_logs = new AccountLogs;

        $acnt_logs->account_id = $account_id;
        $acnt_logs->item = $item;
        $acnt_logs->description = $desc;
        $acnt_logs->amnt = $amnt;
        $acnt_logs->stats = $stats;
        $acnt_logs->pay_meth = $pay_meth;
        $acnt_logs->remarks = $remarks;

        $acnt_logs->save();
        $acnt_logs->push();
    }

    public function mtrxTree($ref_id,$user_mtrx_id,$pack_id,$table_no) {
        $cur_ref_user_id = User::find($ref_id);
        $cur_mtrx_id = Matrix::find($user_mtrx_id);
        $ret_bin_id = 0;
        
        $curmtrxinfo = Matrix::where('account_id',$cur_ref_user_id->account->id)->get();
        
        if($curmtrxinfo->count() > 0){
            $res_table_1 = Matrix::where('account_id',$cur_ref_user_id->account->id)->where('package_id',$pack_id)->where('table_no',$table_no)->first(); 
            
            if($res_table_1 != null){
                $alldownaccnt = array($res_table_1->id);
                
                for($ar_ctr = 0;$ar_ctr < count($alldownaccnt);$ar_ctr++){
                    $no_mtrx_link = Matrix::where('matrix_id',$alldownaccnt[$ar_ctr])->get();
                    
                    if ($no_mtrx_link->count() < 3) {
                        
                        $cur_mtrx_id->matrix_id = $alldownaccnt[$ar_ctr];
                        if($no_mtrx_link->count() == 0) {
                            $cur_mtrx_id->l_seg = 1;
                        }elseif ($no_mtrx_link->count() == 1) {
                            $cur_mtrx_id->l_seg = 2;
                        }elseif ($no_mtrx_link->count() == 2) {
                            $cur_mtrx_id->l_seg = 3;
                        }
    
                        $ret_bin_id = $alldownaccnt[$ar_ctr];
                        $cur_mtrx_id->save();
                        $cur_mtrx_id->push();
                        $this->addUpLvlAcnt($ret_bin_id,$cur_mtrx_id->id);
                        break;
                    }else{
                        foreach ($no_mtrx_link as $mtrx_lnk) { 
                            array_push($alldownaccnt,$mtrx_lnk->id);
                        }
                    }
                }
            }
        }
    }
    
    public function addUpLvlAcnt($ret_bin_id,$cur_mtrx_id) {
        $lvl_ctr = 1;
        $lvl1_ctr = 0;
        $lvl2_ctr = 0;

        $lvl1 = 0;
        $lvl2 = 0;
        $table_no = 0;
        $earnings = 0;
        $uni_bns = 0;
        $prem_bns = 0;
        $acnt_frm = $cur_mtrx_id;

        while ($lvl_ctr <= 2) {
            $cur_mtrx_strctre = Matrix::find($ret_bin_id);
            $table_no = $cur_mtrx_strctre->table_no;
            if ($lvl_ctr==1) {
                $cur_mtrx_strctre->lvl1 = $cur_mtrx_strctre->lvl1+1;
                $lvl1 = 1;
            }else{
                $lvl1_ctr = $cur_mtrx_strctre->lvl1;
                $lvl2_ctr = $cur_mtrx_strctre->lvl2+1;
                $cur_mtrx_strctre->lvl2 = $lvl2_ctr;
                $lvl2 = 1;

                if($lvl1_ctr > 2 && $lvl2_ctr > 8){
                    // graduate
                    $cur_mtrx_pack = Package::find($cur_mtrx_strctre->package_id);
                    $cur_mtrx_strctre->earnings = $cur_mtrx_pack->m_cycle;
                    $prev_table_no = $cur_mtrx_strctre->table_no;
                    $next_table_no = $prev_table_no+1;
                    $earnings = $cur_mtrx_pack->m_cycle;

                    $accnt_id_cur = Account::find($cur_mtrx_strctre->account_id);
                    $user_id_cur = User::find($accnt_id_cur->user_id);

                    $this->gradNewAcnt($cur_mtrx_strctre->id,$cur_mtrx_strctre->account_id,$cur_mtrx_strctre->l_seg,$cur_mtrx_strctre->matrix_id,$cur_mtrx_strctre->package_id,$next_table_no);
                    $this->unicycleBns($cur_mtrx_pack->id, $user_id_cur->referral_id, $cur_mtrx_strctre->id);
                }
            }

            $ret_bin_id = $cur_mtrx_strctre->matrix_id;
            $cur_mtrx_strctre->save();
            $cur_mtrx_strctre->push();

            $this->mtrxLogs($cur_mtrx_strctre->id,$lvl1,$lvl2,$table_no,$earnings,$uni_bns,$prem_bns,$acnt_frm);
            
            if ($ret_bin_id > 0) {
                $lvl1 = 0;
                $lvl2 = 0;
                
                $lvl_ctr++;
            }else{
                $lvl_ctr=3;
                break;
            }
        }
    }

    public function gradNewAcnt($cur_mtrx_id,$cur_account_id,$cur_l_seg,$upline_mtrx_id,$package_id,$next_table_no) {
        if ($upline_mtrx_id>0) {
            $cur_mtrx_strctre = Matrix::find($upline_mtrx_id);
            $res_cur_table_mtrx = Matrix::where('account_id',$cur_mtrx_strctre->account_id)->where('package_id',$package_id)->where('table_no',$next_table_no)->first();
        
            $new_mtrx_next_table = new Matrix;
            $new_mtrx_next_table->account_id = $cur_account_id;
            $new_mtrx_next_table->package_id = $package_id;
            $new_mtrx_next_table->table_no = $next_table_no;
            $new_mtrx_next_table->matrix_id = $res_cur_table_mtrx->id;
            $new_mtrx_next_table->l_seg = $cur_l_seg;

            $new_mtrx_next_table->save();
            $new_mtrx_next_table->push();

            $this->addUpLvlAcnt($res_cur_table_mtrx->id,$new_mtrx_next_table->id);
        }else{
            
            $new_mtrx_next_table = new Matrix;
            $new_mtrx_next_table->account_id = $cur_account_id;
            $new_mtrx_next_table->package_id = $package_id;
            $new_mtrx_next_table->table_no = $next_table_no;
            $new_mtrx_next_table->l_seg = $cur_l_seg;

            $new_mtrx_next_table->save();
            $new_mtrx_next_table->push();
        }

        $prev_cur_table_mtrx = Matrix::where('matrix_id',$cur_mtrx_id)->get();

        if ($prev_cur_table_mtrx->count() > 0) {
            foreach ($prev_cur_table_mtrx  as $mtrx) {
                $prev_table_no_link = Matrix::where('account_id',$mtrx->account_id)->where('package_id',$package_id)->where('table_no',$next_table_no)->first();
                if ($prev_table_no_link != null) {
                    $cur_table_mtrx = Matrix::where('account_id',$cur_account_id)->where('package_id',$package_id)->where('table_no',$next_table_no)->first();
                    $prev_table_no_link->matrix_id = $cur_table_mtrx->id;

                    $prev_table_no_link->save();
                    $prev_table_no_link->push();
                }
            }
        }
    }

    public function mtrxLogs($matrix_id,$lvl1,$lvl2,$table_no,$earnings,$uni_bns,$prem_bns,$acnt_frm) {

        $new_mtrx_logs = new MatrixLogs;
        $new_mtrx_logs->matrix_id = $matrix_id;
        $new_mtrx_logs->lvl1 = $lvl1;
        $new_mtrx_logs->lvl2 = $lvl2;
        $new_mtrx_logs->table_no = $table_no;
        $new_mtrx_logs->earnings = $earnings;
        $new_mtrx_logs->uni_bns = $uni_bns;
        $new_mtrx_logs->prem_bns = $prem_bns;
        $new_mtrx_logs->acnt_frm = $acnt_frm;

        $new_mtrx_logs->save();
        $new_mtrx_logs->push();
    }

    public function unicycleBns($package_id, $referral_id, $user_mtrx_id) {
        $package = Package::find($package_id);
        $cycle_dref = 0;
        $dref_bns = 0;
        for ($i=1; $i <= $package->uni_cycle; $i++) { 
            if($referral_id>0){
                $cur_user_info = User::find($referral_id);
                $cur_user_mtrx = Matrix::where('account_id',$cur_user_info->account->id)->where('package_id',$package_id)->orderBy('table_no','DESC')->first();
                if($cur_user_mtrx != null) {
                    if($i == 1) {
                        $cycle_dref = $package->uni_bns_1lvl;
                    }elseif($i == 2) {
                        $cycle_dref = $package->uni_bns_2lvl;
                    }elseif($i == 3) {
                        $cycle_dref = $package->uni_bns_3lvl;
                    }elseif($i == 4) {
                        $cycle_dref = $package->uni_bns_4lvl;
                    }elseif($i == 5) {
                        $cycle_dref = $package->uni_bns_5lvl;
                    }elseif($i == 6) {
                        $cycle_dref = $package->uni_bns_6lvl;
                    }elseif($i == 7) {
                        $cycle_dref = $package->uni_bns_7lvl;
                    }elseif($i == 8) {
                        $cycle_dref = $package->uni_bns_8lvl;
                    }
                    $cur_user_mtrx->uni_bns = $cur_user_mtrx->uni_bns+$cycle_dref;

                    $cur_user_mtrx->save();
                    $cur_user_mtrx->push();

                    $this->mtrxLogs($cur_user_mtrx->id,0,0,$cur_user_mtrx->table_no,0,$cycle_dref,0,$user_mtrx_id);
                }
            }
            $referral_id = $cur_user_info->referral_id;
        }
    }
}
