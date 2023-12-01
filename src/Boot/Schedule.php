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
 
namespace Tobento\App\Schedule\Boot;

use Tobento\App\Boot;
use Tobento\Service\Schedule\TaskProcessor;
use Tobento\Service\Schedule\TaskProcessorInterface;
use Tobento\Service\Schedule\ScheduleProcessor;
use Tobento\Service\Schedule\ScheduleProcessorInterface;
use Tobento\Service\Schedule\Schedule as ServiceSchedule;
use Tobento\Service\Schedule\ScheduleInterface;
use Tobento\Service\Console\ConsoleInterface;

/**
 * Schedule
 */
class Schedule extends Boot
{
    public const INFO = [
        'boot' => [
            'implements schedule interfaces',
            'boots the console and adds schedule commands to console',
        ],
    ];

    public const BOOT = [
        \Tobento\App\Console\Boot\Console::class,
        \Tobento\App\Event\Boot\Event::class,
        \Tobento\App\Cache\Boot\Cache::class,
        \Tobento\App\Mail\Boot\Mail::class,
    ];

    /**
     * Boot application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // interfaces:
        $this->app->set(TaskProcessorInterface::class, TaskProcessor::class);
        $this->app->set(ScheduleProcessorInterface::class, ScheduleProcessor::class);
        $this->app->set(ScheduleInterface::class, ServiceSchedule::class)->with(['name' => 'app']);
        
        // console commands:
        $this->app->on(ConsoleInterface::class, static function(ConsoleInterface $console): void {
            $console->addCommand(\Tobento\Service\Schedule\Console\ScheduleRunCommand::class);
            $console->addCommand(\Tobento\Service\Schedule\Console\ScheduleListCommand::class);
        });
    }
}