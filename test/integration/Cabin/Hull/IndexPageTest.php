<?php
declare(strict_types=1);
namespace Airship\IntegrationTests\Cabin\Hull;

use Airship\Engine\State;
use Airship\IntegrationTests\IntegrationHelper;
use PHPUnit\Framework\TestCase;

/**
 * Class IndexPage
 * @package Airship\IntegrationTests\Cabin\Hull
 */
class IndexPageTest extends TestCase
{
    /**
     * @var bool
     */
    protected $skip = false;

    public function setUp()
    {
        if (!\file_exists(ROOT . '/config/databases.json')) {
            $this->skip = true;
            $this->markTestSkipped('Integration tests disabled on uninstalled sites.');
        }
        $db = \Airship\get_database();
        try {
            $db->safeQuery('SELECT * FROM view_hull_blog_post LIMIT 1');
        } catch (\Throwable $ex) {
            $this->skip = true;
            $this->markTestSkipped('Database is not installed.');
        }
        $this->state = State::instance();
    }

    /**
     * Very basic integration test.
     */
    public function testIndexPage()
    {
        if ($this->skip) {
            return;
        }
        $response = IntegrationHelper::route('/');
        $this->assertContains(
            'airship.paragonie.com',
            (string) $response->getBody()
        );
    }
}

require_once \dirname(\dirname(__DIR__)) . '/IntegrationHelper.php';
