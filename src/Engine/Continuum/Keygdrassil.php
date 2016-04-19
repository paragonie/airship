<?php
declare(strict_types=1);

namespace Airship\Engine\Continuum;

use Airship\Engine\{
    Bolt\Log,
    Contract\DBInterface,
    Hail,
    State
};
use \GuzzleHttp\Psr7\Response;
use \GuzzleHttp\Exception\TransferException;
use \ParagonIE\Halite\Structure\{
    MerkleTree,
    Node
};
use \Psr\Log\LogLevel;

/**
 * Class Keygdrassil
 *
 * (Yggdrasil = "world tree")
 *
 * This synchronizes our public keys for each channel with the rest of the network,
 * taking care to verify that a random subset of trusted peers sees the same keys.
 *
 * @package Airship\Engine\Continuum
 */
class Keyggdrasil
{
    use Log;

    protected $db;
    protected $hail;
    protected $supplierCache;
    protected $channelCache;

    /**
     * Keyggdrasil constructor.
     *
     * @param Hail|null $hail
     * @param DBInterface|null $db
     * @param array $channels
     */
    public function __construct(Hail $hail = null, DBInterface $db = null, array $channels = [])
    {
        $config = State::instance();
        if (empty($hail)) {
            $this->hail = $config->hail;
        } else {
            $this->hail = $hail;
        }

        if (!$db) {
            $db = \Airship\get_database();
        }
        $this->db = $db;

        foreach ($channels as $ch => $urls) {
            $this->channelCache[$ch] = new Channel($ch, $urls);
        }
    }

    /**
     * Launch the update process.
     *
     * This updates our keys for each channel.
     */
    public function doUpdate()
    {
        if (empty($this->channelCache)) {
            return;
        }
        foreach ($this->channelCache as $chan) {
            $this->updateChannel($chan);
        }
    }

    /**
     * Fetch all of the updates from the remote server.
     *
     * @param string $url
     * @return array
     * @throws TransferException
     */
    protected function fetchKeyUpdates(string $url): array
    {
        $response = $this->hail->post($url . API::get('fetch_keys'));
        if ($response instanceof Response) {
            $code = $response->getStatusCode();
            if ($code >= 200 && $code < 300) {
                $body = (string) $response->getBody();

            }
        }
    }

    /**
     * Get the tree of existing Merkle roots.
     *
     * @param Channel $chan
     * @return MerkleTree
     */
    protected function getMerkleTree(Channel $chan): MerkleTree
    {
        $nodeList = [];
        $queryString = 'SELECT data FROM airship_key_updates WHERE channel = ? ORDER BY keyupdateid ASC';
        foreach ($this->db->run($queryString, $chan->getName()) as $node) {
            $nodeList []= new Node($node['data']);
        }
        return new MerkleTree(...$nodeList);
    }

    /**
     * Insert/delete entries in supplier_keys, while updating the database.
     *
     * @param Channel $chan
     * @param array $updates
     */
    protected function processKeyUpdates(Channel $chan, array $updates = [])
    {

    }

    /**
     * Update a particular channel.
     *
     * 1. Identify a working URL for the channel.
     * 2. Query server for updates.
     * 3. For each update:
     *    1. Verify that our trusted notaries see the same update.
     *       (Ed25519 signature of challenge nonce || Merkle root)
     *    2. Add/remove the supplier's key.
     *
     * @param Channel $chan
     */
    protected function updateChannel(Channel $chan)
    {
        $originalTree = $this->getMerkleTree($chan);
        foreach ($chan->getAllURLs() as $url) {
            try {
                $response = $this->fetchKeyUpdates($url);
                if (!empty($response['status'])) {
                    if ($response['status'] === 'OK' && !empty($response['updates'])) {
                        if ($this->verifyResponseWithPeers($chan, $originalTree, $response)) {
                            $this->processKeyUpdates($chan, $response['updates']);
                            return;
                        }
                    }
                    // Received a successful API response.
                    return;
                }
            } catch (TransferException $ex) {
                // Should we log here?
                $this->log(
                    'Channel update error',
                    LogLevel::NOTICE,
                    \Airship\throwableToArray($ex)
                );
            }
        }
        // IF we get HERE, we've run out of updates to try.

        $this->log('Channel update concluded with no changes', LogLevel::ALERT);
    }

    /**
     * Return true if the Merkle roots match.
     *
     * This employs challenge-response authentication:
     * @ref https://github.com/paragonie/airship/issues/13
     *
     * @param Channel $channel
     * @param MerkleTree $originalTree
     * @param array $response
     * @return bool
     */
    protected function verifyResponseWithPeers(
        Channel $channel,
        MerkleTree $originalTree,
        array $response = []
    ): bool {

    }
}
