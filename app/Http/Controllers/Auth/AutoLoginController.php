<?php
/**
 * Created by Justin McCombs.
 * Date: 9/25/15
 * Time: 1:52 PM
 */

namespace App\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Ninja\Repositories\AccountRepository;
use Illuminate\Http\Request;

class AutoLoginController extends Controller
{

    /**
     * @var AccountRepository
     */
    private $accountRepository;

    public function __construct(AccountRepository $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    public function login(Request $request)
    {
//        dd(\Crypt::encrypt('jtmccombs@gmail.com'));
        try {
            $email = \Crypt::decrypt($request->get('token'));
        }catch(\Exception $e) {
            return abort('403', 'Forbidden');
        }

        $user = User::whereEmail($email)->first();

        if ( ! $user )
            return abort('403', 'Forbidden');

        if ( ! $user->account )
        {
            $b2bCompany = \DB::connection('mysql-b2b')->table('companies')->where('user_id', '=', $user->id)->first();
//            $b2bCompany = false;
            $accountName = ($b2bCompany) ? $b2bCompany->company_name : $user->email;
            $account = new Account();
            $account->ip = $request->getClientIp();
            $account->name = $accountName;
            $account->account_key = str_random(RANDOM_KEY_LENGTH);
            $account->save();
            $user->account_id = $account->id;
            $user->registered = true;
            $user->save();


            $exists = \DB::connection('mysql')->table('users')->whereId($user->id)->count();
            if ( ! $exists )
            {
                \DB::connection('mysql')->table('users')->insert([
                    'id' => $user->id,
                    'account_id' => $user->account_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'deleted_at' => $user->deleted_at,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone' => $user->phone,
                    'username' => $user->username,
                    'email' => $user->email,
                    'password' => $user->password,
                    'confirmation_code' => $user->confirmation_code,
                    'registered' => $user->registered,
                    'confirmed' => $user->confirmed,
                    'notify_sent' => $user->notify_sent,
                    'notify_viewed' => $user->notify_viewed,
                    'notify_paid' => $user->notify_paid,
                    'public_id' => $user->public_id,
                    'force_pdfjs' => false,
                    'remember_token' => $user->remember_token,
                    'news_feed_id' => $user->news_feed_id,
                    'notify_approved' => $user->notify_approved,
                    'failed_logins' => $user->failed_logins,
                    'dark_mode' => $user->dark_mode,
                    'referral_code' => $user->referral_code,

                ]);
            }


        }

        \Auth::loginUsingId($user->id);

        return redirect('/');

    }

}