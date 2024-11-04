<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor {

    case Success = 'Unopened';
    case Pending = 'Pending';
    case Failed = 'Failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Pending => 'Pending',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Success => 'success',
            self::Pending => 'warning',
            self::Failed => 'danger',
        };
    }
}
