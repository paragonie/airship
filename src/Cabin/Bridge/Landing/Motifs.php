<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

require_once __DIR__.'/init_gear.php';

/**
 * Class Motifs
 * @package Airship\Cabin\Bridge\Landing
 */
class Motifs extends AdminOnly
{
    /**
     * @route motifs
     */
    public function index()
    {
        $this->lens('motifs');
    }

    /**
     * @route motifs/manage/{string}
     *
     * @param string $cabinName
     */
    public function manage(string $cabinName = '')
    {
        $this->lens('motif_manage');
    }
}