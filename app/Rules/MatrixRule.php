<?php

namespace App\Rules;

use App\Package;
use Illuminate\Contracts\Validation\Rule;

class MatrixRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // 
        if(!empty($value)){
            $package = Package::find($value);
            $vpoints = 0;
            if ($vpoints = auth()->user()->account->binary != null) {
                $vpoints = $vpoints->vpnts;
            }
            
            if(auth()->user()->wallet->amnt > 0 || $vpoints > 0){
                if((auth()->user()->wallet->amnt >= $package->m_amnt) || ($vpoints >= $package->m_amnt)){
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Insufficient Wallet or Points Value.';
    }
}
