<?php

namespace Phoenix\Tests\Migration;

use Phoenix\Config\Config;
use Phoenix\Exception\DatabaseQueryExecuteException;
use Phoenix\Exception\InvalidArgumentValueException;
use Phoenix\Migration\AbstractMigration;
use Phoenix\Migration\Init\Init;
use Phoenix\Migration\Manager;
use Phoenix\Tests\Helpers\Adapter\MysqlCleanupAdapter;
use Phoenix\Tests\Helpers\Pdo\MysqlPdo;
use Phoenix\Tests\Mock\Migration\FakeMigration;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{
    private $manager;

    private $adapter;

    private $initMigration;

    protected function setUp(): void
    {
        $config = new Config([
            'migration_dirs' => [
                __DIR__ . '/../fake/structure/migration_directory_1/',
            ],
            'environments' => [
                'mysql' => [
                    'adapter' => 'mysql',
                    'host' => getenv('PHOENIX_MYSQL_HOST'),
                    'port' => getenv('PHOENIX_MYSQL_PORT'),
                    'username' => getenv('PHOENIX_MYSQL_USERNAME'),
                    'password' => getenv('PHOENIX_MYSQL_PASSWORD'),
                    'db_name' => getenv('PHOENIX_MYSQL_DATABASE'),
                    'charset' => getenv('PHOENIX_MYSQL_CHARSET'),
                ],
            ],
        ]);
        $pdo = new MysqlPdo();
        $adapter = new MysqlCleanupAdapter($pdo);
        $adapter->cleanupDatabase();

        $pdo = new MysqlPdo(getenv('PHOENIX_MYSQL_DATABASE'));
        $this->adapter = new MysqlCleanupAdapter($pdo);

        $this->initMigration = new Init($this->adapter, $config->getLogTableName());
        $this->initMigration->migrate();

        $this->manager = new Manager($config, $this->adapter);
    }

    public function testMigrations()
    {
        $executedMigrations = $this->manager->executedMigrations();
        $this->assertTrue(is_array($executedMigrations));
        $this->assertCount(0, $executedMigrations);

        $migrations = $this->manager->findMigrationsToExecute();
        $this->checkMigrations($migrations, 2, [0 => '20150428140909', 1 => '20150518091732']);
        $this->assertTrue(is_array($migrations));

        $firstUpMigration = $this->manager->findMigrationsToExecute('up', 'first');
        $this->checkMigrations($firstUpMigration, 1, [0 => '20150428140909']);

        $downMigrations = $this->manager->findMigrationsToExecute('down');
        $this->checkMigrations($downMigrations, 0, []);

        $count = 0;
        foreach ($migrations as $migration) {
            $migration->migrate();
            $this->manager->logExecution($migration);
            $count++;
            $this->assertTrue(is_array($this->manager->executedMigrations()));
            $this->assertCount($count, $this->manager->executedMigrations());

            $migration->rollback();
            $this->manager->removeExecution($migration);
            $count--;
            $this->assertTrue(is_array($this->manager->executedMigrations()));
            $this->assertCount($count, $this->manager->executedMigrations());

            $migration->migrate();
            $this->manager->logExecution($migration);
            $count++;
            $this->assertTrue(is_array($this->manager->executedMigrations()));
            $this->assertCount($count, $this->manager->executedMigrations());
        }

        $this->assertEquals(2, $count);
        $this->assertCount($count, $migrations);

        $firstDownMigration = $this->manager->findMigrationsToExecute('down', 'first');
        $this->checkMigrations($firstDownMigration, 1, [0 => '20150518091732']);

        $downMigrations = $this->manager->findMigrationsToExecute('down');
        $this->checkMigrations($downMigrations, 2, [0 => '20150518091732', 1 => '20150428140909']);
    }

    public function testWrongType()
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Type "type" is not allowed.');
        $this->manager->findMigrationsToExecute('type');
    }

    public function testWrongTarget()
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Target "target" is not allowed.');
        $this->manager->findMigrationsToExecute('up', 'target');
    }

    public function testSkippingNonExistingMigration()
    {
        $executedMigrations = $this->manager->executedMigrations();
        $this->assertTrue(is_array($executedMigrations));
        $this->assertCount(0, $executedMigrations);

        $migrations = $this->manager->findMigrationsToExecute(Manager::TYPE_DOWN);
        $this->assertTrue(is_array($migrations));
        $this->assertEmpty($migrations);

        $this->manager->logExecution(new FakeMigration($this->adapter));

        $executedMigrations = $this->manager->executedMigrations();
        $this->assertTrue(is_array($executedMigrations));
        $this->assertCount(1, $executedMigrations);

        $migrations = $this->manager->findMigrationsToExecute(Manager::TYPE_DOWN);
        $this->assertTrue(is_array($migrations));
        $this->assertEmpty($migrations);
    }

    public function testExecuteLatestMigrationFirst()
    {
        $oldName = __DIR__ . '/../fake/structure/migration_directory_1/20150428140909_first_migration.php';
        $newName = __DIR__ . '/../fake/structure/migration_directory_2/20150428140909_first_migration.php';
        rename($oldName, $newName);

        $migrations = $this->manager->findMigrationsToExecute();
        $this->checkMigrations($migrations, 1, [0 => '20150518091732']);
        foreach ($migrations as $migration) {
            $migration->migrate();
            $this->manager->logExecution($migration);
        }

        sleep(2);
        rename($newName, $oldName);

        $migrations = $this->manager->findMigrationsToExecute();
        $this->checkMigrations($migrations, 1, [0 => '20150428140909']);
        foreach ($migrations as $migration) {
            $migration->migrate();
            $this->manager->logExecution($migration);
        }

        $downMigrations = $this->manager->findMigrationsToExecute('down');
        $this->checkMigrations($downMigrations, 2, [0 => '20150428140909', 1 => '20150518091732']);
        $this->initMigration->rollback();
    }

    private function checkMigrations($migrations, $count, array $migrationDatetimes = [])
    {
        $this->assertTrue(is_array($migrations));
        $this->assertCount($count, $migrations);
        $numberOfMigrations = count($migrations);
        for ($i = 0; $i < $numberOfMigrations; ++$i) {
            $this->assertInstanceOf(AbstractMigration::class, $migrations[$i]);
            $this->assertEquals($migrationDatetimes[$i], $migrations[$i]->getDatetime());
        }
    }
}
