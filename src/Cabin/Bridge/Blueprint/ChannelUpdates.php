<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Engine\{
    Continuum\API,
    Database,
    Hail,
    State
};
use \GuzzleHttp\{
    Client,
    Exception\TransferException,
    Psr7\Response
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \ParagonIE\Halite\{
    Asymmetric\Crypto as AsymmetricCrypto,
    Asymmetric\SignaturePublicKey,
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
    private $channelPublicKey;
    private $urls;

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
        $channelConfig = \Airship\loadJSON(ROOT . '/config/channels.json');
        $this->channelPublicKey = new SignaturePublicKey(
            \Sodium\bin2hex($channelConfig[$channel])
        );
        $this->urls = $channelConfig[$channel]['urls'];
    }

    /**
     * Return an encoded message containing the updates,
     * and an encoded Ed25519 signature of the message.
     *
     * @param SignatureSecretKey $sk
     * @param string $challenge
     * @return string[]
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
     * Get all URLs
     *
     * @param bool $doNotShuffle
     * @return string[]
     */
    protected function getChannelURLs(bool $doNotShuffle = false): array
    {
        $state = State::instance();
        $candidates = [];
        if ($state->universal['tor-only']) {
            // Prioritize Tor Hidden Services
            $after = [];
            foreach ($this->urls as $url) {
                if (\strpos($url, '.onion') !== false) {
                    $candidates[] = $url;
                } else {
                    $after[] = $url;
                }
            }

            // Shuffle each array separately, to maintain priority;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
                \Airship\secure_shuffle($after);
            }

            foreach ($after as $url) {
                $candidates[] = $url;
            }
        } else {
            $candidates = $this->urls;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
            }
        }
        return $candidates;
    }

    /**
     * Send the HTTP request, return the
     *
     * @param string $root
     * @return string
     */
    protected function getChannelUpdates(string $root): string
    {
        $state = State::instance();
        if (IDE_HACKS) {
            $state->hail = new Hail(new Client());
        }
        foreach ($this->getChannelURLs() as $url) {
            $response = $state->hail->post(
                $url . API::get('fetch_keys') . '/' . $root
            );
            if ($response instanceof Response) {
                $code = $response->getStatusCode();
                if ($code >= 200 && $code < 300) {
                    try {
                        return $this->parseChannelUpdateResponse(
                            (string) $response->getBody()
                        );
                    } catch (\Exception $ex) {
                        // continue;
                    }
                }
            }
        }
        // When all else fails, TransferException
        throw new TransferException();
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

    /**
     * @param string $raw
     * @return array
     */
    protected function parseChannelUpdateResponse(string $raw): array
    {
        $data = \json_decode($raw, true);
        $valid = [];
        if (!empty($data['no_updates'])) {
            // Verify signature.
        } else {
            // Verify each update.
        }
        return $valid;
    }
}
