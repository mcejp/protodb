<?php
/*@description        Dice - A minimal Dependency Injection Container for PHP
* @author             Tom Butler tom@r.je
* @copyright          2012-2015 Tom Butler <tom@r.je>
* @link               http://r.je/dice.html
* @license            http://www.opensource.org/licenses/bsd-license.php  BSD License
* @version            2.0
*/
abstract class DiceTest extends PHPUnit\Framework\TestCase {
    protected $dice;

    public function __construct() {
        parent::__construct();

        $GLOBALS['base_path'] = '/';

        require_once 'vendor/Dice.php';

        // initialize autoloader & dependency injection
        spl_autoload_extensions(".php");
        spl_autoload_register();
    }

    protected function setUp(): void {
        parent::setUp();

        $this->dice = new \Dice\Dice();
        $this->dice = $this->dice->addRule('\candb\DB', ['shared' => true]);
    }

    protected function tearDown(): void {
        unset($this->dice);
        parent::tearDown();
    }
}
