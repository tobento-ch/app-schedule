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

namespace Tobento\App\Schedule\Test;

use PHPUnit\Framework\TestCase;
use Tobento\App\Schedule\Boot\Schedule;
use Tobento\Service\Schedule\ScheduleInterface;
use Tobento\Service\Schedule\ScheduleProcessorInterface;
use Tobento\Service\Schedule\TaskProcessorInterface;
use Tobento\Service\Schedule\Task;
use Tobento\Service\Schedule\Parameter;
use Tobento\Service\Console\ConsoleInterface;
use Tobento\Service\Console\InteractorInterface;
use Tobento\Service\Console\Command;
use Tobento\Service\Mail\Message;
use Tobento\App\AppInterface;
use Tobento\App\AppFactory;
use Tobento\App\Boot;
use Tobento\Service\Filesystem\Dir;
use Psr\SimpleCache\CacheInterface;

class RunScheduledTasksTest extends TestCase
{    
    protected function createApp(bool $deleteDir = true): AppInterface
    {
        if ($deleteDir) {
            (new Dir())->delete(__DIR__.'/app/');
        }
        
        (new Dir())->create(__DIR__.'/app/');
        
        $app = (new AppFactory())->createApp();
        
        $app->dirs()
            ->dir(realpath(__DIR__.'/../'), 'root:core')
            // we set the root the same as the app, because of the console app migration.
            ->dir(realpath(__DIR__.'/app/'), 'root')
            ->dir(realpath(__DIR__.'/app/'), 'app')
            ->dir($app->dir('app').'config', 'config', group: 'config')
            ->dir($app->dir('root:core').'vendor', 'vendor')
            // for testing only we add public within app dir.
            ->dir($app->dir('app').'public', 'public');
        
        return $app;
    }
    
    public static function tearDownAfterClass(): void
    {
        (new Dir())->delete(__DIR__.'/app/');
    }
    
    public function testCallableTask()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
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
    
    public function testCommandTask()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ConsoleInterface::class, function(ConsoleInterface $console) {
            $command = (new Command(name: 'command:foo'))
                ->handle(function(InteractorInterface $io): int {
                    $io->write('command:foo output');
                    return 0;
                });
            
            $console->addCommand($command);
        });
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
            $schedule->task(
                (new Task\CommandTask(
                    command: 'command:foo'
                ))->id('foo')
            );
        });
        
        $executed = $app->get(ConsoleInterface::class)->execute(command: 'schedule:run');
        
        $this->assertSame(0, $executed->code());
        $this->assertStringContainsString('Success: task command:foo with the id foo', $executed->output());
    }
    
    public function testPingTask()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
            $schedule->task(
                (new Task\PingTask(
                    uri: 'https://example.com/ping',
                    method: 'GET',
                    options: [],
                ))->id('foo')
            );
        });

        $executed = $app->get(ConsoleInterface::class)->execute(command: 'schedule:run');
        
        $this->assertSame(1, $executed->code());
        $this->assertStringContainsString('https://example.com/ping` resulted in a `404 Not Found` response', $executed->output());
    }
    
    public function testProcessTask()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
            $schedule->task(
                (new Task\ProcessTask(
                    process: 'echo foo',
                ))->id('foo')
            );
        });

        $executed = $app->get(ConsoleInterface::class)->execute(command: 'schedule:run');
        
        $this->assertSame(0, $executed->code());
        $this->assertStringContainsString('Success: task echo foo with the id foo', $executed->output());
    }
    
    public function testMailParameter()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
            $schedule->task(
                (new Task\CallableTask(
                    callable: static function (): string {
                        return 'task output';
                    },
                ))
                ->id('foo')
                ->after(new Parameter\Mail(
                    message: (new Message())->to('admin@example.com'),
                ))
            );
        });

        $executed = $app->get(ConsoleInterface::class)->execute(command: 'schedule:run');
        
        // fails because of dsn, but it means mailing is supported.
        $this->assertSame(1, $executed->code());
        $this->assertStringContainsString('Exception: The "smtp://user:pass@smtp.example.com:port" mailer DSN', $executed->output());
    }
    
    public function testWithoutOverlappingParameter()
    {
        $app = $this->createApp();
        $app->boot(Schedule::class);
        $app->booting();
        
        $app->get(CacheInterface::class)->set('task-processing:foo', true);
        
        $app->on(ScheduleInterface::class, function(ScheduleInterface $schedule) {
            $schedule->task(
                (new Task\CallableTask(
                    callable: static function (): string {
                        return 'task output';
                    },
                ))
                ->id('foo')
                ->withoutOverlapping()
            );
        });
        
        $executed = $app->get(ConsoleInterface::class)->execute(command: 'schedule:run');
        
        $this->assertSame(0, $executed->code());
        $this->assertStringContainsString('Exception: Task running in another process', $executed->output());
    }
}