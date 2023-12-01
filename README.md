# App Schedule

Schedule support for the app using the [Schedule Service](https://github.com/tobento-ch/service-schedule).

## Table of Contents

- [Getting Started](#getting-started)
    - [Requirements](#requirements)
- [Documentation](#documentation)
    - [App](#app)
    - [Schedule Boot](#schedule-boot)
        - [Scheduling Tasks](#scheduling-tasks)
        - [Running Scheduled Tasks](#running-scheduled-tasks)
- [Credits](#credits)
___

# Getting Started

Add the latest version of the app schedule project running this command.

```
composer require tobento/app-schedule
```

## Requirements

- PHP 8.0 or greater

# Documentation

## App

Check out the [**App Skeleton**](https://github.com/tobento-ch/app-skeleton) if you are using the skeleton.

You may also check out the [**App**](https://github.com/tobento-ch/app) to learn more about the app in general.

## Schedule Boot

The schedule boot does the following:

* implements schedule interfaces
* boots the [Console Boot](https://github.com/tobento-ch/app-console#console-boot) and adds schedule commands to console

```php
use Tobento\App\AppFactory;
use Tobento\Service\Schedule\ScheduleInterface;
use Tobento\Service\Schedule\ScheduleProcessorInterface;
use Tobento\Service\Schedule\TaskProcessorInterface;

// Create the app
$app = (new AppFactory())->createApp();

// Add directories:
$app->dirs()
    ->dir(realpath(__DIR__.'/../'), 'root')
    ->dir(realpath(__DIR__.'/../app/'), 'app')
    ->dir($app->dir('app').'config', 'config', group: 'config')
    ->dir($app->dir('root').'public', 'public')
    ->dir($app->dir('root').'vendor', 'vendor');

// Adding boots
$app->boot(\Tobento\App\Schedule\Boot\Schedule::class);
$app->booting();

// Implemented interfaces:
$schedule = $app->get(ScheduleInterface::class);
$scheduleProcessor = $app->get(ScheduleProcessorInterface::class);
$taskProcessor = $app->get(TaskProcessorInterface::class);

// Run the app
$app->run();
```

If you are not using the [App Skeleton](https://github.com/tobento-ch/app-skeleton/) check out the [App Console Boot](https://github.com/tobento-ch/app-console#console-boot) section as you might adjust the ```app``` file.

### Scheduling Tasks

You can schedule tasks in severval ways:

**Using the app**

You may use the app ```on``` method to schedule tasks only if the schedule is requested.

```php
use Tobento\App\AppFactory;
use Tobento\Service\Schedule\ScheduleInterface;

// Create the app
$app = (new AppFactory())->createApp();

// Add directories:
$app->dirs()
    ->dir(realpath(__DIR__.'/../'), 'root')
    ->dir(realpath(__DIR__.'/../app/'), 'app')
    ->dir($app->dir('app').'config', 'config', group: 'config')
    ->dir($app->dir('root').'public', 'public')
    ->dir($app->dir('root').'vendor', 'vendor');

// Adding boots:
$app->boot(\Tobento\App\Schedule\Boot\Schedule::class);

// Adding tasks:
$app->on(ScheduleInterface::class, static function(ScheduleInterface $schedule): void {
    $schedule->task($task);
});

// Run the app
$app->run();
```

**Using a boot**

You may create a boot for scheduling tasks:

```php
use Tobento\App\Boot;
use Tobento\App\Schedule\Boot\Schedule;
use Tobento\Service\Schedule\ScheduleInterface;

class MyScheduleTasksBoot extends Boot
{
    public const BOOT = [
        // you may ensure the schedule boot.
        Schedule::class,
    ];
    
    public function boot()
    {
        $this->app->on(ScheduleInterface::class, static function(ScheduleInterface $schedule): void {
            $schedule->task($task);
        });
    }
}
```

Check out the [Schedule Service - Schedule](https://github.com/tobento-ch/service-schedule#schedule) section to learn more about it.

Furthermore, all the [Tasks](https://github.com/tobento-ch/service-schedule#tasks) and [Task Parameters](https://github.com/tobento-ch/service-schedule#task-parameters) are ready to use without any requirements.

### Running Scheduled Tasks

To run the scheduled tasks, add a cron configuration entry to your server that runs the schedule:run command every minute.

```
* * * * * cd /path-to-your-project && php app schedule:run >> /dev/null 2>&1
```

# Credits

- [Tobias Strub](https://www.tobento.ch)
- [All Contributors](../../contributors)