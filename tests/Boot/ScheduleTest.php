<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);

namespace Tobento\App\Schedule\Test\Boot;

use PHPUnit\Framework\TestCase;
use Tobento\App\Schedule\Boot\Schedule;
use Tobento\Service\Schedule\ScheduleInterface;
use Tobento\Service\Schedule\ScheduleProcessorInterface;
use Tobento\Service\Schedule\TaskProcessorInterface;
use Tobento\Service\Schedule\Task;
use Tobento\Service\Console\ConsoleInterface;
use Tobento\App\AppInterface;
use Tobento\App\AppFactory;
use Tobento\App\Boot;
use Tobento\Service\Filesystem\Dir;

class ScheduleTest extends TestCase
{    
    protected function createApp(bool $deleteDir = true): AppInterface
    {
        if ($deleteDir) {
            (new Dir())->delete(__DIR__.'/../app/');
        }
        
        (new Dir())->create(__DIR__.'/../app/');
        
        $app = (new AppFactory())->createApp();
        
        $app->dirs()
            ->dir(realpath(__DIR__.'/../../'), 'root:core')
            // we set the root the same as the app, because of the console app migration.
            ->dir(realpath(__DIR__.'/../app/'), 'root')
            ->dir(realpath(__DIR__.'/../app/'), 'app')
            ->dir($app->dir('app').'config', 'config', group: 'config')
            ->dir($app->dir('root:core').'vendor', 'vendor')
            // for testing only we add public within app dir.
            ->dir($app->dir('app').'public', 'public');
        
        return $app;
    }
    
    public static function tearDownAfterClass(): void
    {
        (new Dir())->delete(__DIR__.'/../app/');
    }
    
    public function testInterfacesAreAvailable()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();

        $this->assertInstanceof(ScheduleInterface::class, $app->get(ScheduleInterface::class));
        $this->assertInstanceof(ScheduleProcessorInterface::class, $app->get(ScheduleProcessorInterface::class));
        $this->assertInstanceof(TaskProcessorInterface::class, $app->get(TaskProcessorInterface::class));
    }
    
    public function testConsoleCommandsAreAvailable()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $console = $app->get(ConsoleInterface::class);
        $this->assertTrue($console->hasCommand('schedule:run'));
        $this->assertTrue($console->hasCommand('schedule:list'));
    }
    
    public function testAddingTaskUsingApp()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
            
            $task = new Task\CallableTask(
                callable: static function (): string {
                    return 'task output';
                },
            );
            
            $schedule->task($task);
        });
        
        $this->assertSame(1, $app->get(ScheduleInterface::class)->count());
    }
    
    public function testAddingTaskUsingBoot()
    {
        $app = $this->createApp();
        
        $serviceBoot = new class($app) extends Boot {
            public const BOOT = [
                Schedule::class,
            ];

            public function boot()
            {
                $this->app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {

                    $task = new Task\CallableTask(
                        callable: static function (): string {
                            return 'task output';
                        },
                    );

                    $schedule->task($task);
                });
            }
        };
        
        $app->boot($serviceBoot);
        $app->booting();
        
        $this->assertSame(1, $app->get(ScheduleInterface::class)->count());
    }
    
    public function testScheduleRunCommand()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ScheduleInterface::class, static function(ScheduleInterface $schedule): void {
            $schedule->task(
                (new Task\CallableTask(
                    callable: static function (): string {
                        return 'task output';
                    },
                ))->id('foo')
            );
        });

        $executed = $app->get(ConsoleInterface::class)->execute(command: 'schedule:run');
        
        $this->assertSame(0, $executed->code());
        $this->assertStringContainsString('Success: task Closure with the id foo', $executed->output());
    }
}