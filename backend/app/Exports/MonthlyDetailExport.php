<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyDetailExport implements WithMultipleSheets
{
    public function __construct(
        private array $data,
        private int $year,
        private int $month
    ) {
    }

    public function sheets(): array
    {
        return [
            new MonthlyDetailIncomeSheet($this->data['income_data'], $this->year, $this->month),
            new MonthlyDetailExpenseSheet($this->data['expense_data'], $this->year, $this->month),
            new MonthlyDetailSummarySheet($this->data['summary'], $this->year, $this->month),
        ];
    }
}
