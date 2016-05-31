<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\CustomPages;
use \Airship\Cabin\Hull\Exceptions\CustomPageNotFoundException;
use \Airship\Engine\{
    Gears,
    State
};
use Psr\Log\LogLevel;
use \ReCaptcha\ReCaptcha;

require_once __DIR__.'/init_gear.php';

/**
 * Class Redirects
 * @package Airship\Cabin\Bridge\Landing
 */
class Redirects extends LoggedInUsersOnly
{
    /**
     * @var CustomPages
     */
    protected $pg;

    public function airshipLand()
    {
        parent::airshipLand();
        $this->pg = $this->blueprint('CustomPages');
    }


    /**
     * @route redirects/{string}/edit/{id}
     */
    public function editRedirect(string $cabin, string $redirectId)
    {
        $cabins = $this->getCabinNamespaces();
        if (!\in_array($cabin, $cabins) && !$this->can('update')) {
            \Airship\redirect($this->airship_cabin_prefix . '/redirects');
        }
        $post = $this->post();
        $redirectId += 0;
        $redirect = $this->pg->getRedirect($cabin, $redirectId);
        if (empty($redirect)) {
            \Airship\redirect($this->airship_cabin_prefix . '/redirects/' . $cabin);
        }
        if ($post) {
            if (\Airship\all_keys_exist(['old_url', 'new_url'], $post)) {
                if ($this->pg->updateRedirect($redirectId, $post)) {
                    \Airship\redirect($this->airship_cabin_prefix . '/redirects/' . $cabin);
                } else {
                    $this->storeLensVar(
                        'form_error',
                        'Could not update redirect. Check that it does not already exist.'
                    );
                }
            }
        }
        $this->lens(
            'redirect_edit',
            [
                'cabin' => $cabin,
                'redirect' => $redirect
            ]
        );
    }

    /**
     * @route redirects/{string}
     * @param string $cabin
     */
    public function forCabin(string $cabin = '')
    {
        $cabins = $this->getCabinNamespaces();
        if (!\in_array($cabin, $cabins)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->lens(
            'redirect_for_cabin',
            [
                'cabin' => $cabin,
                'redirects' => $this->pg->getRedirectsForCabin($cabin)
            ]
        );
    }

    /**
     * @route redirects
     */
    public function index()
    {
        $this->lens(
            'redirect',
            [
                'cabins' => $this->getCabinNamespaces()
            ]
        );
    }

    /**
     * @route redirects/{string}/new
     */
    public function newRedirect(string $cabin)
    {
        $cabins = $this->getCabinNamespaces();
        if (!\in_array($cabin, $cabins) && !$this->can('create')) {
            \Airship\redirect($this->airship_cabin_prefix . '/redirects');
        }
        $post = $this->post();
        if ($post) {
            if (\Airship\all_keys_exist(['old_url', 'new_url'], $post)) {
                if (\preg_match('#^https?://#', $post['new_url'])) {
                    // Less restrictions:
                    $result = $this->pg->createDifferentCabinRedirect(
                        \trim($post['old_url'], '/'),
                        \trim($post['new_url'], '/'),
                        $cabin
                    );
                } else {
                    $result = $this->pg->createSameCabinRedirect(
                        \trim($post['old_url'], '/'),
                        \trim($post['new_url'], '/'),
                        $cabin
                    );
                }

                if ($result) {
                    \Airship\redirect($this->airship_cabin_prefix . '/redirects/' . $cabin);
                }
            }
        }
        $this->lens(
            'redirect_new',
            [
                'cabin' => $cabin
            ]
        );
    }

}
