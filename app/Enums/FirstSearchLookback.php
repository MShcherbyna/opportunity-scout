<?php

namespace App\Enums;

enum FirstSearchLookback: int
{
    case None = 0;
    case OneDay = 1;
    case ThreeDays = 3;
}
