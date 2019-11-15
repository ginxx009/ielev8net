<?php

namespace App\Http\Controllers;

use App\Binary;
use App\User;
use App\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{

    public function create() {
        $actv_link = "wallet";
        $user = User::find(auth()->user()->id);
        
        $all_acnts = Binary::where('account_id',$user->account->id)->get();
        if($all_acnts->count() > 0){
            $all_acnts = $all_acnts->count();
        }else{
            $all_acnts = 0;
        }
        return view('wallet.create', compact('actv_link','user','all_acnts'));
    }

    public function store() {
        $data = request()->validate([
            'amnt' => ['required','numeric','min:0','not_in:0','max:'.auth()->user()->wallet->amnt],
            'wall_add' => ['required','exists:wallets,wall_add'],
        ]);

        auth()->user()->wallet->walletlogs()->create([
            'amnt' => request()->amnt,
            'stat' => 1,
            'actvty' => 'out',
            'desc' => 'transfer',
            'wall_add' => request()->wall_add,
        ]);
        $this->deduc_wal(auth()->user()->wallet->id,request()->amnt);

        $this->store_wal(
            request()->wall_add,
            request()->amnt,
            auth()->user()->wallet->wall_add);

        // dd(request()->all());
        return redirect('/dashboard');
    }

    public function store_wal($wall_add, $wall_amnt, $prsnt_wal_add) {

        $wal_res = Wallet::where('wall_add',$wall_add)->first();
        $wal_user = User::find($wal_res->user_id);
        $wal_user->wallet->walletlogs()->create([
            'amnt' => $wall_amnt,
            'stat' => 1,
            'actvty' => 'in',
            'desc' => 'transfer',
            'wall_add' => $prsnt_wal_add,
        ]);

        $this->add_wal($wal_user->wallet->id,$wall_amnt);
    }

    public function add_wal($wal_id,$wal_amnt) {
        $cur_wal = Wallet::find($wal_id);
        $cur_wal->amnt = $cur_wal->amnt + $wal_amnt;
        $cur_wal->save();
    }
    
    public function deduc_wal($wal_id,$wal_amnt) {
        $cur_wal = Wallet::find($wal_id);
        $cur_wal->amnt = $cur_wal->amnt - $wal_amnt;
        $cur_wal->save();
    }
}
