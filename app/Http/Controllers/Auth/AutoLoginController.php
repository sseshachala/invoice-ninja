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
            $account = new Account();
            $account->ip = $request->getClientIp();
            $account->account_key = str_random(RANDOM_KEY_LENGTH);
            $account->save();
            $user->account_id = $account->id;
            $user->registered = true;
            $user->save();
        }

        \Auth::loginUsingId($user->id);

        return redirect('/');
    }

}