<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use \Airship\Engine\Database;
use ParagonIE\ConstantTime\Base64UrlSafe;
use \ParagonIE\Halite\{
    Asymmetric\Crypto as AsymmetricCrypto,
    Asymmetric\SignatureSecretKey,
    Structure\MerkleTree,
    Structure\Node
};

require_once __DIR__.'/gear.php';

/**
 * Class ChannelUpdates
 *
 * Manage key updates from a particular channel
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class ChannelUpdates extends BlueprintGear
{
    private $channel;

    /**
     * ChannelUpdates constructor.
     * 
     * @param Database $db
     * @param string $channel
     */
    public function __construct(Database $db, string $channel = '')
    {
        parent::__construct($db);
        $this->channel = $channel;
    }

    /**
     * @param SignatureSecretKey $sk
     * @param string $challenge
     * @return array
     */
    public function verifyUpdate(SignatureSecretKey $sk, string $challenge): array
    {
        $tree = $this->getUpdatedMerkleTree();

        $now = new \DateTime('now');
        $updates = [
            'challenge' => $challenge,
            'root' => $tree->getRoot(),
            'timestamp' => $now->format('Y-m-d\TH:i:s')
        ];
        $response = \json_encode($updates);
        return [
            Base64UrlSafe::encode($response),
            Base64UrlSafe::encode(AsymmetricCrypto::sign($response, $sk, true))
        ];
    }

    /**
     * Get key updates from the channel
     *
     * @param string $root
     * @return Node[]
     */
    protected function getKeyUpdates(string $root): array
    {
        $newNodes = [];
        /**
         * @todo Write this out.
         *
         * 1. Contact the channel, get new updates since the current Merkle root.
         * 2. Store in the database.
         * 3. If new updates, return an array of nodes.
         */
        return $newNodes;
    }

    /**
     * Get the current Merkle tree for our active channel.
     *
     * @return MerkleTree
     */
    protected function getMerkleTree(): MerkleTree
    {
        $nodeList = [];
        $queryString = 'SELECT data FROM airship_key_updates WHERE channel = ? ORDER BY keyupdateid ASC';
        foreach ($this->db->run($queryString, $this->channel) as $node) {
            $nodeList []= new Node($node['data']);
        }
        return new MerkleTree(...$nodeList);
    }

    /**
     * @return MerkleTree
     */
    protected function getUpdatedMerkleTree(): MerkleTree
    {
        $originalTree = $this->getMerkleTree();
        $newNodes = $this->getKeyUpdates(
            $originalTree->getRoot()
        );
        if (empty($newNodes)) {
            return $originalTree;
        }
        return $originalTree->getExpandedTree(...$newNodes);
    }
}
