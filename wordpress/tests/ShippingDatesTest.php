<?php

namespace Tests\WordPress;

use PHPUnit\Framework\TestCase;

class ShippingDatesTest extends TestCase
{
    private function makeDatePeriod($start, $end)
    {
        $start = new \DateTime($start);
        $end = new \DateTime($end);

        return new \DatePeriod($start, $end->diff($start), $end);
    }

    public function testTimeSlotToDatePeriods()
    {
        require_once __DIR__ . '/../src/CoopCycle.php';

        $ohs = [
            [
              "@type" => "OpeningHoursSpecification",
              "dayOfWeek" => [ "Tuesday", "Thursday" ],
              "opens" => "18:00:00",
              "closes" => "19:00:00"
            ],
            [
              "@type" => "OpeningHoursSpecification",
              "dayOfWeek" => [ "Tuesday", "Thursday" ],
              "opens" => "19:00:00",
              "closes" => "20:30:00"
            ],
        ];

        $time_slot = [
            'interval' => '2 days',
            'openingHoursSpecification' => $ohs,
        ];

        $ranges = \CoopCycle::time_slot_to_date_periods($time_slot, new \DateTime('2019-12-18 12:00:00'));

        $this->assertCount(4, $ranges);
        $this->assertEquals($this->makeDatePeriod('2019-12-19 18:00', '2019-12-19 19:00'), $ranges[0]);
        $this->assertEquals($this->makeDatePeriod('2019-12-19 19:00', '2019-12-19 20:30'), $ranges[1]);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 18:00', '2019-12-24 19:00'), $ranges[2]);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 19:00', '2019-12-24 20:30'), $ranges[3]);
    }

    public function testTimeSlotToDatePeriodsWithPriorNotice()
    {
        require_once __DIR__ . '/../src/CoopCycle.php';

        $ohs = [
            [
              "@type" => "OpeningHoursSpecification",
              "dayOfWeek" => [ "Tuesday", "Thursday" ],
              "opens" => "18:00:00",
              "closes" => "19:00:00"
            ],
            [
              "@type" => "OpeningHoursSpecification",
              "dayOfWeek" => [ "Tuesday", "Thursday" ],
              "opens" => "19:00:00",
              "closes" => "20:30:00"
            ],
        ];

        $time_slot = [
            'interval' => '2 days',
            'priorNotice' => '3 hours',
            'openingHoursSpecification' => $ohs,
        ];

        $ranges = \CoopCycle::time_slot_to_date_periods($time_slot, new \DateTime('2019-12-19 15:30:00'));

        $this->assertCount(3, $ranges);
        $this->assertEquals($this->makeDatePeriod('2019-12-19 19:00', '2019-12-19 20:30'), $ranges[0]);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 18:00', '2019-12-24 19:00'), $ranges[1]);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 19:00', '2019-12-24 20:30'), $ranges[2]);

        $ranges = \CoopCycle::time_slot_to_date_periods($time_slot, new \DateTime('2019-12-19 16:07:00'));

        $this->assertCount(4, $ranges);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 18:00', '2019-12-24 19:00'), $ranges[0]);
    }

    public function testTimeSlotToDatePeriodsWithPriorNotice2()
    {
        require_once __DIR__ . '/../src/CoopCycle.php';

        $time_slot = json_decode(file_get_contents(__DIR__ . '/payloads/prior_notice.json'), true);

        $ranges = \CoopCycle::time_slot_to_date_periods($time_slot, new \DateTime('2021-03-16 21:26:00'));

        $this->assertCount(12, $ranges);
        $this->assertEquals($this->makeDatePeriod('2021-03-17 18:00', '2021-03-17 18:30'), $ranges[0]);
    }

    public function testTimeSlotToDatePeriodsWithInterval()
    {
        require_once __DIR__ . '/../src/CoopCycle.php';

        $ohs = [
            [
              "@type" => "OpeningHoursSpecification",
              "dayOfWeek" => [ "Tuesday", "Thursday" ],
              "opens" => "18:00:00",
              "closes" => "19:00:00"
            ],
            [
              "@type" => "OpeningHoursSpecification",
              "dayOfWeek" => [ "Tuesday", "Thursday" ],
              "opens" => "19:00:00",
              "closes" => "20:30:00"
            ],
        ];

        $time_slot = [
            'interval' => '7 days',
            'openingHoursSpecification' => $ohs,
        ];

        $ranges = \CoopCycle::time_slot_to_date_periods($time_slot, new \DateTime('2019-12-18 12:00:00'));

        $this->assertCount(14, $ranges);
        $this->assertEquals($this->makeDatePeriod('2019-12-19 18:00', '2019-12-19 19:00'), $ranges[0]);
        $this->assertEquals($this->makeDatePeriod('2019-12-19 19:00', '2019-12-19 20:30'), $ranges[1]);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 18:00', '2019-12-24 19:00'), $ranges[2]);
        $this->assertEquals($this->makeDatePeriod('2019-12-24 19:00', '2019-12-24 20:30'), $ranges[3]);
    }
}
