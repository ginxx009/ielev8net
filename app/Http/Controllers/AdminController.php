<?php

namespace App\Http\Controllers;

use App\AccountLogs;
use App\Binary;
use App\Referral;
use App\Matrix;
use App\User;
use App\Wallet;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct() {
        $this->middleware('auth');
    }
    
    public function index() {
        $user = User::find(auth()->user()->id);
        $bin_info_earnings = Binary::All()->sum('earnings');
        $bin_info_vpnts = Binary::All()->sum('vpnts');
        
        $mtrx_info_earnings = Matrix::All()->sum('earnings');
        $mtrx_info_unicycle = Matrix::All()->sum('uni_bns');
        
        $ref_info_earnings = Referral::All()->sum('earnings');
        $ref_info_in_rwds = Referral::All()->sum('in_rwds');
        
        $wal_info_amnt = Wallet::All()->sum('amnt');
        
        $acnt_sales = AccountLogs::All()->sum('amnt');
        
        $total_cashout = $bin_info_earnings+$bin_info_vpnts+$mtrx_info_earnings+$mtrx_info_unicycle+$ref_info_earnings;
        
        $actv_link = "dashboard";
        return view('admin.index', compact(
            'user',
            'actv_link',
            'bin_info_earnings',
            'bin_info_vpnts',
            'mtrx_info_earnings',
            'mtrx_info_unicycle',
            'ref_info_earnings',
            'ref_info_in_rwds',
            'wal_info_amnt',
            'acnt_sales',
            'total_cashout'));
    }
    
    public function binDetails() {
        $bin_info = Binary::All();
        
        $actv_link = "binary";
        return view('admin.binary', compact(
            'bin_info',
            'actv_link'));
    }
}