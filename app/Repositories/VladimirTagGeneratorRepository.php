<?php

namespace App\Repositories;

use App\Models\FixedAsset;

class VladimirTagGeneratorRepository
{
    public function vladimirTagGenerator(): string
    {
        $generatedEan13Result = $this->generateEan13();
        // Check if the generated number is a duplicate or already exists in the database
        while ($this->checkDuplicateEan13($generatedEan13Result)) {
            $generatedEan13Result = $this->generateEan13();
        }

        return $generatedEan13Result;
    }
    private function generateEan13(): string
    {
        $date = date('ymd');
        static $lastRandom = 0;
        do {
            $random = mt_rand(1, 9) . mt_rand(1000, 9999);
        } while ($random === $lastRandom);
        $lastRandom = $random;

        $number = "5$date$random";

        if (strlen($number) !== 12) {
            return 'Invalid Number';
        }

        //Calculate checkDigit
        $checkDigit = $this->calculateCheckDigit($number);

        $ean13Result = $number . $checkDigit;

        return $ean13Result;
    }
    private function calculateCheckDigit(string $number): int
    {
        $evenSum = $this->calculateEvenSum($number);
        $oddSum = $this->calculateOddSum($number);

        $totalSum = $evenSum + $oddSum;
        $remainder = $totalSum % 10;
        $checkDigit = ($remainder === 0) ? 0 : 10 - $remainder;

        return $checkDigit;
    }
    private function calculateEvenSum(string $number): int
    {
        $evenSum = 0;
        for ($i = 1; $i < 12; $i += 2) {
            $evenSum += (int)$number[$i];
        }
        return $evenSum * 3;
    }
    private function calculateOddSum(string $number): int
    {
        $oddSum = 0;
        for ($i = 0; $i < 12; $i += 2) {
            $oddSum += (int)$number[$i];
        }
        return $oddSum;
    }
    private function checkDuplicateEan13(string $ean13Result): bool
    {
        $generated = [];
        return in_array($ean13Result, $generated) || FixedAsset::withTrashed()->where('vladimir_tag_number', $ean13Result)->exists();
    }
}
