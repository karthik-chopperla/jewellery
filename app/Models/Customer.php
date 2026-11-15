<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dob' => 'date', 'anniversary' => 'date'];
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function oldGoldPurchases()
    {
        return $this->hasMany(OldGoldPurchase::class);
    }

    public function goldSchemes()
    {
        return $this->hasMany(GoldScheme::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function wishlists()
    {
        return $this->hasMany(CustomerWishlist::class);
    }

    public function daysUntilEvent(string $type = 'birthday'): ?int
    {
        $field = $type === 'anniversary' ? 'anniversary' : 'dob';
        $date = $this->{$field};
        if (!$date) return null;

        $now = now()->startOfDay();
        $thisYearEvent = \Carbon\Carbon::create($now->year, $date->month, $date->day)->startOfDay();
        if ($thisYearEvent->lt($now)) {
            $thisYearEvent = \Carbon\Carbon::create($now->year + 1, $date->month, $date->day)->startOfDay();
        }

        return (int) $now->diffInDays($thisYearEvent, false);
    }

    public function nextEventDate(string $type = 'birthday'): ?\Carbon\Carbon
    {
        $field = $type === 'anniversary' ? 'anniversary' : 'dob';
        $date = $this->{$field};
        if (!$date) return null;

        $now = now()->startOfDay();
        $thisYearEvent = \Carbon\Carbon::create($now->year, $date->month, $date->day)->startOfDay();
        if ($thisYearEvent->lt($now)) {
            $thisYearEvent = \Carbon\Carbon::create($now->year + 1, $date->month, $date->day)->startOfDay();
        }

        return $thisYearEvent;
    }

    public static function getUpcomingEvents(string $type = 'birthday', string $window = '7days')
    {
        $field = $type === 'anniversary' ? 'anniversary' : 'dob';

        return static::whereNotNull($field)->get()->filter(function ($customer) use ($type, $field, $window) {
            $days = $customer->daysUntilEvent($type);
            if ($days === null) return false;

            $date = $customer->{$field};
            $now = now()->startOfDay();

            switch ($window) {
                case 'today':
                case '1day':
                case '1':
                    return $days === 0;
                case '7days':
                case '7':
                    return $days >= 0 && $days <= 7;
                case '15days':
                case '15':
                    return $days >= 0 && $days <= 15;
                case '30days':
                case '1month':
                case '30':
                    return $days >= 0 && $days <= 30;
                case 'month':
                case 'this_month':
                    return $date->month === $now->month;
                default:
                    return true;
            }
        })->sortBy(fn ($c) => $c->daysUntilEvent($type))->values();
    }
}

