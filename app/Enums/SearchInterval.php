<?php

namespace App\Enums;

enum SearchInterval: int
{
    case Five = 5;
    case Fifteen = 15;
    case Thirty = 30;
    case Sixty = 60;

    /**
     * Number of scheduled runs in 24 hours.
     */
    public function runsPerDay(): int
    {
        return intdiv(1440, $this->value);
    }

    /**
     * Maximum number of keywords that fit the daily query budget.
     */
    public function keywordCap(): int
    {
        return min(20, intdiv(2000, $this->runsPerDay()));
    }
}
