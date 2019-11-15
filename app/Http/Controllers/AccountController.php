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
use Illuminate\Http\Request;
use App\Rules\PurchasedRule;
use App\Wallet;

class AccountController extends Controller
{
    public function index() {
        $user =  User::find(auth()->user()->id);
        $actv_link = "account";
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        return view('account.index', compact('user','actv_link','all_acnts'));
    }

    public function create() {
        $user =  User::find(auth()->user()->id);
        $package = Package::All();
        $actv_link = "account";
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        return view('account.create', compact('user','package','actv_link','all_acnts'));
    }

    public function upgrade() {
        $user =  User::find(auth()->user()->id);
        $package = Package::All();
        $actv_link = "account";
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        $allbin = $all_acnts;
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        
        return view('account.upgrade', compact('user','package','actv_link','all_acnts','allbin'));
    }

    public function store() {
        $data = request()->validate([
            'package' => [
                'required',
                'integer',
                'not_in:0',
                'min:0',
                'exists:packages,id',
                new PurchasedRule,
                function($attribute, $value, $fail){
                    if(auth()->user()->account->binary->count() > 0){
                        $gettobin = Binary::where('account_id',auth()->user()->account->id)->get();
                        if($gettobin->count() > 0){
                            if($gettobin->count() > 2){
                                $fail('You have reached the maximum account');
                            }
                        }
                    }
                },
            ],
        ]);

        $package = Package::find(request()->package);

        // Direct Referral Rewards
        if (auth()->user()->referral_id>0) {
            $this->drefRwrds(auth()->user()->referral_id,auth()->user()->referral->id,$package->dr_amnt,$package->in_rwds);
        }else{
            $reflogs = new ReferralLogs;
            $reflogs->referral_id = 1;
            $reflogs->earnings = $package->dr_amnt;
            $reflogs->in_rwds = $package->in_rwds;
            $reflogs->acnt_frm = auth()->user()->referral->id;
            $reflogs->remarks = "Company Earnings";

            $reflogs->save();
        }
        
        // Create binary accounts
        $usrbinid = auth()->user()->account->binary()->create([
            'package_id' => $package->id,
        ]);

        // Insert Logs
        $item_logs = "Binary Account";
        $desc_logs = "Package : ".$package->id;
        $amnt_logs = $package->amnt;
        $stats_logs = 1;
        $pay_meth_logs = "wallet";
        $remarks_logs = "approved";
        $this->actvLogs(auth()->user()->account->id,$item_logs, $desc_logs, $amnt_logs, $stats_logs, $pay_meth_logs, $remarks_logs);

        // Deduction of wallet
        $this->deduc_wal(auth()->user()->wallet->id,$package->amnt);
        
        $linktree = 0;
        if(auth()->user()->account->binary->count() > 0){
            $linktree = auth()->user()->id;
        }else{
            $linktree = auth()->user()->referral_id;
        }

        $link_bin_id = $this->findtree($linktree,$usrbinid->id,auth()->user()->account->id,$package->id);
        
        if($link_bin_id==0) {
            $lvl_ctr = 0;
            $ret_bin_id = 0;
            $legseg = 0;
            $usr_acnt_id = auth()->user()->account->id;
            
            $cur_pack_pv = $package->pv;
        
            $this->savelogs($ret_bin_id,$lvl_ctr,$legseg,$cur_pack_pv,$usr_acnt_id);
        }
        
        $mtrxtree = 0;
        
        $refusrinfo = Referral::find(auth()->user()->referral_id);
        $mtrxtree = $refusrinfo->user_id;
            
        $mat_acnt = Matrix::where('account_id',auth()->user()->account->id)->get();
        if($mat_acnt->count() > 0){
            $mtrxtree = auth()->user()->id;
        }

        for ($i=1; $i <= $package->id; $i++) { 
            
            $mtrx_id = auth()->user()->account->matrix()->create([
                'package_id' => $i,
                'table_no' => 1,
                'binary_id' => $usrbinid->id,
            ]);
            
            $this->unicycleBns($i, auth()->user()->referral_id, $mtrx_id->id);
            $this->mtrxTree($mtrxtree,$mtrx_id->id,$i,1);
        }
        
        // dd(request()->all());
        return redirect('/account/binary');
    }

