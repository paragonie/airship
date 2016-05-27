<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\UserAccounts;

require_once __DIR__.'/gear.php';

/**
 * Class PublicAjax
 * @package Airship\Cabin\Bridge\Landing
 */
class PublicAjax extends LandingGear
{
    /**
     * @var UserAccounts
     */
    protected $acct;

    /**
     * Post-constructor
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->acct = $this->blueprint('UserAccounts');
    }

    /**
     * AJAX + JSON API to see if a username is taken or invalid.
     */
    public function checkUsername()
    {
        // If you didn't supply a username, it's not available.
        if (!\array_key_exists('username', $_POST)) {
            \Airship\json_response([
                'status' => 'error',
                'message' => \__('You did not supply a username'),
                'result' => []
            ]);
        }

        // Did someone else reserve this username?
        if ($this->acct->isUsernameTaken($_POST['username'])) {
            \Airship\json_response([
                'status' => 'success',
                'message' => \__('Username is not available'),
                'result' => [
                    'available' => false
                ]
            ]);
        }

        if ($this->acct->isUsernameInvalid($_POST['username'])) {
            \Airship\json_response([
                'status' => 'success',
                'message' => \__('Username is not available'),
                'result' => [
                    'available' => false
                ]
            ]);
        }

        // The username has not been reserved.
        \Airship\json_response([
            'status' => 'success',
            'message' => \__('Username is available'),
            'result' => [
                'available' => true
            ]
        ]);
    }
}
