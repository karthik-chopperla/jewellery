<?php

namespace App\Services;

use App\Models\MetalRate;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class LiveMetalRateService
{
    /**
     * Supported major Indian jewellery hubs.
     */
    public const CITIES = [
        'guwahati' => 'Guwahati',
        'mumbai' => 'Mumbai',
        'delhi' => 'Delhi',
        'kolkata' => 'Kolkata',
        'chennai' => 'Chennai',
        'bangalore' => 'Bangalore',
        'hyderabad' => 'Hyderabad',
        'ahmedabad' => 'Ahmedabad',
        'jaipur' => 'Jaipur',
        'pune' => 'Pune',
        'india' => 'All India (National Average)',
    ];

    /**
     * Fetch live market rates and persist to database.
     */
    public static function sync(string $city = 'guwahati', ?int $branchId = null, ?int $userId = null): array
    {
        $citySlug = strtolower(trim($city ?: Setting::get('default_rate_city', 'guwahati')));
        if (!array_key_exists($citySlug, self::CITIES)) {
            $citySlug = 'guwahati';
        }

        $rates = self::fetchIndianDomesticRates($citySlug);

        if (!$rates) {
            $rates = self::fetchInternationalSpotFallback($citySlug);
        }

        $now = now();
        $source = $rates['source'];
        $cityName = self::CITIES[$citySlug] ?? ucfirst($citySlug);

        // 1. Publish 24K Gold
        MetalRate::create([
            'branch_id' => $branchId,
            'metal_type' => 'Gold',
            'purity' => '24K',
            'rate_per_gram' => $rates['gold_24k'],
            'effective_at' => $now,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'notes' => "Live Rate: {$cityName} ({$source})",
        ]);

        // 2. Publish 22K Gold
        MetalRate::create([
            'branch_id' => $branchId,
            'metal_type' => 'Gold',
            'purity' => '22K',
            'rate_per_gram' => $rates['gold_22k'],
            'effective_at' => $now,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'notes' => "Live Rate: {$cityName} 916 Hallmark ({$source})",
        ]);

        // 3. Publish 18K Gold
        MetalRate::create([
            'branch_id' => $branchId,
            'metal_type' => 'Gold',
            'purity' => '18K',
            'rate_per_gram' => $rates['gold_18k'],
            'effective_at' => $now,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'notes' => "Live Rate: {$cityName} 18K ({$source})",
        ]);

        // 4. Auto-derive 20K, 14K, 9K
        foreach (['20K', '14K', '9K'] as $purity) {
            MetalRate::create([
                'branch_id' => $branchId,
                'metal_type' => 'Gold',
                'purity' => $purity,
                'rate_per_gram' => JewelleryPriceCalculator::deriveRateFrom24K($rates['gold_24k'], $purity),
                'effective_at' => $now,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => "Derived from {$cityName} 24K rate",
            ]);
        }

        // 5. Publish Silver 999
        MetalRate::create([
            'branch_id' => $branchId,
            'metal_type' => 'Silver',
            'purity' => '999',
            'rate_per_gram' => $rates['silver_999'],
            'effective_at' => $now,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'notes' => "Live Silver: {$cityName} ({$source})",
        ]);

        return [
            'city' => $cityName,
            'source' => $source,
            'gold_24k' => $rates['gold_24k'],
            'gold_22k' => $rates['gold_22k'],
            'silver_999' => $rates['silver_999'],
        ];
    }

    private static function fetchIndianDomesticRates(string $citySlug): ?array
    {
        try {
            $goldUrl = "https://groww.in/gold-rates/gold-rate-today-in-{$citySlug}";
            $opts = [
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)\r\n",
                    'timeout' => 6,
                ],
            ];
            $html = @file_get_contents($goldUrl, false, stream_context_create($opts));

            if ($html && preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/s', $html, $matches)) {
                $json = json_decode($matches[1], true);
                $phys = $json['props']['pageProps']['goldRateData']['physicalGoldRate'] ?? [];
                $cityData = $phys[$citySlug] ?? $phys['india'] ?? null;

                if ($cityData && isset($cityData['price'])) {
                    $gold24 = (float) $cityData['price']['TWENTY_FOUR'];
                    $gold22 = (float) $cityData['price']['TWENTY_TWO'];
                    $gold18 = (float) $cityData['price']['EIGHTEEN'];

                    // Silver price for this city
                    $silverRate = 195.0;
                    $silverUrl = "https://groww.in/silver-rates/silver-rate-today-in-{$citySlug}";
                    $silverHtml = @file_get_contents($silverUrl, false, stream_context_create($opts));
                    if ($silverHtml && preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/s', $silverHtml, $sm)) {
                        $sJson = json_decode($sm[1], true);
                        foreach ($sJson['props']['pageProps']['cityRates'] ?? [] as $cr) {
                            if (strtolower($cr['slug'] ?? '') === $citySlug || strtolower($cr['city'] ?? '') === $citySlug) {
                                $silverRate = (float) $cr['todayPrice'];
                                break;
                            }
                        }
                    }

                    return [
                        'source' => 'Indian Retail Market Feed',
                        'gold_24k' => $gold24,
                        'gold_22k' => $gold22,
                        'gold_18k' => $gold18,
                        'silver_999' => $silverRate,
                    ];
                }
            }
        } catch (\Throwable) {
            // fallback
        }

        return null;
    }

    private static function fetchInternationalSpotFallback(string $citySlug): array
    {
        $inrRate = 87.5;
        $goldUsdPerOz = 3200.0;
        $silverUsdPerOz = 38.0;

        try {
            $goldRes = Http::timeout(5)->get('https://api.gold-api.com/price/XAU');
            $silverRes = Http::timeout(5)->get('https://api.gold-api.com/price/XAG');
            $fxRes = Http::timeout(5)->get('https://open.er-api.com/v6/latest/USD');

            if ($fxRes->successful()) {
                $inrRate = (float) ($fxRes->json()['rates']['INR'] ?? 87.5);
            }
            if ($goldRes->successful()) {
                $goldUsdPerOz = (float) $goldRes->json()['price'];
            }
            if ($silverRes->successful()) {
                $silverUsdPerOz = (float) $silverRes->json()['price'];
            }
        } catch (\Throwable) {}

        // Indian import duty (BCD 15% + AIDC cess = ~16.8% landing factor)
        $dutyMultiplier = 1.168;

        $rawGoldPerGram = ($goldUsdPerOz * $inrRate) / 31.1034768;
        $gold24k = round($rawGoldPerGram * $dutyMultiplier, 2);
        $gold22k = JewelleryPriceCalculator::deriveRateFrom24K($gold24k, '22K');
        $gold18k = JewelleryPriceCalculator::deriveRateFrom24K($gold24k, '18K');

        $rawSilverPerGram = ($silverUsdPerOz * $inrRate) / 31.1034768;
        $silver999 = round($rawSilverPerGram * 1.32, 2);

        return [
            'source' => 'Spot + Indian Customs Import Duty (Landed)',
            'gold_24k' => $gold24k,
            'gold_22k' => $gold22k,
            'gold_18k' => $gold18k,
            'silver_999' => $silver999,
        ];
    }
}

