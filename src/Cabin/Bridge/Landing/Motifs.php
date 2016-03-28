<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Engine\State;

require_once __DIR__.'/gear.php';

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