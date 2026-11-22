<?php

namespace App\Services;

class JewelleryPriceCalculator
{
    public const PURITY_FACTORS = [
        '24K' => 1.0000,
        '22K' => 0.9167,
        '20K' => 0.8333,
        '18K' => 0.7500,
        '14K' => 0.5833,
        '9K'  => 0.3750,
        '999' => 0.9990,
        '925' => 0.9250,
        '950' => 0.9500,
    ];

    /**
     * Calculate line-item jewellery price breakdown.
     */
    public function calculateItem(array $input): array
    {
        $quantity = max(1, (int) ($input['quantity'] ?? 1));
        $grossWeight = max(0, (float) ($input['gross_weight'] ?? 0));
        $stoneWeight = max(0, (float) ($input['stone_weight'] ?? 0));
        $otherDeductions = max(0, (float) ($input['other_deductions'] ?? 0));
        $netWeight = max(0, $grossWeight - $stoneWeight - $otherDeductions);

        $metalRate = max(0, (float) ($input['metal_rate'] ?? 0));
        $metalValue = round($netWeight * $metalRate * $quantity, 2);

        $wastagePct = max(0, (float) ($input['wastage_pct'] ?? 0));
        $wastageWeightInput = $input['wastage_weight'] ?? null;
        $wastageWeight = max(0, $wastageWeightInput === null || $wastageWeightInput === ''
            ? $netWeight * ($wastagePct / 100)
            : (float) $wastageWeightInput);
        $wastageValue = round($wastageWeight * $metalRate * $quantity, 2);

        $makingType = $input['making_charge_type'] ?? 'per_gram';
        $makingRate = max(0, (float) ($input['making_charge_rate'] ?? 0));
        $makingAmount = match ($makingType) {
            'percentage' => round($metalValue * ($makingRate / 100), 2),
            'fixed'      => round($makingRate * $quantity, 2),
            'per_gram'   => round($netWeight * $makingRate * $quantity, 2),
            default      => throw new \InvalidArgumentException('Unsupported making charge type.'),
        };

        $stoneCharge = max(0, (float) ($input['stone_charge'] ?? 0)) * $quantity;
        $diamondCharge = max(0, (float) ($input['diamond_charge'] ?? 0)) * $quantity;
        $hallmarkCharge = max(0, (float) ($input['hallmark_charge'] ?? 0)) * $quantity;
        $otherCharge = max(0, (float) ($input['other_charge'] ?? 0)) * $quantity;
        $discount = max(0, (float) ($input['discount'] ?? 0)) * $quantity;

        $subtotalBeforeDiscount = $metalValue + $wastageValue + $makingAmount + $stoneCharge + $diamondCharge + $hallmarkCharge + $otherCharge;
        $taxableValue = max(0, round($subtotalBeforeDiscount - $discount, 2));

        $gstRate = max(0, (float) ($input['gst_rate'] ?? 3.00));
        $gstAmount = round($taxableValue * ($gstRate / 100), 2);
        $lineTotal = round($taxableValue + $gstAmount, 2);

        return [
            'gross_weight' => round($grossWeight, 3),
            'stone_weight' => round($stoneWeight, 3),
            'other_deductions' => round($otherDeductions, 3),
            'net_weight' => round($netWeight, 3),
            'quantity' => $quantity,
            'total_gross_weight' => round($grossWeight * $quantity, 3),
            'total_net_weight' => round($netWeight * $quantity, 3),
            'metal_rate' => round($metalRate, 2),
            'metal_value' => $metalValue,
            'wastage_pct' => $wastagePct,
            'wastage_weight' => $wastageWeight,
            'wastage_value' => $wastageValue,
            'making_charge_type' => $makingType,
            'making_charge_rate' => $makingRate,
            'making_charge_amount' => $makingAmount,
            'stone_charge' => round($stoneCharge, 2),
            'diamond_charge' => round($diamondCharge, 2),
            'hallmark_charge' => round($hallmarkCharge, 2),
            'other_charge' => round($otherCharge, 2),
            'discount' => round($discount, 2),
            'taxable_value' => $taxableValue,
            'gst_rate' => $gstRate,
            'gst_amount' => $gstAmount,
            'line_total' => $lineTotal,
        ];
    }

