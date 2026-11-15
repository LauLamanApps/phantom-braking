<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/** Which driver assistance the car itself recorded as engaged, frame by frame. */
enum DrivingAutomation: string
{
    case None = 'none';
    /** Tesla FSD (Supervised): steers, brakes and navigates. */
    case SelfDriving = 'self_driving';
    /** Lane centering with adaptive cruise (Tesla Autosteer). */
    case LaneCentering = 'lane_centering';
    /** Adaptive cruise only (Tesla TACC). */
    case AdaptiveCruise = 'adaptive_cruise';
}