    public function update() {
        $data = request()->validate([
            'binacnt' => ['required','not_in:0','min:0'],
            'package' => [
                'required',
                'integer',
                'not_in:0',
                'min:0',
                'exists:packages,id',
                new PurchasedRule,
                function($attribute, $value, $fail){
                    if(auth()->user()->account->binary->count() > 0){
                        $val_pack = Package::find($value);
                        if($val_pack!=null){
                            $pack_id = Binary::find(request()->binacnt);
                            
                            if($pack_id!=null){
                                if($val_pack->id <= $pack_id->package_id) {
                                    $fail($val_pack->name.' Package already purchased');
                                }
                            }else{
                                $fail('Invalid Account'); 
                            }
                        }else{
                            fail($val_pack->name.' Invalid Package');
                        }
                    }else{
                        $fail('Activate your account first');
                    }
                },
            ],
        ]);

        $package = Package::find(request()->package);
        $usr_exst_pack = Binary::find(request()->binacnt);
        $usr_exst_pack_amnt = Package::find($usr_exst_pack->package_id);

        // Direct Referral Rewards
        if (auth()->user()->referral_id>0) {
            $dr_amnt = $package->dr_amnt-$usr_exst_pack_amnt->dr_amnt;
            $in_rwds = $package->in_rwds-$usr_exst_pack_amnt->in_rwds;
            $this->drefRwrds(auth()->user()->referral_id,auth()->user()->referral->id,$dr_amnt,$in_rwds);
        }else{
            $dr_amnt = $package->dr_amnt-$usr_exst_pack_amnt->dr_amnt;
            $in_rwds = $package->in_rwds-$usr_exst_pack_amnt->in_rwds;

            $reflogs = new ReferralLogs;
            $reflogs->referral_id = 1;
            $reflogs->earnings = $dr_amnt;
            $reflogs->in_rwds = $in_rwds;
            $reflogs->acnt_frm = auth()->user()->referral->id;
            $reflogs->remarks = "Company Earnings: Direct Referrals";

            $reflogs->save();
        }

        // upgrade existing account
        $up_pack = Binary::find($usr_exst_pack->id);
        $up_pack->package_id = $package->id;

        $up_pack->save();
        $up_pack->push();

        $amnt = $package->amnt-$usr_exst_pack_amnt->amnt;
        // Insert Logs
        $item_logs = "Binary Acount ID: ".$usr_exst_pack->id." - Upgrade";
        $desc_logs = "Package : ".$package->id;
        $amnt_logs = $amnt;
        $stats_logs = 1;
        $pay_meth_logs = "wallet";
        $remarks_logs = "approved";
        $this->actvLogs(auth()->user()->account->id,$item_logs, $desc_logs, $amnt_logs, $stats_logs, $pay_meth_logs, $remarks_logs);

        // Deduction on wallet
        $this->deduc_wal(auth()->user()->wallet->id,$amnt);

        // Additional PV
        $acnt_stats = "upgrade";
        $ret_bin_id = $usr_exst_pack->binary_id;
        $legseg = $usr_exst_pack->binary_seg;
        $usr_acnt_id = auth()->user()->account->id;
        $pack_id = $package->id;
        $this->addUpPV($ret_bin_id,$legseg,$usr_acnt_id,$pack_id,$acnt_stats,$usr_exst_pack_amnt->pv);

        if($ret_bin_id==0) {
            $lvl_ctr = 0;
            $ret_bin_id = 0;
            $cur_pack = Package::find($pack_id);
            
            $cur_pack_pv = $cur_pack->pv - $usr_exst_pack_amnt->pv;
        
            $this->savelogs($ret_bin_id,$lvl_ctr,$legseg,$cur_pack_pv,$usr_acnt_id);
        }
        
        $mtrxtree = 0;
        
        $refusrinfo = Referral::find(auth()->user()->referral_id);
        $mtrxtree = $refusrinfo->user_id;
            
        $mat_acnt = Matrix::where('account_id',auth()->user()->account->id)->get();
        if($mat_acnt->count() > 0){
            $mtrxtree = auth()->user()->id;
        }

        $i = $usr_exst_pack->package_id+1;
        for (; $i <= $package->id; $i++) { 
            
            $mtrx_id = auth()->user()->account->matrix()->create([
                'package_id' => $i,
                'table_no' => 1,
                'binary_id' => $usr_exst_pack->id,
            ]);

            $this->unicycleBns($i, auth()->user()->referral_id, $mtrx_id->id);
            $this->mtrxTree($mtrxtree,$mtrx_id->id,$i,1);
        }

        return redirect('/account/binary');
    }
    
