<?php

namespace App\Services;

use Carbon\Carbon;

class PersianCalendarService
{
    public function monthNames(): array
    {
        return ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    }

    public function gregorianToJalali(Carbon $date): array
    {
        return $this->gregorianToJalaliInts((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    public function jalaliToGregorian(int $jy, int $jm, int $jd): Carbon
    {
        [$gy, $gm, $gd] = $this->jalaliToGregorianInts($jy, $jm, $jd);

        return Carbon::create($gy, $gm, $gd)->startOfDay();
    }

    public function monthRange(int $jy, int $jm): array
    {
        $start = $this->jalaliToGregorian($jy, $jm, 1);
        $next = $jm === 12 ? $this->jalaliToGregorian($jy + 1, 1, 1) : $this->jalaliToGregorian($jy, $jm + 1, 1);

        return [$start, $next->copy()->subDay()];
    }

    public function monthLabel(int $jy, int $jm): string
    {
        return $this->monthNames()[$jm - 1].' '.$jy;
    }

    private function gregorianToJalaliInts(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [31, (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd;
        for ($i = 0; $i < $gm - 1; $i++) {
            $days += $gDaysInMonth[$i];
        }

        $jy = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        for ($jm = 0; $jm < 11 && $days >= $jDaysInMonth[$jm]; $jm++) {
            $days -= $jDaysInMonth[$jm];
        }

        return [$jy, $jm + 1, $days + 1];
    }

    private function jalaliToGregorianInts(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd;
        $days += $jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186;

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $kab = (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0);
        $salA = [0, 31, $kab ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 1; $gm <= 12 && $gd > $salA[$gm]; $gm++) {
            $gd -= $salA[$gm];
        }

        return [$gy, $gm, $gd];
    }
}
