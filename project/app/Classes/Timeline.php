<?php

namespace App\Classes;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class Timeline
{
    /**
     * @var Collection
     */
    private Collection $open_hours;

    /**
     * @var Collection
     */
    private Collection $timeline;
    /**
     * @var Carbon
     */
    private Carbon $from;
    /**
     * @var Carbon
     */
    private Carbon $to;
    /**
     * @var ExceptionsHours
     */
    private ExceptionsHours $exceptions;

    public function __construct(Collection $open_hours)
    {
        $this->open_hours = $open_hours;
        $this->timeline = collect();
    }

    public function timeline(): Collection
    {
        return $this->timeline;
    }

    public function generate(Carbon $from, Carbon $to): self
    {
        $this->from = $from;
        $this->to = $to;
        $period = CarbonPeriod::create($this->from, $this->to);

        $first_date = $period->first()->toDateString();
        $last_date = $period->last()->toDateString();

        foreach ($period as $date) {
            $day_plan = $this->open_hours[$date->dayOfWeek] ?? collect();

            $start_time = $first_date === $date->toDateString() ? $date->toTimeString() : '00:00';
            $end_time = $last_date === $date->toDateString() ? $date->toTimeString() : '24:00';

//            dump($day_plan->toArray(), $start_time, $end_time);
            $day_full_plan = (new DayPlan($day_plan, $date))->generate();
//            dd($day_full_plan, $date->toDateTimeString());
            $day_full_plan = $this->exceptions->applyExceptions($day_full_plan, $date);
//            dd($day_full_plan->toArray());
//            dump('-----------------------');


            $this->timeline->put($date->toDateTimeString(), $day_full_plan);
        }

//        dd('end');
//        dd($this->timeline->toArray());
        return $this;
    }

    public function addExceptions(Collection $exceptions): self
    {
        $this->exceptions = new ExceptionsHours($exceptions);

        return $this;
    }

    /**
     * @param bool $current_state
     * @return Carbon|null
     */
    public function nextStateChange(bool $current_state): ?Carbon
    {
        $result = null;
        foreach ($this->timeline as $date => $day_plan) {
            $changed = $day_plan->filter(
                function ($plan) use ($current_state) {
                    return $plan['status'] != $current_state;
                }
            )->first();

            if ($changed) {
                $result = Carbon::createFromFormat('Y-m-d H:i:s', $date)
                    ->setTime(...explode(":", $changed['from']));

                break;
            }
        }

        return $result;
    }
}