    public function deduc_wal($wal_id,$wal_amnt) {
        $cur_wal = Wallet::find($wal_id);
        $cur_wal->amnt = $cur_wal->amnt - $wal_amnt;
        $cur_wal->save();
    }

    public function findtree($cur_ref_id,$user_bin_id,$acnt_id,$pack_id){
        $currefinfo = Referral::find($cur_ref_id);
        $cur_ref_user_id = User::find($currefinfo->user_id);
        $user_bin_info = Binary::find($user_bin_id);
        $ret_bin_id = 0;

        // check if current user id have an account in binary
        if($cur_ref_user_id->account->binary->count() > 0) {
            $fstbin = Binary::where('account_id',$cur_ref_user_id->account->id)->first();
            $cur_bin_id = $fstbin;
            $nothaveseg = true;
            $strebinid = array($cur_bin_id->id);
            $retbinid = 0;
            
            for($ctr = 0; $ctr < count($strebinid); $ctr++){
                $no_acnt_link = Binary::where('binary_id',$strebinid[$ctr])->get();
                if($no_acnt_link->count() < 2){
                    $user_bin_info->binary_id = $strebinid[$ctr];
                    $legseg = 0;
                    
                    if ($no_acnt_link->count()==0) {
                        // left side of a tree
                        $user_bin_info->binary_seg = 1;
                        $legseg = 1;
                    }elseif($no_acnt_link->count()==1){
                        // right side of a tree
                        $user_bin_info->binary_seg = 2;
                        $legseg = 2;
                    }
                    
                    $user_bin_info->save();
                    $user_bin_info->push();
                    
                    $ret_bin_id = $strebinid[$ctr];
                    $acnt_stats = "new";
                    $this->addUpPV($ret_bin_id,$legseg,$user_bin_info->id,$pack_id,$acnt_stats,0);
                    break;
                }else{
                    foreach($no_acnt_link as $binlink){
                        array_push($strebinid,$binlink->id);
                    }
                }
            }
        }

        return $ret_bin_id;
    }

