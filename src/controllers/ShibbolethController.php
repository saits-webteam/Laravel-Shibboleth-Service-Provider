<?php namespace Saitswebuwm\Shibboleth;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Controller;

class ShibbolethController extends Controller {

    /**
     * User
     */
    protected $user;
    
    /**
     * Inject the user into this controller if present.
     */
    public function __construct(GenericUser $user = null)
    {
        $this->user = $user;
    }
    
    /**
     * Create the session, send the user away to the IDP
     * for authentication.
     */
    public function create()
    {
        return Redirect::to(Config::get('Shibboleth::shibboleth.idp_login') . '?target=' . action('Saitswebuwm\Shibboleth\ShibbolethController@idpAuthorize'));
    }
    
    /**
     * Login for users not using the IDP
     */
    public function localCreate()
    {
        return View::make(Config::get('Shibboleth::shibboleth.login_view'));
    }
    
    public function localAuthorize()
    {
        $email = \Input::get(Config::get('Shibboleth::shibboleth.local_login_user_field'));
        $password = \Input::get(Config::get('Shibboleth::shibboleth.local_login_pass_field'));

        if (Auth::attempt(array('email' => $email, 'password' => $password), true))
        {
            $user = \User::where('email', '=', $email)->first();
            if (isset($user->first_name)) Session::put('first', $user->first_name);
            if (isset($user->last_name)) Session::put('last', $user->last_name);
            if (isset($email)) Session::put('email', $user->email);

            //Set session to know user is local
            Session::put('auth_type', 'local');
            return Redirect::to('/local_landing');
        }
        else
        {
            return Redirect::to(Config::get('Shibboleth::shibboleth.login_fail'));
        }
    }
    
    public function local_landing()
    {
        return View::make(Config::get('Shibboleth::shibboleth.default_view'));
    }

    /**
     * Setup authorization based on returned server variables
     * from the IDP.
     */
    public function idpAuthorize()
    {
        $email = Request::server(Config::get('Shibboleth::shibboleth.idp_login_email'));
        $first_name = Request::server(Config::get('Shibboleth::shibboleth.idp_login_first'));
        $last_name = Request::server(Config::get('Shibboleth::shibboleth.idp_login_last'));
        
        // Attempt to login with the email, if success, update the user model
        // with data from the Shibboleth headers (if present)
        if (Auth::attempt(array('email' => $email), true))
        {
            if (isset($first_name)) Session::put('first', $first_name);
            if (isset($last_name)) Session::put('last', $last_name);
            if (isset($email)) Session::put('email', $email);

            //Set session to know user is idp
            Session::put('auth_type', 'idp');
            return Redirect::to('/idp_landing');
        }
        else
        {
            return Redirect::to(Config::get('Shibboleth::shibboleth.login_fail'));
        }
    }

    public function idp_landing()
    {
        return View::make(Config::get('Shibboleth::shibboleth.shibboleth_view'));
    }
    
    /**
     * Get current information about the session.
     */
    public function session()
    {
        echo 'Logged In: ' . ((Auth::check()) ? 'yes' : 'no') . '<br />';
        echo 'Session Information: <br />' . var_dump(Session::all());
    }
    
    /**
     * Destroy the current session and log the user out, redirect them to the main route.
     */
    public function destroy()
    {
        Auth::logout();

        if(Session::get('auth_type') == 'idp'){
            Session::flush();
            return Redirect::to(Config::get('Shibboleth::shibboleth.idp_logout'));
        }else{
            Session::flush();
            return View::make(Config::get('Shibboleth::shibboleth.local_logout'));
        }
    }
}