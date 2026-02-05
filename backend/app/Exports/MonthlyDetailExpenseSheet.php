<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyDetailExpenseSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $expenseData,
        private int $year,
        private int $month
    ) {
    }

    public function array(): array
    {
        $rows = [];
        $index = 1;
        
        foreach ($this->expenseData as $expense) {
            $rows[] = [
                $index++,
                $expense['date_display'],
                number_format($expense['amount'], 2),
                $expense['item_name'],
                $expense['remarks'] ?: '-',
            ];
        }
        
        return $rows;
    }

    public function headings(): array
    {
        return ['序号', '日期', '金额', '项目', '备注'];
    }

    public function title(): string
    {
        return '支出明细';
    }

    public function styles(Worksheet $sheet)
    {
        // 标题行样式
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'dc2626']], // 红色
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // 数据行样式
        $lastRow = count($this->expenseData) + 1;
        $sheet->getStyle('A2:E' . $lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // 序号列居中
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // 金额列右对齐
        $sheet->getStyle('C:C')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        return [];
    }
}