    /**
     * Calculate full invoice totals with GST split, round-off, and adjustments.
     */
    public function calculateInvoice(array $items, array $options = []): array
    {
        $calculatedItems = array_map(fn ($item) => $this->calculateItem($item), $items);

        $subtotal = 0;
        $makingTotal = 0;
        $stoneTotal = 0;
        $totalGst = 0;

        foreach ($calculatedItems as $item) {
            $subtotal += $item['taxable_value'];
            $makingTotal += $item['making_charge_amount'];
            $stoneTotal += $item['stone_charge'] + $item['diamond_charge'];
            $totalGst += $item['gst_amount'];
        }

        $invoiceDiscount = max(0, (float) ($options['discount_amount'] ?? 0));
        if ($invoiceDiscount > $subtotal) {
            throw new \InvalidArgumentException('Invoice discount cannot exceed the taxable subtotal.');
        }
        $netTaxable = max(0, $subtotal - $invoiceDiscount);

        if ($subtotal > 0 && $invoiceDiscount > 0) {
            $remainingDiscount = $invoiceDiscount;
            $lastIndex = array_key_last($calculatedItems);
            foreach ($calculatedItems as $index => &$item) {
                $allocatedDiscount = $index === $lastIndex
                    ? $remainingDiscount
                    : round($invoiceDiscount * ($item['taxable_value'] / $subtotal), 2);
                $allocatedDiscount = min($item['taxable_value'], $allocatedDiscount);
                $remainingDiscount = round($remainingDiscount - $allocatedDiscount, 2);
                $item['invoice_discount'] = $allocatedDiscount;
                $item['taxable_value'] = round($item['taxable_value'] - $allocatedDiscount, 2);
                $item['gst_amount'] = round($item['taxable_value'] * ($item['gst_rate'] / 100), 2);
                $item['line_total'] = round($item['taxable_value'] + $item['gst_amount'], 2);
            }
            unset($item);
            $totalGst = array_sum(array_column($calculatedItems, 'gst_amount'));
        } else {
            foreach ($calculatedItems as &$item) {
                $item['invoice_discount'] = 0.0;
            }
            unset($item);
        }

        $gstType = $options['gst_type'] ?? 'intra';
        $cgst = $gstType === 'intra' ? round($totalGst / 2, 2) : 0.0;
        $sgst = $gstType === 'intra' ? round($totalGst - $cgst, 2) : 0.0;
        $igst = $gstType === 'inter' ? round($totalGst, 2) : 0.0;

        $grossTotal = $netTaxable + $cgst + $sgst + $igst;
        $roundingMode = $options['rounding_mode'] ?? 'nearest_rupee';
        $roundedTotal = match ($roundingMode) {
            'nearest_5'  => round($grossTotal / 5) * 5,
            'nearest_10' => round($grossTotal / 10) * 10,
            'none'       => round($grossTotal, 2),
            default      => round($grossTotal),
        };
        $roundOff = round($roundedTotal - $grossTotal, 2);

        $oldGoldDeduction = max(0, (float) ($options['old_gold_deduction'] ?? 0));
        $advanceAdjusted = max(0, (float) ($options['advance_adjusted'] ?? 0));
        $schemeAdjusted = max(0, (float) ($options['scheme_adjusted'] ?? 0));
        $otherAdjusted = max(0, (float) ($options['other_adjusted'] ?? 0));

        if ($oldGoldDeduction + $advanceAdjusted + $schemeAdjusted + $otherAdjusted > $roundedTotal) {
            throw new \InvalidArgumentException('Adjustments cannot exceed the invoice total.');
        }
        $grandTotal = round($roundedTotal - $oldGoldDeduction - $advanceAdjusted - $schemeAdjusted - $otherAdjusted, 2);

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 2),
            'making_total' => round($makingTotal, 2),
            'stone_total' => round($stoneTotal, 2),
            'discount_amount' => round($invoiceDiscount, 2),
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'round_off' => $roundOff,
            'old_gold_deduction' => round($oldGoldDeduction, 2),
            'advance_adjusted' => round($advanceAdjusted, 2),
            'scheme_adjusted' => round($schemeAdjusted, 2),
            'other_adjusted' => round($otherAdjusted, 2),
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Calculate Old Gold Exchange / Purchase valuation.
     */
    public function calculateOldGold(array $input): array
    {
        $grossWeight = max(0, (float) ($input['gross_weight'] ?? 0));
        $stoneWeight = max(0, (float) ($input['stone_weight'] ?? 0));
        $netWeight = max(0, $grossWeight - $stoneWeight);

        $purityPct = min(100, max(0, (float) ($input['purity_pct'] ?? 91.60)));
        $deductionPct = min(50, max(0, (float) ($input['deduction_pct'] ?? 2.00)));
        $meltingLossPct = min(25, max(0, (float) ($input['melting_loss_pct'] ?? 1.00)));

        $fineGoldWeight = round($netWeight * ($purityPct / 100), 3);
        $effectiveWeight = round($fineGoldWeight * (1 - ($deductionPct + $meltingLossPct) / 100), 3);
        $appliedRate = max(0, (float) ($input['applied_rate'] ?? 0)); // 24K rate per gram
        $valuationAmount = round($effectiveWeight * $appliedRate, 2);

        return [
            'gross_weight' => round($grossWeight, 3),
            'stone_weight' => round($stoneWeight, 3),
            'net_weight' => round($netWeight, 3),
            'purity_pct' => $purityPct,
            'fine_gold_weight' => $fineGoldWeight,
            'deduction_pct' => $deductionPct,
            'melting_loss_pct' => $meltingLossPct,
            'effective_weight' => $effectiveWeight,
            'applied_rate' => round($appliedRate, 2),
            'valuation_amount' => $valuationAmount,
        ];
    }

    /**
     * Derive purity rate from 24K rate.
     */
    public static function deriveRateFrom24K(float $rate24k, string $purity): float
    {
        $factor = self::PURITY_FACTORS[$purity] ?? 1.0;
        return round($rate24k * $factor, 2);
    }
}

