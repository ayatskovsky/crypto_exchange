<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\UpdateExchangeRatesMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
final readonly class UpdateExchangeRatesSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::every('5 minutes', new UpdateExchangeRatesMessage())
            );
    }
}
