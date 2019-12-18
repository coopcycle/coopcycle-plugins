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
}