    public function addUpPV($link_bin_id,$acnt_lseg,$acnt_id,$pack_id,$acnt_stats,$exst_pv) {
        $lvl_ctr = 1;
        $cur_pack = Package::find($pack_id);
        
        if($acnt_stats=="new"){
            $cur_pack_pv = $cur_pack->pv;   
        }else{
            $cur_pack_pv = $cur_pack->pv-$exst_pv;  
        }
        
        $curbinid = $link_bin_id;

        while ($curbinid > 0) {
            $cur_bin_user = Binary::find($curbinid);
            $pack_limit = Package::find($cur_bin_user->package_id);
            $left_seg = 0;
            $right_seg = 0;
            $dailypairlimit = $pack_limit->p_limit;
            $remdailypair = 0;

            $add_pr = 0;
            $add_cv = 0;

            if ($acnt_lseg==1) {
                // Left side
                $left_seg = $cur_bin_user->left_cnt + $cur_pack_pv;
                $cur_bin_user->left_cnt = $left_seg;
                $right_seg = $cur_bin_user->right_cnt;
            }elseif($acnt_lseg==2){
                // Right side
                $right_seg = $cur_bin_user->right_cnt + $cur_pack_pv;
                $cur_bin_user->right_cnt = $right_seg;
                $left_seg = $cur_bin_user->left_cnt;
            }

            $totdailypair = BinaryEarnings::where('binary_id',$cur_bin_user->id)->where('created_at','>=',date('Y-m-d').' 00:00:00')->where('created_at','<=',date('Y-m-d').' 23:59:59')->sum('paired');
            $getfrst10pr = BinaryEarnings::where('binary_id',$cur_bin_user->id)->sum('paired');

            if (($left_seg > 0) && ($right_seg > 0)) {
                $p_cnt = 0;
                $noofpair = 0;
                $b_paired = 200;
                $v_points = 100;
                $v_earn = 0;
                $pair_earn = 0;
                $getfirst10limit = 8;

                if($left_seg <= $right_seg) {
                    // left side least
                    $p_cnt = $left_seg;
                }else{
                    // right side least
                    $p_cnt = $right_seg;
                }

                $noofpairg = bcdiv(($p_cnt/10),1,0);
                $cash_vchr = bcdiv(($noofpairg/5),1,0);
                $noofpair = $noofpairg-$cash_vchr;

                if ($noofpair > $cur_bin_user->paired) {
                    $add_pr = $noofpair - $cur_bin_user->paired;
                    $cur_bin_user->paired = $noofpair;
                }
                
                if ($cash_vchr > $cur_bin_user->cv) {
                    $add_cv = $cash_vchr - $cur_bin_user->cv;
                    $cur_bin_user->cv = $cash_vchr;
                    $v_earn = $v_points*$add_cv;
                    $cur_bin_user->vpnts = $cur_bin_user->vpnts+$v_earn;
                }

                if ($totdailypair<$dailypairlimit) {
                    $remdailypair = $dailypairlimit-$totdailypair;
                    $add_pr_ant = $add_pr;
                    if ($add_pr > $remdailypair) {
                        $add_pr_ant = $remdailypair;
                    }

                    $exes = 0;
                    $b_paired_add = 0;
                    $notinclde = 0;

                    if($getfrst10pr<$getfirst10limit){ 
                        $exes = $getfirst10limit-$getfrst10pr;
                        if($exes <= $add_pr_ant) {
                            $notinclde = $add_pr_ant - $exes;
                            
                            $b_paired_add = $exes * ($b_paired+50);
                            $pair_earn = $b_paired * $notinclde;
                            $pair_earn = $pair_earn+$b_paired_add;
                        }else{
                            $pair_earn = $add_pr_ant * ($b_paired+50);
                        }
                    }else{
                        $pair_earn = $b_paired * $add_pr_ant;
                    }

                    $cur_bin_user->earnings = $cur_bin_user->earnings + $pair_earn;
                }

                if(($add_pr > 0) || ($pair_earn > 0) || ($add_cv > 0)){
                    $cur_bin_user->binaryearnings()->create([
                        'paired' => $add_pr,
                        'earnings' => $pair_earn,
                        'cv' => $add_cv,
                        'vpnts' => $v_earn,
                    ]);
                }
            }

            $cur_bin_user->save();
            $cur_bin_user->push();

            $this->savelogs($cur_bin_user->id,$lvl_ctr,$acnt_lseg,$cur_pack_pv,$acnt_id);

            if($cur_bin_user->binary_id > 0){
                $next_acnt = Binary::find($cur_bin_user->binary_id);
            
                $curbinid = $next_acnt->id;
                $acnt_lseg = $cur_bin_user->binary_seg;
            }else{
                $curbinid = 0;
                $acnt_lseg = 0;
            }
            
            $lvl_ctr++;
        }
    }

    public function drefRwrds($cur_ref_id,$cur_user_ref_id,$dr_amnt,$in_rwds) {
        $cur_ref = Referral::find($cur_ref_id);
        $cur_user = User::find($cur_ref->user_id);
        $cur_user->referral->earnings = $cur_user->referral->earnings+$dr_amnt;
        $cur_user->referral->in_rwds = $cur_user->referral->in_rwds+$in_rwds;

        $cur_user->save();
        $cur_user->push();

        $cur_user->referral->referrallogs()->create([
            'earnings' => $dr_amnt,
            'in_rwds' => $in_rwds,
            'acnt_frm' => $cur_user_ref_id,
        ]);
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

    public function savelogs($link_bin_id,$bin_lvl,$acnt_lseg,$pack_pv,$acnt_id) {
        $bin_logs = new BinaryLogs;

        $bin_logs->binary_id = $link_bin_id;
        $bin_logs->binary_lvl = $bin_lvl;
        $bin_logs->leg_seg = $acnt_lseg;
        $bin_logs->binary_pv = $pack_pv;
        $bin_logs->acnt_frm = $acnt_id;

        $bin_logs->save();
        $bin_logs->push();
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
